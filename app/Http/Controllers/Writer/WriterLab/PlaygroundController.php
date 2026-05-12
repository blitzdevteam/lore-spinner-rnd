<?php

declare(strict_types=1);

namespace App\Http\Controllers\Writer\WriterLab;

use App\Ai\Agents\NarrationAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\WriterLabDraft;
use App\Support\WriterLab\WriterLabLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Multi-event Playground — stateless turn engine that mirrors the runtime narrator.
 *
 * The writer selects N events in Chapter.vue, the front-end opens a drawer and
 * fires one `/turn` request per round-trip. The controller renders the SAME
 * `ai.agents.narration.system-prompt` view the runtime uses, so what the writer
 * tests is exactly what the player will see.
 *
 * Nothing is persisted server-side: history + world_state are echoed back by
 * the client on every turn. No Game row is created. Active (non-activated)
 * edit drafts for the previewed events are overlaid so writers can test their
 * unactivated changes inline.
 */
final class PlaygroundController extends Controller
{
    /**
     * Start a playground session — resolves the event sequence the writer
     * selected and overlays any active edit drafts. Stateless: returns
     * everything the front-end needs to drive the loop.
     */
    public function start(Request $request, Story $story, Chapter $chapter): JsonResponse
    {
        $data = $request->validate([
            'event_ids'   => ['required', 'array', 'min:1'],
            'event_ids.*' => ['required', 'integer'],
        ]);

        $events = Event::whereIn('id', $data['event_ids'])
            ->where('chapter_id', $chapter->id)
            ->orderBy('position')
            ->get();

        if ($events->isEmpty()) {
            WriterLabLog::warning('playground.start.no_events', [
                'story_id'   => $story->id,
                'chapter_id' => $chapter->id,
                'event_ids'  => $data['event_ids'],
            ]);
            return response()->json(['error' => 'No matching events in this chapter.'], 422);
        }

        // Index draft overlays so we can flag which events have unactivated edits.
        $eventIds = $events->pluck('id')->all();
        $draftsByEventId = WriterLabDraft::query()
            ->where('chapter_id', $chapter->id)
            ->where('type', 'edit')
            ->whereNotIn('status', ['activated'])
            ->get()
            ->filter(fn (WriterLabDraft $d): bool => is_array($d->source_event_ids)
                && count(array_intersect($d->source_event_ids, $eventIds)) > 0)
            ->keyBy(fn (WriterLabDraft $d): int => (int) ($d->source_event_ids[0] ?? 0));

        $sequence = $events->map(fn (Event $e): array => [
            'id'             => $e->id,
            'position'       => $e->position,
            'title'          => $e->title,
            'session_number' => $e->session_number,
            'has_draft'      => $draftsByEventId->has($e->id),
        ])->all();

        WriterLabLog::info('playground.start', [
            'story_id'    => $story->id,
            'chapter_id'  => $chapter->id,
            'event_count' => count($sequence),
            'with_drafts' => $draftsByEventId->keys()->all(),
        ]);

        return response()->json([
            'chapter'  => ['id' => $chapter->id, 'position' => $chapter->position, 'title' => $chapter->title],
            'story'    => ['id' => $story->id, 'title' => $story->title],
            'sequence' => $sequence,
        ]);
    }

