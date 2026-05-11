<?php

declare(strict_types=1);

namespace App\Http\Controllers\Writer\WriterLab;

use App\Ai\Agents\WriterLab\ChoiceAlignmentAgent;
use App\Ai\Agents\WriterLab\EventCombinerAgent;
use App\Ai\Agents\NarrationAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\WriterLabDraft;
use App\Models\WriterLabVersion;
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

        $result = EventCombinerAgent::make()->prompt($prompt)->toArray();

        $draft = WriterLabDraft::create([
            'story_id'           => $story->id,
            'chapter_id'         => $chapter->id,
            'session_number'     => $sessionNumber,
            'type'               => 'combine',
            'source_event_ids'   => $events->pluck('id')->all(),
            'rewritten_content'  => $result['rewritten_content'] ?? '',
            'derived_objectives' => $result['derived_objectives'] ?? null,
            'derived_attributes' => $result['derived_attributes'] ?? null,
            'beat_type'          => $result['beat_type'] ?? null,
            'requires_choice'    => $result['requires_choice'] ?? true,
            'canonical_anchors'  => $result['canonical_anchors'] ?? $canonicalAnchors,
            'previous_state'     => $previousState,
            'status'             => 'ai_written',
        ]);

        return to_route('writer.writer-lab.drafts.show', [
            'story'   => $story->id,
            'chapter' => $chapter->id,
            'draft'   => $draft->id,
        ]);
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
        ]);

        $draft->update(array_merge($data, ['status' => 'draft']));

        return back()->with('success', 'Draft saved.');
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

            // Snapshot before any write
            $affectedEvents = $draft->source_event_ids
                ? Event::whereIn('id', $draft->source_event_ids)->get()
                : collect();

            $nextVersionNumber = (WriterLabVersion::where('story_id', $story->id)
                ->where('session_number', $draft->session_number ?? 0)
                ->max('version_number') ?? 0) + 1;

            WriterLabVersion::create([
                'story_id'            => $story->id,
                'session_number'      => $draft->session_number ?? 0,
                'version_number'      => $nextVersionNumber,
                'snapshot_events'     => $affectedEvents->toArray(),
                'snapshot_adaptation' => $sessionAdaptation?->toArray(),
                'is_active'           => false,
                'note'                => "Activated draft #{$draft->id} ({$draft->type})",
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
        $sourceEvent   = $sourceEventId ? Event::find($sourceEventId) : null;

        $sessionAdaptation = $this->resolveSessionAdaptation($story, $draft->session_number);
        $storyData         = $story->system_prompt ?? [];

        $nextEvents = $sourceEvent
            ? Event::where('chapter_id', $chapter->id)
                ->where('position', '>', $sourceEvent->position)
                ->orderBy('position')
                ->take(2)
                ->get()
                ->map(fn (Event $e): array => ['position' => $e->position, 'title' => $e->title])
                ->all()
            : [];

        // Render the exact same system prompt view used by the runtime narrator
        $systemPrompt = view('ai.agents.narration.system-prompt', [
            'characterName'      => $storyData['character_name'] ?? null,
            'worldRules'         => $storyData['world_rules'] ?? [],
            'toneAndStyle'       => $storyData['tone_and_style'] ?? null,
            'previousEvents'     => [],
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
            'isSessionStart'     => true,
            'isSessionEnd'       => false,
            'sessionCloseDesign' => null,
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
     * Create or overwrite an edit draft for a single event directly from the
     * Chapter.vue inline editor. Returns JSON so the page never navigates away.
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

        $previousState = $this->buildPreviousState(collect([$event]));

        $draft = WriterLabDraft::create([
            'story_id'          => $story->id,
            'chapter_id'        => $chapter->id,
            'session_number'    => $event->session_number,
            'type'              => 'edit',
            'source_event_ids'  => [$event->id],
            'rewritten_content' => $data['content'],
            'requires_choice'   => $data['requires_choice'],
            'beat_type'         => $data['beat_type'] ?? null,
            'derived_objectives' => $data['objectives'] ?? null,
            'derived_attributes' => $data['attributes'] ?? null,
            'adaptation_patch'  => $data['adaptation_patch'] ?? null,
            'previous_state'    => $previousState,
            'status'            => 'writer_approved',
        ]);

        return response()->json(['draft_id' => $draft->id]);
    }

    /**
     * Create a draft that carries only an adaptation_patch (cold open edit,
     * choice design edits) with no event content change.
     * Returns JSON for inline AJAX from Chapter.vue.
     */
    public function createAdaptationEdit(Request $request, Story $story, Chapter $chapter): JsonResponse
    {
        $data = $request->validate([
            'session_number'   => ['required', 'integer'],
            'adaptation_patch' => ['required', 'array'],
        ]);

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

        // Original content comes from previous_state snapshot
        $previousState   = $draft->previous_state ?? [];
        $originalContent = $previousState['events'][0]['content'] ?? $sourceEvent->content;

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

        try {
            $agent  = \App\Ai\Agents\WriterLab\ScriptChangeImpactAgent::make();
            $result = $agent->prompt($prompt)->toArray();

            return response()->json(['impact' => $result]);
        } catch (Throwable $e) {
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
}
