<?php

declare(strict_types=1);

namespace App\Http\Controllers\Writer\WriterLab;

use App\Ai\Agents\EventObjectiveAndAttributesExtractor;
use App\Ai\Agents\WriterLab\ChoiceAlignmentAgent;
use App\Ai\Agents\WriterLab\EventCombinerAgent;
use App\Ai\Agents\WriterLab\ScriptChangeImpactAgent;
use App\Ai\Agents\NarrationAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\WriterLabDraft;
use App\Models\WriterLabVersion;
use App\Support\WriterLab\WriterLabLog;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Throwable;

final class DraftController extends Controller
{
    // ── Combine ───────────────────────────────────────────────────────────────

    /**
     * Merge 2–N events from the same chapter into a single AI-rewritten draft.
     */
    public function combine(Request $request, Story $story, Chapter $chapter): RedirectResponse
    {
        $data = $request->validate([
            'event_ids'   => ['required', 'array', 'min:2'],
            'event_ids.*' => ['required', 'integer'],
        ]);

        $events = Event::whereIn('id', $data['event_ids'])
            ->orderBy('position')
            ->get();

        // Enforce chapter boundary
        $crossChapter = $events->filter(fn (Event $e): bool => $e->chapter_id !== $chapter->id);
        if ($crossChapter->isNotEmpty()) {
            throw ValidationException::withMessages([
                'event_ids' => 'All selected events must belong to the same chapter.',
            ]);
        }

        if ($events->count() < 2) {
            throw ValidationException::withMessages([
                'event_ids' => 'At least two events are required for a combine operation.',
            ]);
        }

        $canonicalAnchors = $this->buildCanonicalAnchors($events);
        $previousState    = $this->buildPreviousState($events);
        $sessionNumber    = $events->first()?->session_number;
        $sessionAdaptation = $this->resolveSessionAdaptation($story, $sessionNumber);

        $styleProfile = $story->system_prompt ?? [];
        $coldOpen     = $sessionAdaptation?->entry_point_diagnosis['cold_open'] ?? null;
        $beatMap      = $sessionAdaptation?->session_architecture['beat_map'] ?? null;
        $choiceDesign = $sessionAdaptation?->session_choice_design;

        $prompt = view('ai.agents.writer-lab.event-combiner.prompt', [
            'styleProfile'      => $styleProfile,
            'coldOpen'          => $coldOpen,
            'beatMap'           => $beatMap,
            'choiceDesign'      => $choiceDesign,
            'canonicalAnchors'  => $canonicalAnchors,
            'sourceEvents'      => $events->map(fn (Event $e): array => [
                'position'   => $e->position,
                'title'      => $e->title,
                'content'    => $e->content,
                'objectives' => $e->objectives,
                'attributes' => $e->attributes,
            ])->all(),
        ])->render();

        $logContext = [
            'story_id'      => $story->id,
            'chapter_id'    => $chapter->id,
            'session_number' => $sessionNumber,
            'event_ids'     => $events->pluck('id')->all(),
            'event_count'   => $events->count(),
        ];

        // 1) Run the editorial compressor — produces only rewritten_content
        //    and canonical_anchors. Derived fields are obtained downstream.
        $result = WriterLabLog::track('combine.compressor', $logContext, fn () =>
            EventCombinerAgent::make()->prompt($prompt)->toArray()
        );

        $rewrittenContent = (string) ($result['rewritten_content'] ?? '');
        $aiAnchors        = is_array($result['canonical_anchors'] ?? null)
            ? $result['canonical_anchors']
            : $canonicalAnchors;

        // 2) Derive objectives + attributes via the SAME pipeline agent that
        //    populated the originals — so the schema and conventions match
        //    exactly. We feed the surrounding events as context so the
        //    extractor knows what was already established/changed.
        $derived = $this->extractObjectivesAndAttributes(
            $story,
            $chapter,
            $events,
            $rewrittenContent,
            $events->first()?->title ?? 'Combined event',
        );

        // 3) Derive beat_type heuristically from the source events (the pipeline
        //    beat assignment is session-level, not per-event — so the most
        //    faithful per-event answer is "the dominant beat the source events
        //    were already assigned to"). Writer can override in the editor.
        $beatType = $this->dominantBeatType($events, $beatMap);
        if (is_string($beatType) && $beatType !== '') {
            $beatType = strtolower($beatType);
        } else {
            $beatType = null;
        }

        // 4) requires_choice: if any source event was authored as a choice
        //    beat, the combined block inherits it. Conservative default true.
        $requiresChoice = $events->contains(fn (Event $e): bool => (bool) ($e->requires_choice ?? true));

        $draft = WriterLabDraft::create([
            'story_id'           => $story->id,
            'chapter_id'         => $chapter->id,
            'session_number'     => $sessionNumber,
            'type'               => 'combine',
            'source_event_ids'   => $events->pluck('id')->all(),
            'rewritten_content'  => $rewrittenContent,
            'derived_objectives' => $derived['objectives'],
            'derived_attributes' => $derived['attributes'],
            'beat_type'          => $beatType,
            'requires_choice'    => $requiresChoice,
            'canonical_anchors'  => $aiAnchors,
            'previous_state'     => $previousState,
            'status'             => 'ai_written',
        ]);

        WriterLabLog::info('combine.draft_created', $logContext + [
            'draft_id'         => $draft->id,
            'rewritten_bytes'  => strlen($rewrittenContent),
            'anchors_count'    => count($aiAnchors),
            'derived_objectives_present' => $derived['objectives'] !== null,
            'derived_attributes_count'   => is_array($derived['attributes']) ? count($derived['attributes']) : 0,
            'beat_type'        => $beatType,
            'requires_choice'  => $requiresChoice,
        ]);

        return to_route('writer.writer-lab.drafts.show', [
            'story'   => $story->id,
            'chapter' => $chapter->id,
            'draft'   => $draft->id,
        ]);
    }