    /**
     * Run one playground turn against the live runtime system prompt.
     *
     * Body:
     *   event_id              int      currently-playing event
     *   conversation_history  array    [{role, text}, ...] all prior turns in this event
     *   world_state           array    accumulated state_delta merges (client-tracked)
     *   player_action         string   '' for the auto-fire opening turn
     *   turn_count            int      turns already taken in THIS event (0 = first)
     */
    public function turn(Request $request, Story $story, Chapter $chapter): JsonResponse
    {
        $data = $request->validate([
            'event_id'             => ['required', 'integer'],
            'conversation_history' => ['present', 'array'],
            'conversation_history.*.role' => ['required', 'string'],
            'conversation_history.*.text' => ['required', 'string'],
            'world_state'          => ['present', 'array'],
            // Allow null/empty — ConvertEmptyStringsToNull turns "" into null.
            // The opening auto-fire turn always sends an empty action.
            'player_action'        => ['present', 'nullable', 'string'],
            'turn_count'           => ['required', 'integer', 'min:0'],
        ]);

        // Normalise: null (from ConvertEmptyStringsToNull) → empty string
        $data['player_action'] = $data['player_action'] ?? '';

        $event = Event::query()
            ->with('chapter')
            ->where('id', $data['event_id'])
            ->where('chapter_id', $chapter->id)
            ->first();

        if (! $event) {
            WriterLabLog::warning('playground.turn.event_not_found', [
                'chapter_id' => $chapter->id,
                'event_id'   => $data['event_id'],
            ]);
            return response()->json(['error' => 'Event not found in this chapter.'], 422);
        }

        // Overlay active edit draft for this event (so the writer previews their
        // unactivated edit). If none exists, the live event row is used as-is.
        $overlay = $this->loadEditOverlay($chapter, $event);

        $currentEvent = [
            'position'        => $event->position,
            'title'           => $event->title,
            'content'         => is_array($overlay) ? ($overlay['content'] ?? $event->content) : $event->content,
            'objectives'      => is_array($overlay) ? ($overlay['objectives'] ?? $event->objectives) : $event->objectives,
            'attributes'      => is_array($overlay) ? ($overlay['attributes'] ?? $event->attributes) : $event->attributes,
            'requires_choice' => is_array($overlay) && isset($overlay['requires_choice'])
                ? (bool) $overlay['requires_choice']
                : ($event->requires_choice ?? true),
        ];

        $sessionAdaptation = $this->resolveSessionAdaptation($story, $event->session_number);

        // isSessionStart: only when current event matches the session's authored
        // start event AND this is the very first turn of the playground for this event.
        $isSessionStart = false;
        if ($sessionAdaptation?->entry_point_diagnosis) {
            $startEventId = $sessionAdaptation->entry_point_diagnosis['start_event_id'] ?? null;
            $isSessionStart = $startEventId !== null
                && $event->id === (int) $startEventId
                && $data['turn_count'] === 0;
        }

        // isSessionEnd: same trigger logic as runtime
        $isSessionEnd       = false;
        $sessionCloseDesign = null;
        if ($sessionAdaptation?->session_close_design && $data['turn_count'] === 0) {
            $closeDesign    = $sessionAdaptation->session_close_design;
            $triggerEventId = $closeDesign['session_close_trigger_event_id'] ?? null;
            if ($triggerEventId !== null) {
                $isSessionEnd = $event->id === (int) $triggerEventId;
            }
            if ($isSessionEnd) {
                $sessionCloseDesign = $closeDesign;
            }
        }

        $storyData = $story->system_prompt ?? [];

        $systemPrompt = view('ai.agents.narration.system-prompt', [
            'characterName'      => $storyData['character_name'] ?? null,
            'worldRules'         => $storyData['world_rules'] ?? [],
            'toneAndStyle'       => $storyData['tone_and_style'] ?? null,
            'previousEvents'     => $this->getPreviousEvents($event, 3),
            'currentEvent'       => $currentEvent,
            'nextEvents'         => $this->getNextEvents($event, 3),
            'turnCount'          => $data['turn_count'],
            'isFirstTurnInEvent' => $data['turn_count'] === 0,
            'sessionAdaptation'  => $sessionAdaptation,
            'isSessionStart'     => $isSessionStart,
            'isSessionEnd'       => $isSessionEnd,
            'sessionCloseDesign' => $sessionCloseDesign,
            'worldState'         => $data['world_state'],
            'deterministicMatch' => null,
            'playerChoiceEchoes' => [],
        ])->render();

        $logContext = [
            'story_id'        => $story->id,
            'chapter_id'      => $chapter->id,
            'event_id'        => $event->id,
            'event_position'  => $event->position,
            'turn_count'      => $data['turn_count'],
            'history_len'     => count($data['conversation_history']),
            'world_state_keys' => array_keys($data['world_state']),
            'is_session_start' => $isSessionStart,
            'is_session_end'   => $isSessionEnd,
            'overlay_used'    => is_array($overlay) && array_filter($overlay, static fn ($v) => $v !== null && $v !== '') !== [],
        ];

        return WriterLabLog::track('playground.turn', $logContext, function () use ($systemPrompt, $data) {
            try {
                $response = NarrationAgent::make(customInstructions: $systemPrompt)
                    ->prompt(
                        view('ai.agents.narration.prompt', [
                            'conversationHistory' => $data['conversation_history'],
                            'playerAction'        => $data['player_action'],
                            'deterministicMatch'  => null,
                        ])->render()
                    );

                $stateDelta = is_array($response['state_delta'] ?? null) ? $response['state_delta'] : [];

                $payload = [
                    'response'             => $response['response'] ?? '',
                    'choices'              => $response['choices'] ?? [],
                    'advance_event'        => (bool) ($response['advance_event'] ?? false),
                    'input_classification' => (string) ($response['input_classification'] ?? ''),
                    'mapped_option'        => (string) ($response['mapped_option'] ?? ''),
                    'mapped_choice_id'     => (string) ($response['mapped_choice_id'] ?? ''),
                    'state_delta'          => $stateDelta,
                ];

                WriterLabLog::debug('playground.turn.response', [
                    'response_bytes' => strlen((string) $payload['response']),
                    'advance_event'  => $payload['advance_event'],
                    'choices_count'  => count($payload['choices']),
                    'state_delta_keys' => array_keys($stateDelta),
                ]);

                return response()->json($payload);
            } catch (Throwable $e) {
                WriterLabLog::error('playground.turn.exception', [], $e);
                return response()->json([
                    'error' => 'Playground turn failed: ' . $e->getMessage(),
                ], 500);
            }
        });
    }

    /**
     * Load the active edit draft overlay for a single event, if any.
     *
     * @return array{content: ?string, objectives: ?string, attributes: ?array, requires_choice: ?bool}|null
     */
    private function loadEditOverlay(Chapter $chapter, Event $event): ?array
    {
        $draft = WriterLabDraft::query()
            ->where('chapter_id', $chapter->id)
            ->where('type', 'edit')
            ->whereNotIn('status', ['activated'])
            ->whereJsonContains('source_event_ids', $event->id)
            ->orderByDesc('id')
            ->first();

        if (! $draft) {
            return null;
        }

        return [
            'content'         => $draft->rewritten_content ?: null,
            'objectives'      => $draft->derived_objectives ?: null,
            'attributes'      => $draft->derived_attributes ?: null,
            'requires_choice' => $draft->requires_choice ?? null,
        ];
    }

    /**
     * Mirrors PromptController::getPreviousEvents — chapter-natural with
     * cross-chapter fallback at chapter starts.
     *
     * @return array<int, array{position: int, title: string, objectives: string|null}>
     */
    private function getPreviousEvents(Event $currentEvent, int $take): array
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
     * Mirrors PromptController::getNextEvents — chapter-natural lookahead.
     * Always 3, matches the runtime narrator.
     *
     * @return array<int, array{position: int, title: string}>
     */
    private function getNextEvents(Event $currentEvent, int $take): array
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

    private function resolveSessionAdaptation(Story $story, ?int $sessionNumber): ?SessionAdaptation
    {
        if ($sessionNumber === null) {
            return null;
        }

        return SessionAdaptation::query()
            ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $story->id))
            ->where('session_number', $sessionNumber)
            ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
            ->first();
    }
}