    /**
     * Runs the pipeline's EventObjectiveAndAttributesExtractor against a
     * synthetic event whose content is the rewrite. Mirrors the exact
     * surrounding-events context the original pipeline uses.
     *
     * @param  EloquentCollection<int, Event>  $sourceEvents
     * @return array{objectives: ?string, attributes: ?array<int, string>}
     */
    private function extractObjectivesAndAttributes(
        Story $story,
        Chapter $chapter,
        EloquentCollection $sourceEvents,
        string $newContent,
        string $newTitle,
    ): array {
        if (trim($newContent) === '') {
            return ['objectives' => null, 'attributes' => null];
        }

        // Pull 5 events before the first source and 5 events after the last
        // source, scoped to the chapter — same as the original adaptation pipeline.
        $first = $sourceEvents->first();
        $last  = $sourceEvents->last();
        if (! $first || ! $last) {
            return ['objectives' => null, 'attributes' => null];
        }

        $previousEvents = Event::query()
            ->where('chapter_id', $chapter->id)
            ->where('position', '<', $first->position)
            ->orderByDesc('position')
            ->take(5)
            ->get()
            ->sortBy('position')
            ->values();

        $nextEvents = Event::query()
            ->where('chapter_id', $chapter->id)
            ->where('position', '>', $last->position)
            ->orderBy('position')
            ->take(5)
            ->get();

        // Synthetic target — never persisted. Carries the rewrite as content.
        $target = new Event([
            'title'   => $newTitle,
            'content' => $newContent,
        ]);

        $prompt = view('ai.agents.event-objective-and-attribute-extractor.prompt', [
            'previousEvents' => $previousEvents,
            'targetEvent'    => $target,
            'nextEvents'     => $nextEvents,
        ])->render();

        try {
            $result = WriterLabLog::track('combine.extractor', [
                'story_id'   => $story->id,
                'chapter_id' => $chapter->id,
                'prev_count' => $previousEvents->count(),
                'next_count' => $nextEvents->count(),
                'content_bytes' => strlen($newContent),
            ], fn () => EventObjectiveAndAttributesExtractor::make()->prompt($prompt)->toArray());

            // The agent returns `objective` (singular). DB column is `objectives`.
            $objectivesText = isset($result['objective']) && is_string($result['objective'])
                ? trim($result['objective'])
                : null;

            $attributes = isset($result['attributes']) && is_array($result['attributes'])
                ? array_values(array_filter(array_map(
                    static fn ($a): string => is_string($a) ? trim($a) : '',
                    $result['attributes'],
                )))
                : null;

            return [
                'objectives' => $objectivesText !== '' ? $objectivesText : null,
                'attributes' => ($attributes && count($attributes) > 0) ? $attributes : null,
            ];
        } catch (Throwable $e) {
            WriterLabLog::error('combine.extractor.failed', [
                'story_id'   => $story->id,
                'chapter_id' => $chapter->id,
            ], $e);
            return ['objectives' => null, 'attributes' => null];
        }
    }

    /**
     * Picks the most representative beat_type for a combined block by counting
     * which beat_map entry each source event matches best. Tie-breaks toward
     * the later-position beat (pacing collapses forward).
     *
     * @param  EloquentCollection<int, Event>  $events
     * @param  array<int, array<string, mixed>>|null  $beatMap
     */
    private function dominantBeatType(EloquentCollection $events, ?array $beatMap): ?string
    {
        if (! is_array($beatMap) || count($beatMap) === 0) {
            return null;
        }

        $counts = [];
        $latest = null;
        foreach ($events as $event) {
            $match = $this->bestBeatMatch($event, $beatMap);
            if ($match === null) continue;
            $key = $match['beat_type'] ?? null;
            if (! is_string($key) || $key === '') continue;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $latest = $key;
        }

        if (empty($counts)) return null;

        arsort($counts);
        $top      = array_keys($counts);
        $topCount = $counts[$top[0]];
        $tied     = array_filter($top, fn (string $k): bool => $counts[$k] === $topCount);

        return count($tied) > 1 && $latest !== null && in_array($latest, $tied, true)
            ? $latest
            : $top[0];
    }

    /**
     * Mirrors WriterLabController::bestBeatMatch — returns the beat_map entry
     * whose moment/type text shares the most word overlap with the event content.
     *
     * @param  array<int, array<string, mixed>>  $beatMap
     * @return array<string, mixed>|null
     */
    private function bestBeatMatch(Event $event, array $beatMap): ?array
    {
        // Match WriterLabController: overlap title + content against beat_map `moment`
        // (the pipeline schema uses `moment`, not `beat_moment`).
        $eventTokens = $this->tokenize((string) ($event->title ?? '') . ' ' . (string) ($event->content ?? ''));
        if (count($eventTokens) === 0) {
            return null;
        }

        $best = null;
        $bestScore = 0;
        foreach ($beatMap as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $haystack = (string) ($entry['moment'] ?? '') . ' ' . (string) ($entry['beat_type'] ?? '');
            $score    = count(array_intersect($eventTokens, $this->tokenize($haystack)));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = $entry;
            }
        }

        return $best;
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        $clean = strtolower(preg_replace('/[^a-z0-9\s]/i', ' ', $text) ?? '');
        $parts = preg_split('/\s+/', $clean) ?: [];
        return array_values(array_unique(array_filter(
            $parts,
            static fn (string $w): bool => mb_strlen($w) >= 4,
        )));
    }

    // ── Split ─────────────────────────────────────────────────────────────────

    /**
     * Begin a split operation on a single event.
     * Creates a draft with two parts pre-populated (part 2 empty for writer to fill).
     */
    public function split(Request $request, Story $story, Chapter $chapter): RedirectResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer'],
        ]);

        $event = Event::findOrFail($data['event_id']);

        if ($event->chapter_id !== $chapter->id) {
            throw ValidationException::withMessages([
                'event_id' => 'Event does not belong to this chapter.',
            ]);
        }

        $draft = WriterLabDraft::create([
            'story_id'         => $story->id,
            'chapter_id'       => $chapter->id,
            'session_number'   => $event->session_number,
            'type'             => 'split',
            'source_event_ids' => [$event->id],
            'previous_state'   => $this->buildPreviousState(collect([$event])),
            'split_parts'      => [
                [
                    'title'           => $event->title . ' (Part 1)',
                    'content'         => $event->content,
                    'objectives'      => $event->objectives,
                    'attributes'      => $event->attributes ?? [],
                    'beat_type'       => null,
                    'requires_choice' => false,
                ],
                [
                    'title'           => $event->title . ' (Part 2)',
                    'content'         => '',
                    'objectives'      => '',
                    'attributes'      => [],
                    'beat_type'       => null,
                    'requires_choice' => $event->requires_choice,
                ],
            ],
            'status' => 'draft',
        ]);

        return to_route('writer.writer-lab.drafts.show', [
            'story'   => $story->id,
            'chapter' => $chapter->id,
            'draft'   => $draft->id,
        ]);
    }

    // ── Reorder ───────────────────────────────────────────────────────────────

    /**
     * Create a reorder draft from the writer's drag-and-drop result.
     * Goes directly to writer_approved — no AI step, no content review.
     */
    public function reorder(Request $request, Story $story, Chapter $chapter): RedirectResponse
    {
        $data = $request->validate([
            'event_order'                  => ['required', 'array', 'min:2'],
            'event_order.*.event_id'       => ['required', 'integer'],
            'event_order.*.new_position'   => ['required', 'integer', 'min:1'],
        ]);

        $eventIds = collect($data['event_order'])->pluck('event_id');
        $events   = Event::whereIn('id', $eventIds)->get();

        $crossChapter = $events->filter(fn (Event $e): bool => $e->chapter_id !== $chapter->id);
        if ($crossChapter->isNotEmpty()) {
            throw ValidationException::withMessages([
                'event_order' => 'All events in a reorder must belong to the same chapter.',
            ]);
        }

        $previousState = $this->buildPreviousState(
            Event::where('chapter_id', $chapter->id)->orderBy('position')->get()
        );

        $draft = WriterLabDraft::create([
            'story_id'       => $story->id,
            'chapter_id'     => $chapter->id,
            'type'           => 'reorder',
            'event_order'    => $data['event_order'],
            'previous_state' => $previousState,
            'status'         => 'writer_approved',
        ]);

        return to_route('writer.writer-lab.drafts.show', [
            'story'   => $story->id,
            'chapter' => $chapter->id,
            'draft'   => $draft->id,
        ]);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(Story $story, Chapter $chapter, WriterLabDraft $draft): Response
    {
        $sourceEvents = [];
        if ($draft->source_event_ids) {
            $sourceEvents = Event::whereIn('id', $draft->source_event_ids)
                ->orderBy('position')
                ->get(['id', 'position', 'title', 'content', 'objectives', 'attributes'])
                ->toArray();
        }

        return inertia('WriterLab/Draft', [
            'story'        => ['id' => $story->id, 'title' => $story->title, 'slug' => $story->slug],
            'chapter'      => ['id' => $chapter->id, 'position' => $chapter->position, 'title' => $chapter->title],
            'draft'        => $draft,
            'sourceEvents' => $sourceEvents,
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    /**
     * Writer manually edits the draft content.
     * Resets status to 'draft' so they must re-approve after any edit.
     */
    public function update(Request $request, Story $story, Chapter $chapter, WriterLabDraft $draft): RedirectResponse
    {
        $data = $request->validate([
            'rewritten_content'  => ['nullable', 'string'],
            'split_parts'        => ['nullable', 'array'],
            'adaptation_patch'   => ['nullable', 'array'],
            'requires_choice'    => ['nullable', 'boolean'],
            'beat_type'          => ['nullable', 'string'],
            'derived_objectives' => ['nullable', 'string'],
            'derived_attributes' => ['nullable', 'array'],
            'derived_attributes.*' => ['string'],
        ]);

        // Normalise empties — beat_type and objectives can be cleared by the writer
        if (isset($data['beat_type']) && trim($data['beat_type']) === '') {
            $data['beat_type'] = null;
        }
        if (isset($data['derived_objectives']) && trim($data['derived_objectives']) === '') {
            $data['derived_objectives'] = null;
        }
        if (isset($data['derived_attributes']) && count($data['derived_attributes']) === 0) {
            $data['derived_attributes'] = null;
        }

        $draft->update(array_merge($data, ['status' => 'draft']));

        WriterLabLog::info('draft.update', [
            'story_id'   => $story->id,
            'chapter_id' => $chapter->id,
            'draft_id'   => $draft->id,
            'fields'     => array_keys($data),
        ]);

        return back()->with('success', 'Draft saved.');
    }

    // ── Discard ───────────────────────────────────────────────────────────────

    /**
     * Discard a draft. Allowed for any non-activated draft — activated drafts
     * must go through Versions to roll back. Soft-protective: requires the
     * draft to belong to the current chapter (route-model binding already
     * scoped) and refuses if status is 'activated'.
     */
    public function destroy(Request $request, Story $story, Chapter $chapter, WriterLabDraft $draft): JsonResponse|RedirectResponse
    {
        if ($draft->status === 'activated') {
            $msg = 'Activated drafts cannot be discarded — use the Versions page to roll back.';
            if ($request->wantsJson()) {
                return response()->json(['error' => $msg], 422);
            }
            return back()->withErrors(['draft' => $msg]);
        }

        $context = [
            'story_id'   => $story->id,
            'chapter_id' => $chapter->id,
            'draft_id'   => $draft->id,
            'type'       => $draft->type,
            'status'     => $draft->status,
        ];

        $draft->delete();

        WriterLabLog::info('draft.discard', $context);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }
        return to_route('writer.writer-lab.chapter', [
            'story'   => $story->id,
            'chapter' => $chapter->id,
        ])->with('success', 'Draft discarded.');
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    public function approve(Request $request, Story $story, Chapter $chapter, WriterLabDraft $draft): RedirectResponse
    {
        if ($draft->type === 'split') {
            // Validate that part 2 has content before approving
            $parts = $draft->split_parts ?? [];
            foreach ($parts as $i => $part) {
                if (empty(trim($part['content'] ?? ''))) {
                    throw ValidationException::withMessages([
                        'split_parts' => "Part " . ($i + 1) . " must have content before approving.",
                    ]);
                }
            }
        }

        $draft->update(['status' => 'writer_approved']);

        return back()->with('success', 'Draft approved.');
    }

    // ── Activate ──────────────────────────────────────────────────────────────

    /**
     * Write the approved draft into the live events table.
     * Dispatches on draft type: combine | split | reorder | edit.
     * All paths snapshot before writing and run inside a transaction.
     */
    public function activate(Request $request, Story $story, Chapter $chapter, WriterLabDraft $draft): RedirectResponse
    {
        if ($draft->status !== 'writer_approved') {
            return back()->with('error', 'Only writer-approved drafts can be activated.');
        }

        DB::transaction(function () use ($draft, $chapter, $story): void {
            $sessionAdaptation = $this->resolveSessionAdaptation($story, $draft->session_number);

            // ── Full-chapter snapshot ─────────────────────────────────────
            // Always capture EVERY event in the chapter and EVERY session
            // adaptation whose events touch the chapter. This lets restore
            // recover from combine/split/reorder operations idempotently
            // even if the draft only listed a subset of events.
            $allEvents = Event::query()
                ->where('chapter_id', $chapter->id)
                ->orderBy('position')
                ->get();

            $sessionNumbers = $allEvents->pluck('session_number')->filter()->unique()->values()->all();

            $allAdaptations = empty($sessionNumbers)
                ? collect()
                : SessionAdaptation::query()
                    ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $story->id))
                    ->whereIn('session_number', $sessionNumbers)
                    ->get();

            $nextVersionNumber = (WriterLabVersion::where('story_id', $story->id)
                ->where('chapter_id', $chapter->id)
                ->max('version_number') ?? 0) + 1;

            WriterLabVersion::create([
                'story_id'             => $story->id,
                'chapter_id'           => $chapter->id,
                'snapshot_kind'        => 'chapter',
                'session_number'       => $draft->session_number ?? 0,
                'version_number'       => $nextVersionNumber,
                'snapshot_events'      => $allEvents->toArray(),
                'snapshot_adaptation'  => $sessionAdaptation?->toArray(),
                'snapshot_adaptations' => $allAdaptations->map(fn (SessionAdaptation $a) => $a->toArray())->all(),
                'is_active'            => false,
                'note'                 => "Activated draft #{$draft->id} ({$draft->type})",
            ]);

            WriterLabLog::info('activate.snapshot', [
                'story_id'           => $story->id,
                'chapter_id'         => $chapter->id,
                'draft_id'           => $draft->id,
                'draft_type'         => $draft->type,
                'version_number'     => $nextVersionNumber,
                'snapshotted_events' => $allEvents->count(),
                'snapshotted_adapts' => $allAdaptations->count(),
            ]);

            match ($draft->type) {
                'combine' => $this->activateCombine($draft),
                'split'   => $this->activateSplit($draft, $chapter),
                'reorder' => $this->activateReorder($draft),
                'edit'    => $this->activateEdit($draft),
                default   => null,
            };

            // Apply any adaptation layer patches (cold_open edits, choice text changes, etc.)
            // IMPORTANT: use recursive merge so partial patches (e.g. {cold_open: '...'} inside
            // entry_point_diagnosis) don't overwrite the rest of the JSON column.
            if ($draft->adaptation_patch && $sessionAdaptation) {
                $allowedColumns = [
                    'entry_point_diagnosis',
                    'session_architecture',
                    'session_choice_design',
                    'choice_consequence_map',
                    'session_close_design',
                ];
                foreach ($draft->adaptation_patch as $column => $patchValue) {
                    if (! in_array($column, $allowedColumns, strict: true)) {
                        continue;
                    }
                    if (! is_array($patchValue)) {
                        continue;
                    }
                    $existing = $sessionAdaptation->$column;
                    // Merge: incoming patch takes precedence; existing keys not in patch are preserved
                    $merged = is_array($existing)
                        ? array_replace_recursive($existing, $patchValue)
                        : $patchValue;
                    $sessionAdaptation->update([$column => $merged]);
                }
            }

            $draft->update(['status' => 'activated', 'activated_at' => now()]);
        });

        return to_route('writer.writer-lab.chapter', [
            'story'   => $story->id,
            'chapter' => $chapter->id,
        ])->with('success', 'Draft activated — live events updated.');
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    /**
     * Fire the same NarrationAgent against the draft's rewritten_content.
     * Mirrors GameController::generateFirstNarration() exactly — no game record created,
     * no private method calls needed. Direct view render + NarrationAgent call.
     */
    public function preview(Request $request, Story $story, Chapter $chapter, WriterLabDraft $draft): JsonResponse
    {
        // Determine which content to preview
        $previewContent = match ($draft->type) {
            'split'  => $draft->split_parts[0]['content'] ?? '',
            'edit', 'combine' => $draft->rewritten_content ?? '',
            default  => $draft->rewritten_content ?? '',
        };

        if (empty(trim($previewContent))) {
            return response()->json(['error' => 'No content to preview.'], 422);
        }

        // Use the survivor event's position context, or the first source event
        $sourceEventId = $draft->source_event_ids[0] ?? null;
        $sourceEvent   = $sourceEventId ? Event::with('chapter')->find($sourceEventId) : null;

        $sessionAdaptation = $this->resolveSessionAdaptation($story, $draft->session_number);
        $storyData         = $story->system_prompt ?? [];

        $previousEvents = $sourceEvent ? $this->previewGetPreviousEvents($sourceEvent, 3) : [];
        $nextEvents      = $sourceEvent ? $this->previewGetNextEvents($sourceEvent, 3) : [];

        $isSessionStart = false;
        if ($sessionAdaptation?->entry_point_diagnosis && $sourceEvent) {
            $startEventId = $sessionAdaptation->entry_point_diagnosis['start_event_id'] ?? null;
            $isSessionStart = $startEventId !== null && $sourceEvent->id === (int) $startEventId;
        }

        $isSessionEnd       = false;
        $sessionCloseDesign = null;
        if ($sessionAdaptation?->session_close_design && $sourceEvent) {
            $closeDesign    = $sessionAdaptation->session_close_design;
            $triggerEventId = $closeDesign['session_close_trigger_event_id'] ?? null;
            if ($triggerEventId !== null) {
                $isSessionEnd = $sourceEvent->id === (int) $triggerEventId;
            } else {
                $sessionEventRange = $sessionAdaptation->entry_point_diagnosis['session_event_range'] ?? null;
                if (is_string($sessionEventRange) && str_contains($sessionEventRange, '-')) {
                    [, $rangeEnd] = array_map('intval', explode('-', $sessionEventRange, 2));
                    $isSessionEnd = $sourceEvent->position >= ($rangeEnd - 4);
                }
            }
            if ($isSessionEnd) {
                $sessionCloseDesign = $closeDesign;
            }
        }

        // Render the exact same system prompt view used by the runtime narrator
        $systemPrompt = view('ai.agents.narration.system-prompt', [
            'characterName'      => $storyData['character_name'] ?? null,
            'worldRules'         => $storyData['world_rules'] ?? [],
            'toneAndStyle'       => $storyData['tone_and_style'] ?? null,
            'previousEvents'     => $previousEvents,
            'currentEvent'       => [
                'position'        => $sourceEvent?->position ?? 1,
                'title'           => $draft->type === 'combine'
                    ? implode(' + ', collect($draft->source_event_ids ?? [])
                        ->map(fn ($id): string => Event::find($id)?->title ?? '')
                        ->filter()
                        ->all())
                    : ($sourceEvent?->title ?? 'Preview'),
                'content'         => $previewContent,
                'objectives'      => $draft->derived_objectives ?? $sourceEvent?->objectives,
                'attributes'      => $draft->derived_attributes ?? $sourceEvent?->attributes,
                'requires_choice' => $draft->requires_choice,
            ],
            'nextEvents'         => $nextEvents,
            'turnCount'          => 0,
            'isFirstTurnInEvent' => true,
            'sessionAdaptation'  => $sessionAdaptation,
            'isSessionStart'     => $isSessionStart,
            'isSessionEnd'       => $isSessionEnd,
            'sessionCloseDesign' => $sessionCloseDesign,
            'worldState'         => [],
            'deterministicMatch' => null,
            'playerChoiceEchoes' => [],
        ])->render();

        try {
            $response = NarrationAgent::make(customInstructions: $systemPrompt)
                ->prompt(
                    view('ai.agents.narration.prompt', [
                        'conversationHistory' => [],
                        'playerAction'        => '',
                        'deterministicMatch'  => null,
                    ])->render()
                );

            return response()->json([
                'response' => $response['response'] ?? '',
                'choices'  => $response['choices'] ?? [],
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Narration preview failed: ' . $e->getMessage()], 500);
        }
    }

    // ── Direct inline edit (AJAX) ─────────────────────────────────────────────

    /**
     * Upsert an edit draft for a single event.
     *
     * If an active (non-activated) edit draft already exists for this event, we
     * update it in place — so iterative saves from Chapter.vue don't accumulate
     * orphan draft rows. The previous_state snapshot is captured once on the
     * first save and preserved across subsequent saves so rollback always
     * reaches the pre-edit state, not the most-recent intermediate save.
     */
    public function createEdit(Request $request, Story $story, Chapter $chapter): JsonResponse
    {
        $data = $request->validate([
            'event_id'         => ['required', 'integer'],
            'content'          => ['required', 'string'],
            'requires_choice'  => ['required', 'boolean'],
            'beat_type'        => ['nullable', 'string'],
            'objectives'       => ['nullable', 'string'],
            'attributes'       => ['nullable', 'array'],
            'attributes.*'     => ['string'],
            'adaptation_patch' => ['nullable', 'array'],
        ]);

        $event = Event::findOrFail($data['event_id']);

        if ($event->chapter_id !== $chapter->id) {
            return response()->json(['error' => 'Event does not belong to this chapter.'], 422);
        }

        // Look for a non-activated edit draft already touching this event.
        // We use whereJsonContains so we hit the source_event_ids array element directly.
        $existing = WriterLabDraft::where('chapter_id', $chapter->id)
            ->where('type', 'edit')
            ->whereNotIn('status', ['activated'])
            ->whereJsonContains('source_event_ids', $event->id)
            ->orderByDesc('id')
            ->first();

        $payload = [
            'rewritten_content'  => $data['content'],
            'requires_choice'    => $data['requires_choice'],
            'beat_type'          => $data['beat_type'] ?? null,
            'derived_objectives' => $data['objectives'] ?? null,
            'derived_attributes' => $data['attributes'] ?? null,
            'adaptation_patch'   => $data['adaptation_patch'] ?? null,
            'status'             => 'writer_approved',
        ];

        if ($existing !== null) {
            // Keep the original previous_state so version rollback always reaches
            // the live row as it existed BEFORE any draft edit cycle.
            $existing->update($payload);
            return response()->json(['draft_id' => $existing->id]);
        }

        $draft = WriterLabDraft::create(array_merge($payload, [
            'story_id'         => $story->id,
            'chapter_id'       => $chapter->id,
            'session_number'   => $event->session_number,
            'type'             => 'edit',
            'source_event_ids' => [$event->id],
            'previous_state'   => $this->buildPreviousState(collect([$event])),
        ]));

        return response()->json(['draft_id' => $draft->id]);
    }

    /**
     * Upsert an adaptation-only draft for a session (cold open, choice design,
     * session close). One draft per (chapter, session_number) — incoming
     * adaptation_patch is merged recursively into the existing draft's patch
     * so the writer can edit cold open, then choices, then close, all in one
     * draft that activates atomically.
     */
    public function createAdaptationEdit(Request $request, Story $story, Chapter $chapter): JsonResponse
    {
        $data = $request->validate([
            'session_number'   => ['required', 'integer'],
            'adaptation_patch' => ['required', 'array'],
        ]);

        $existing = WriterLabDraft::where('chapter_id', $chapter->id)
            ->where('session_number', $data['session_number'])
            ->where('type', 'edit')
            ->whereNotIn('status', ['activated'])
            ->whereNull('source_event_ids')
            ->orderByDesc('id')
            ->first();

        if ($existing !== null) {
            $mergedPatch = array_replace_recursive(
                $existing->adaptation_patch ?? [],
                $data['adaptation_patch']
            );
            $existing->update([
                'adaptation_patch' => $mergedPatch,
                'status'           => 'writer_approved',
            ]);
            return response()->json(['draft_id' => $existing->id]);
        }

        $draft = WriterLabDraft::create([
            'story_id'         => $story->id,
            'chapter_id'       => $chapter->id,
            'session_number'   => $data['session_number'],
            'type'             => 'edit',
            'adaptation_patch' => $data['adaptation_patch'],
            'status'           => 'writer_approved',
        ]);

        return response()->json(['draft_id' => $draft->id]);
    }

    /**
     * Analyse the draft's rewritten_content against the session's choice_design
     * and suggest updated choice question + A/B/C that align with the new script.
     * Returns JSON — rendered inline as a diff panel in Chapter.vue.
     */
    public function suggestChoices(Request $request, Story $story, Chapter $chapter, WriterLabDraft $draft): JsonResponse
    {
        if (empty(trim($draft->rewritten_content ?? ''))) {
            return response()->json(['error' => 'Save the edited content first.'], 422);
        }

        $sessionAdaptation = $this->resolveSessionAdaptation($story, $draft->session_number);

        if ($sessionAdaptation === null || empty($sessionAdaptation->session_choice_design)) {
            return response()->json(['error' => 'No choice design found for this session.'], 422);
        }

        $sourceEvent = $draft->source_event_ids ? Event::find($draft->source_event_ids[0]) : null;

        $prompt = view('ai.agents.writer-lab.choice-alignment.prompt', [
            'editedContent'    => $draft->rewritten_content,
            'eventTitle'       => $sourceEvent?->title ?? 'Event',
            'coldOpen'         => $sessionAdaptation->entry_point_diagnosis['cold_open'] ?? null,
            'choiceDesign'     => $sessionAdaptation->session_choice_design,
            'styleProfile'     => $story->system_prompt ?? [],
        ])->render();

        try {
            $agent  = ChoiceAlignmentAgent::make();
            $result = $agent->prompt($prompt)->toArray();

            return response()->json(['suggestion' => $result]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'AI suggestion failed: ' . $e->getMessage()], 500);
        }
    }

    // ── Comprehensive script-change impact analysis (AJAX) ────────────────────

    /**
     * Analyse every adaptation layer that may be stale after an event edit:
     *   - event objectives + attributes
     *   - session beat_map entry
     *   - session_choice_design (question + options)
     *   - choice_consequence_map review flag
     *   - cross-session canonical anchor warnings
     *
     * Returns structured JSON for the Chapter.vue impact panel.
     */
    public function analyseImpact(Request $request, Story $story, Chapter $chapter, WriterLabDraft $draft): JsonResponse
    {
        if (empty(trim($draft->rewritten_content ?? ''))) {
            return response()->json(['error' => 'Save the edited content first.'], 422);
        }

        $sourceEvent = $draft->source_event_ids ? Event::find($draft->source_event_ids[0]) : null;

        if (! $sourceEvent) {
            return response()->json(['error' => 'Source event not found.'], 422);
        }

        $sessionNumber     = $draft->session_number ?? $sourceEvent->session_number;
        $sessionAdaptation = $this->resolveSessionAdaptation($story, $sessionNumber);

        // Original content comes from previous_state snapshot.
        // buildPreviousState() stores a flat list of event rows: [[...], ...] — NOT ['events' => ...].
        $previousState   = $draft->previous_state ?? [];
        $firstSnap       = is_array($previousState) ? ($previousState[0] ?? null) : null;
        $originalContent = is_array($firstSnap) && isset($firstSnap['content'])
            ? (string) $firstSnap['content']
            : (string) $sourceEvent->content;

        // Attempt to find the downstream session adaptation for cross-session seeding
        $nextSessionAdaptation = null;
        if ($sessionNumber !== null) {
            $nextSessionAdaptation = $this->resolveSessionAdaptation($story, $sessionNumber + 1);
        }

        $prompt = view('ai.agents.writer-lab.script-change-impact.prompt', [
            'eventTitle'          => $sourceEvent->title,
            'sessionNumber'       => $sessionNumber,
            'eventPosition'       => $sourceEvent->position,
            'originalContent'     => $originalContent,
            'editedContent'       => $draft->rewritten_content,
            'currentObjectives'   => $sourceEvent->objectives,
            'currentAttributes'   => $sourceEvent->attributes,
            // beat_map lives inside session_architecture, not as a top-level column
            'beatMap'             => $sessionAdaptation?->session_architecture['beat_map'] ?? [],
            'choiceDesign'        => $sessionAdaptation?->session_choice_design ?? [],
            'consequenceMap'      => $sessionAdaptation?->choice_consequence_map ?? [],
            // next_session_awareness is also inside session_architecture
            'nextSessionAwareness' => $sessionAdaptation?->session_architecture['next_session_awareness'] ?? null,
            'nextSessionColdOpen' => $nextSessionAdaptation?->entry_point_diagnosis['cold_open'] ?? null,
        ])->render();

        $logContext = [
            'story_id'        => $story->id,
            'chapter_id'      => $chapter->id,
            'draft_id'        => $draft->id,
            'session_number'  => $sessionNumber,
            'event_id'        => $sourceEvent->id,
            'orig_bytes'      => strlen((string) $originalContent),
            'edit_bytes'      => strlen((string) $draft->rewritten_content),
        ];

        try {
            $result = WriterLabLog::track('analyse_impact', $logContext, fn () =>
                ScriptChangeImpactAgent::make()->prompt($prompt)->toArray()
            );

            WriterLabLog::debug('analyse_impact.summary', $logContext + [
                'severity'                 => $result['severity'] ?? null,
                'objectives_needs_update'  => (bool) ($result['objectives_needs_update'] ?? false),
                'beat_map_needs_update'    => (bool) ($result['beat_map_needs_update'] ?? false),
                'choice_design_needs_update' => (bool) ($result['choice_design_needs_update'] ?? false),
                'choice_slot_affected'     => $result['choice_slot_affected'] ?? null,
                'consequence_map_needs_review' => (bool) ($result['consequence_map_needs_review'] ?? false),
                'cross_session_concern'    => (bool) ($result['cross_session_concern'] ?? false),
            ]);

            return response()->json(['impact' => $result]);
        } catch (Throwable $e) {
            WriterLabLog::error('analyse_impact.failed', $logContext, $e);
            return response()->json(['error' => 'Impact analysis failed: ' . $e->getMessage()], 500);
        }
    }

    // ── Private activate helpers ──────────────────────────────────────────────

    private function activateCombine(WriterLabDraft $draft): void
    {
        $events   = Event::whereIn('id', $draft->source_event_ids)->orderBy('position')->get();
        $survivor = $events->first();
        $absorbed = $events->slice(1);

        $survivor->update([
            'content'         => $draft->rewritten_content,
            'objectives'      => $draft->derived_objectives,
            'attributes'      => $draft->derived_attributes ?? $survivor->attributes,
            'requires_choice' => $draft->requires_choice,
        ]);

        Event::whereIn('id', $absorbed->pluck('id'))->delete();
    }

    private function activateSplit(WriterLabDraft $draft, Chapter $chapter): void
    {
        $parts       = $draft->split_parts ?? [];
        $extraCount  = count($parts) - 1;
        $originalId  = $draft->source_event_ids[0];
        $original    = Event::findOrFail($originalId);

        if ($extraCount > 0) {
            // Make room: shift all subsequent positions up by extraCount
            Event::where('chapter_id', $chapter->id)
                ->where('position', '>', $original->position)
                ->orderByDesc('position')
                ->each(function (Event $e) use ($extraCount): void {
                    $e->update(['position' => $e->position + $extraCount]);
                });
        }

        // Update original event with part 1
        $part1 = $parts[0];
        $original->update([
            'title'           => $part1['title'] ?? $original->title,
            'content'         => $part1['content'],
            'objectives'      => $part1['objectives'] ?? null,
            'attributes'      => $part1['attributes'] ?? null,
            'requires_choice' => $part1['requires_choice'] ?? false,
        ]);

        // Insert remaining parts as new events
        for ($i = 1; $i < count($parts); $i++) {
            $part = $parts[$i];
            Event::create([
                'chapter_id'      => $chapter->id,
                'position'        => $original->position + $i,
                'title'           => $part['title'] ?? $original->title . " (Part " . ($i + 1) . ")",
                'content'         => $part['content'],
                'objectives'      => $part['objectives'] ?? null,
                'attributes'      => $part['attributes'] ?? null,
                'session_number'  => $original->session_number,
                'requires_choice' => $part['requires_choice'] ?? true,
            ]);
        }
    }

    private function activateReorder(WriterLabDraft $draft): void
    {
        foreach ($draft->event_order ?? [] as $item) {
            Event::where('id', $item['event_id'])
                ->update(['position' => $item['new_position']]);
        }
    }

    private function activateEdit(WriterLabDraft $draft): void
    {
        $eventId = $draft->source_event_ids[0] ?? null;
        if ($eventId === null) {
            // Adaptation-only draft (no source event) — adaptation_patch is applied
            // by the caller's loop; nothing to do here.
            return;
        }

        $fields = [
            'content'         => $draft->rewritten_content,
            'requires_choice' => $draft->requires_choice,
        ];

        // Only overwrite objectives / attributes when the writer explicitly set them
        // (via direct edit or accepted an AI suggestion). Null means "leave as-is".
        if (! empty($draft->derived_objectives)) {
            $fields['objectives'] = $draft->derived_objectives;
        }
        if (! empty($draft->derived_attributes)) {
            $fields['attributes'] = $draft->derived_attributes;
        }

        // NOTE: beat_type is stored on the draft for context / preview, but the
        // events table has no beat_type column. Beat-map entries live inside
        // session_architecture['beat_map'] on SessionAdaptation. Any beat_type
        // change should be captured as an adaptation_patch on the draft and is
        // applied by the caller's adaptation_patch loop.

        Event::where('id', $eventId)->update($fields);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** Build canonical anchors from merged event objectives and flattened attributes. */
    private function buildCanonicalAnchors(\Illuminate\Database\Eloquent\Collection $events): array
    {
        $anchors = [];

        foreach ($events as $event) {
            if (!empty($event->objectives)) {
                $anchors[] = $event->objectives;
            }
            if (is_array($event->attributes)) {
                foreach ($event->attributes as $attr) {
                    if (is_string($attr) && !empty($attr)) {
                        $anchors[] = $attr;
                    }
                }
            }
        }

        return array_values(array_unique($anchors));
    }

    /** Snapshot current event rows for rollback. */
    private function buildPreviousState(\Illuminate\Database\Eloquent\Collection $events): array
    {
        return $events->map(fn (Event $e): array => [
            'id'              => $e->id,
            'chapter_id'      => $e->chapter_id,
            'position'        => $e->position,
            'title'           => $e->title,
            'content'         => $e->content,
            'objectives'      => $e->objectives,
            'attributes'      => $e->attributes,
            'session_number'  => $e->session_number,
            'requires_choice' => $e->requires_choice,
        ])->all();
    }

    /** Resolve SessionAdaptation with fallback to session 1. */
    private function resolveSessionAdaptation(Story $story, ?int $sessionNumber): ?SessionAdaptation
    {
        if ($sessionNumber !== null) {
            $sa = SessionAdaptation::query()
                ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $story->id))
                ->where('session_number', $sessionNumber)
                ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
                ->first();

            if ($sa !== null) {
                return $sa;
            }
        }

        return SessionAdaptation::query()
            ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $story->id))
            ->where('session_number', 1)
            ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
            ->first();
    }

    /**
     * Mirrors PromptController::getPreviousEvents for Draft preview parity.
     *
     * @return array<int, array{position: int, title: string, objectives: string|null}>
     */
    private function previewGetPreviousEvents(Event $currentEvent, int $take): array
    {
        $events = Event::query()
            ->where('chapter_id', $currentEvent->chapter_id)
            ->where('position', '<', $currentEvent->position)
            ->orderByDesc('position')
            ->take($take)
            ->get();

        if ($events->count() < $take) {
            $remaining = $take - $events->count();
            $prevChapter = Chapter::query()
                ->where('story_id', $currentEvent->chapter->story_id)
                ->where('position', '<', $currentEvent->chapter->position)
                ->orderByDesc('position')
                ->first();

            if ($prevChapter) {
                $events = $events->merge(
                    $prevChapter->events()->orderByDesc('position')->take($remaining)->get()
                );
            }
        }

        return $events->sortBy('position')
            ->map(fn (Event $e): array => [
                'position'   => $e->position,
                'title'      => $e->title,
                'objectives' => $e->objectives,
            ])
            ->values()
            ->all();
    }

    /**
     * Mirrors PromptController::getNextEvents for Draft preview parity.
     *
     * @return array<int, array{position: int, title: string}>
     */
    private function previewGetNextEvents(Event $currentEvent, int $take): array
    {
        $events = Event::query()
            ->where('chapter_id', $currentEvent->chapter_id)
            ->where('position', '>', $currentEvent->position)
            ->orderBy('position')
            ->take($take)
            ->get();

        if ($events->count() < $take) {
            $remaining = $take - $events->count();
            $nextChapter = Chapter::query()
                ->where('story_id', $currentEvent->chapter->story_id)
                ->where('position', '>', $currentEvent->chapter->position)
                ->orderBy('position')
                ->first();

            if ($nextChapter) {
                $events = $events->merge(
                    $nextChapter->events()->orderBy('position')->take($remaining)->get()
                );
            }
        }

        return $events->map(fn (Event $e): array => [
            'position' => $e->position,
            'title'    => $e->title,
        ])->all();
    }
}
