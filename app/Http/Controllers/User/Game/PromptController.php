<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Game;

use App\Ai\Agents\NarrationAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Game\Prompt\StorePromptRequest;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\Game;
use App\Models\SessionAdaptation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

final class PromptController extends Controller
{
    public function store(
        #[CurrentUser] User $user,
        Game $game,
        StorePromptRequest $request,
    ): RedirectResponse {
        $prompt = $request->string('prompt')->toString();
        $isContinue = $prompt === '__continue__';

        $game->prompts()->latest()->first()?->update([
            'prompt' => $isContinue ? '__continue__' : $prompt,
        ]);

        $currentEvent = $game->currentEvent;

        $turnCount = $game->prompts()
            ->where('event_id', $currentEvent->id)
            ->count();

        $conversationHistory = $this->buildConversationHistory($game);

        $sessionAdaptation = $this->resolveSessionAdaptation($game, $currentEvent);

        $deterministicMatch = $this->matchAuthoredChoice(
            playerInput: $isContinue ? '' : $prompt,
            sessionAdaptation: $sessionAdaptation,
        );

        $systemPrompt = $this->renderSystemPrompt(
            story: $game->story,
            currentEvent: $currentEvent,
            turnCount: $turnCount,
            sessionAdaptation: $sessionAdaptation,
            worldState: $game->world_state ?? [],
            deterministicMatch: $deterministicMatch,
        );

        $aiResult = $this->generateNarration(
            systemPrompt: $systemPrompt,
            conversationHistory: $conversationHistory,
            playerAction: $isContinue ? 'Continue forward' : $prompt,
            deterministicMatch: $deterministicMatch,
        );

        $shouldAdvance = $aiResult['advance_event'];

        if (! $shouldAdvance && $turnCount >= 5) {
            $shouldAdvance = true;
        }

        $nextEvent = null;
        $gameUpdate = [];

        if ($shouldAdvance) {
            $nextEvent = $this->findNextEvent($currentEvent, $game->story_id);

            if ($nextEvent) {
                $gameUpdate['current_event_id'] = $nextEvent->id;

                if ($nextEvent->session_number !== null
                    && $nextEvent->session_number !== $currentEvent->session_number) {
                    $nextEvent = $this->applySessionTransitionCut($nextEvent, $game);
                    $gameUpdate['current_event_id'] = $nextEvent->id;
                    $gameUpdate['current_session_number'] = $nextEvent->session_number;
                }
            }
        }

        $resolvedEventId = $shouldAdvance && $nextEvent !== null
            ? $nextEvent->id
            : $currentEvent->id;

        $newWorldState = $this->applyStateDelta(
            worldState: $game->world_state ?? [],
            stateDelta: $aiResult['state_delta'] ?? [],
        );

        $effectiveOption = $deterministicMatch['option'] ?? ($aiResult['mapped_option'] ?: null);
        $effectiveChoiceId = $deterministicMatch['choice_id'] ?? ($aiResult['mapped_choice_id'] ?: null);

        $branchingChoicesTaken = $this->appendBranchingChoice(
            existing: $game->branching_choices_taken ?? [],
            sessionAdaptation: $sessionAdaptation,
            currentEvent: $currentEvent,
            optionLetter: $effectiveOption,
            choiceId: $effectiveChoiceId,
            playerInput: $isContinue ? 'Continue forward' : $prompt,
            inputClassification: $aiResult['input_classification'] ?? 'freeform',
            shouldAdvance: $shouldAdvance,
        );

        $trackedDimensions = $this->mergeTrackedDimensions(
            existing: $game->tracked_dimensions ?? [],
            updates: $aiResult['state_delta']['tracked_path_update'] ?? [],
        );

        $branchResolutionLog = $this->appendBranchResolutionLog(
            existing: $game->branch_resolution_log ?? [],
            sessionNumber: $game->current_session_number,
            eventBefore: $currentEvent->id,
            eventAfter: $resolvedEventId,
            classification: $aiResult['input_classification'] ?? 'freeform',
            optionLetter: $effectiveOption,
            choiceId: $effectiveChoiceId,
            advanced: $shouldAdvance,
        );

        $beatType = $this->resolveCurrentBeatType(
            sessionAdaptation: $sessionAdaptation,
            shouldAdvance: $shouldAdvance,
            currentBeatType: $game->current_beat_type,
        );

        $gameUpdate['world_state'] = $newWorldState;
        $gameUpdate['branching_choices_taken'] = $branchingChoicesTaken;
        $gameUpdate['tracked_dimensions'] = $trackedDimensions;
        $gameUpdate['branch_resolution_log'] = $branchResolutionLog;

        if ($beatType !== null) {
            $gameUpdate['current_beat_type'] = $beatType;
        }

        if (! empty($gameUpdate)) {
            $game->update($gameUpdate);
        }

        $game->prompts()->create([
            'event_id' => $resolvedEventId,
            'response' => $aiResult['response'],
            'choices' => $aiResult['choices'],
        ]);

        $game->refresh();

        Log::channel('narration')->info('narration.turn', [
            'game_id' => $game->id,
            'event_id_before' => $currentEvent->id,
            'event_id_after' => $game->current_event_id,
            'session_number_before' => $currentEvent->session_number,
            'session_number_after' => $game->current_session_number,
            'turn_count' => $turnCount,
            'is_first_turn_in_event' => $turnCount === 0,
            'advance_event_returned' => $aiResult['advance_event'],
            'force_advanced' => ! ($aiResult['advance_event']) && $turnCount >= 5,
            'is_continue' => $isContinue,
            'input_classification' => $aiResult['input_classification'] ?? null,
            'mapped_choice_id' => $effectiveChoiceId,
            'mapped_option' => $effectiveOption,
            'deterministic_match' => $deterministicMatch !== null,
            'state_delta_summary' => $this->summarizeStateDelta($aiResult['state_delta'] ?? []),
            'world_state_object_count' => count($newWorldState['objects'] ?? []),
            'world_state_condition_count' => count($newWorldState['conditions'] ?? []),
            'player_input_first_120' => mb_substr((string) $prompt, 0, 120),
            'narrator_response_first_120' => mb_substr(strip_tags((string) $aiResult['response']), 0, 120),
            'choices_returned' => $aiResult['choices'],
            'system_prompt_hash' => hash('sha256', $systemPrompt),
            'logged_at' => now()->toIso8601String(),
        ]);

        return back();
    }

    private function applySessionTransitionCut(Event $nextEvent, Game $game): Event
    {
        $nextSessionAdaptation = SessionAdaptation::query()
            ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $game->story_id))
            ->where('session_number', $nextEvent->session_number)
            ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
            ->first();

        $startEventId = $nextSessionAdaptation?->entry_point_diagnosis['start_event_id'] ?? null;

        if ($startEventId === null) {
            return $nextEvent;
        }

        $cutAdjusted = Event::find($startEventId);

        if ($cutAdjusted
            && $cutAdjusted->session_number === $nextEvent->session_number
            && $cutAdjusted->chapter->story_id === $game->story_id) {
            return $cutAdjusted;
        }

        return $nextEvent;
    }

    private function findNextEvent(Event $currentEvent, int|string $storyId): ?Event
    {
        $nextEvent = Event::query()
            ->where('chapter_id', $currentEvent->chapter_id)
            ->where('position', '>', $currentEvent->position)
            ->orderBy('position')
            ->first();

        if (! $nextEvent) {
            $nextChapter = Chapter::query()
                ->where('story_id', $storyId)
                ->where('position', '>', $currentEvent->chapter->position)
                ->orderBy('position')
                ->first();

            $nextEvent = $nextChapter?->events()->orderBy('position')->first();
        }

        return $nextEvent;
    }

    /**
     * Render the full system prompt at runtime with story data + event context.
     *
     * @param  array<string, mixed>  $worldState
     * @param  array{option: string, choice_id: ?string, text: string}|null  $deterministicMatch
     */
    private function renderSystemPrompt(
        \App\Models\Story $story,
        Event $currentEvent,
        int $turnCount = 0,
        ?SessionAdaptation $sessionAdaptation = null,
        array $worldState = [],
        ?array $deterministicMatch = null,
    ): string {
        $storyData = $story->system_prompt ?? [];

        $isSessionStart = false;

        if ($sessionAdaptation?->entry_point_diagnosis) {
            $isSessionStart = $currentEvent->id === ($sessionAdaptation->entry_point_diagnosis['start_event_id'] ?? null)
                && $turnCount === 0;
        }

        return view('ai.agents.narration.system-prompt', [
            'characterName' => $storyData['character_name'] ?? null,
            'worldRules' => $storyData['world_rules'] ?? [],
            'toneAndStyle' => $storyData['tone_and_style'] ?? null,
            'previousEvents' => $this->getPreviousEvents($currentEvent, 3),
            'currentEvent' => [
                'position' => $currentEvent->position,
                'title' => $currentEvent->title,
                'content' => $currentEvent->content,
                'objectives' => $currentEvent->objectives,
                'attributes' => $currentEvent->attributes,
            ],
            'nextEvents' => $this->getNextEvents($currentEvent, 2),
            'turnCount' => $turnCount,
            'isFirstTurnInEvent' => $turnCount === 0,
            'sessionAdaptation' => $sessionAdaptation,
            'isSessionStart' => $isSessionStart,
            'worldState' => $worldState,
            'deterministicMatch' => $deterministicMatch,
        ])->render();
    }

    /**
     * Resolve the SessionAdaptation for the current event with cold-open fallback to Session 1.
     */
    private function resolveSessionAdaptation(Game $game, Event $currentEvent): ?SessionAdaptation
    {
        $sessionNumber = $currentEvent->session_number ?? 1;

        return SessionAdaptation::query()
            ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $game->story_id))
            ->where('session_number', $sessionNumber)
            ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
            ->first();
    }

    /**
     * Detect whether the player's freeform input deterministically matches an authored
     * choice (A/B/C) from the active session_choice_design. Uses simple normalization
     * + token-overlap heuristics; only emits a match when confidence is high.
     *
     * @return array{option: string, choice_id: ?string, text: string}|null
     */
    private function matchAuthoredChoice(string $playerInput, ?SessionAdaptation $sessionAdaptation): ?array
    {
        $playerInput = trim($playerInput);

        if ($playerInput === '' || $sessionAdaptation === null) {
            return null;
        }

        $choices = $sessionAdaptation->session_choice_design['choices']
            ?? $sessionAdaptation->session_choice_design
            ?? null;

        if (! is_array($choices) || empty($choices)) {
            return null;
        }

        $normalizedInput = $this->normalizeForMatch($playerInput);
        $inputTokens = array_filter(explode(' ', $normalizedInput), fn ($t) => mb_strlen($t) > 2);

        if (empty($inputTokens)) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($choices as $choice) {
            $option = (string) ($choice['option'] ?? $choice['letter'] ?? '');
            $text = (string) ($choice['text'] ?? $choice['title'] ?? $choice['summary'] ?? '');
            $choiceId = $choice['id'] ?? $choice['choice_id'] ?? null;

            if ($option === '' || $text === '') {
                continue;
            }

            $normalizedChoice = $this->normalizeForMatch($text);
            $choiceTokens = array_filter(explode(' ', $normalizedChoice), fn ($t) => mb_strlen($t) > 2);

            if (empty($choiceTokens)) {
                continue;
            }

            $overlap = count(array_intersect($inputTokens, $choiceTokens));
            $score = $overlap / max(count($inputTokens), count($choiceTokens));

            if (Str::contains($normalizedInput, $normalizedChoice) || Str::contains($normalizedChoice, $normalizedInput)) {
                $score = max($score, 0.85);
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'option' => $option,
                    'choice_id' => $choiceId !== null ? (string) $choiceId : null,
                    'text' => $text,
                ];
            }
        }

        return $bestScore >= 0.6 ? $best : null;
    }

    private function normalizeForMatch(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Apply structured state_delta cumulatively to existing world_state.
     *
     * @param  array<string, mixed>  $worldState
     * @param  array<string, mixed>  $stateDelta
     * @return array<string, mixed>
     */
    private function applyStateDelta(array $worldState, array $stateDelta): array
    {
        $worldState['objects'] ??= [];
        $worldState['conditions'] ??= [];
        $worldState['knowledge'] ??= [];
        $worldState['relationships'] ??= [];
        $worldState['flags'] ??= [];
        $worldState['location'] ??= null;
        $worldState['updated_at'] = now()->toIso8601String();

        foreach (($stateDelta['objects_acquired'] ?? []) as $obj) {
            $name = (string) ($obj['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $worldState['objects'][$name] = [
                'qualifier' => (string) ($obj['qualifier'] ?? ''),
                'contains' => array_values(array_filter((array) ($obj['contains'] ?? []), 'is_string')),
            ];
        }

        foreach (($stateDelta['objects_lost'] ?? []) as $name) {
            if (is_string($name)) {
                unset($worldState['objects'][$name]);
            }
        }

        foreach (($stateDelta['objects_transformed'] ?? []) as $obj) {
            $name = (string) ($obj['name'] ?? '');
            $newQualifier = (string) ($obj['new_qualifier'] ?? '');
            if ($name !== '' && isset($worldState['objects'][$name])) {
                $worldState['objects'][$name]['qualifier'] = $newQualifier;
            }
        }

        foreach (($stateDelta['conditions_added'] ?? []) as $cond) {
            $name = (string) ($cond['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $worldState['conditions'][$name] = (string) ($cond['note'] ?? '');
        }

        foreach (($stateDelta['conditions_removed'] ?? []) as $name) {
            if (is_string($name)) {
                unset($worldState['conditions'][$name]);
            }
        }

        $location = (string) ($stateDelta['location_changed'] ?? '');
        if ($location !== '') {
            $worldState['location'] = $location;
        }

        foreach (($stateDelta['knowledge_gained'] ?? []) as $fact) {
            if (is_string($fact) && $fact !== '' && ! in_array($fact, $worldState['knowledge'], true)) {
                $worldState['knowledge'][] = $fact;
            }
        }

        foreach (($stateDelta['relationship_changes'] ?? []) as $rel) {
            $character = (string) ($rel['character'] ?? '');
            $shift = (string) ($rel['shift'] ?? '');
            if ($character !== '' && $shift !== '') {
                $worldState['relationships'][$character] = $shift;
            }
        }

        foreach (($stateDelta['flags_set'] ?? []) as $flag) {
            if (is_string($flag) && $flag !== '' && ! in_array($flag, $worldState['flags'], true)) {
                $worldState['flags'][] = $flag;
            }
        }

        return $worldState;
    }

    /**
     * Append a branching choice entry once per (session, event).
     * Existing entries for the same key are updated (not duplicated).
     *
     * @param  array<int, array<string, mixed>>  $existing
     * @return array<int, array<string, mixed>>
     */
    private function appendBranchingChoice(
        array $existing,
        ?SessionAdaptation $sessionAdaptation,
        Event $currentEvent,
        ?string $optionLetter,
        ?string $choiceId,
        string $playerInput,
        string $inputClassification,
        bool $shouldAdvance,
    ): array {
        if ($optionLetter === null || $optionLetter === '') {
            return $existing;
        }

        $sessionNumber = $sessionAdaptation?->session_number ?? $currentEvent->session_number;

        $entry = [
            'session_number' => $sessionNumber,
            'event_id' => $currentEvent->id,
            'option' => $optionLetter,
            'choice_id' => $choiceId,
            'player_input_first_120' => mb_substr($playerInput, 0, 120),
            'classification' => $inputClassification,
            'advanced' => $shouldAdvance,
            'taken_at' => now()->toIso8601String(),
        ];

        foreach ($existing as $i => $row) {
            if (($row['session_number'] ?? null) === $sessionNumber
                && ($row['event_id'] ?? null) === $currentEvent->id) {
                $existing[$i] = $entry;

                return $existing;
            }
        }

        $existing[] = $entry;

        return $existing;
    }

    /**
     * Merge tracked-dimension updates from this turn into the cumulative tracker.
     *
     * @param  array<string, array<int, string>>  $existing
     * @param  array<int, array{dimension: string, path: string}>  $updates
     * @return array<string, array<int, string>>
     */
    private function mergeTrackedDimensions(array $existing, array $updates): array
    {
        foreach ($updates as $update) {
            $dimension = (string) ($update['dimension'] ?? '');
            $path = (string) ($update['path'] ?? '');

            if ($dimension === '' || $path === '') {
                continue;
            }

            $existing[$dimension] ??= [];
            $existing[$dimension][] = $path;
        }

        return $existing;
    }

    /**
     * Append a per-turn entry to the branch_resolution_log (lightweight audit trail).
     *
     * @param  array<int, array<string, mixed>>  $existing
     * @return array<int, array<string, mixed>>
     */
    private function appendBranchResolutionLog(
        array $existing,
        ?int $sessionNumber,
        int|string|null $eventBefore,
        int|string|null $eventAfter,
        string $classification,
        ?string $optionLetter,
        ?string $choiceId,
        bool $advanced,
    ): array {
        $existing[] = [
            'at' => now()->toIso8601String(),
            'session_number' => $sessionNumber,
            'event_before' => $eventBefore,
            'event_after' => $eventAfter,
            'classification' => $classification,
            'option' => $optionLetter,
            'choice_id' => $choiceId,
            'advanced' => $advanced,
        ];

        if (count($existing) > 200) {
            $existing = array_slice($existing, -200);
        }

        return $existing;
    }

    /**
     * Resolve the next current_beat_type using a simple proportional mapping over
     * session_architecture beats. Falls back to the existing value when no beat map
     * is available.
     */
    private function resolveCurrentBeatType(
        ?SessionAdaptation $sessionAdaptation,
        bool $shouldAdvance,
        ?string $currentBeatType,
    ): ?string {
        $beats = $sessionAdaptation?->session_architecture['beats']
            ?? $sessionAdaptation?->session_architecture['beat_map']
            ?? null;

        if (! is_array($beats) || empty($beats)) {
            return $currentBeatType;
        }

        if (! $shouldAdvance) {
            return $currentBeatType;
        }

        $index = 0;

        if ($currentBeatType !== null) {
            foreach ($beats as $i => $beat) {
                $type = $beat['type'] ?? $beat['beat_type'] ?? $beat['name'] ?? null;
                if ($type === $currentBeatType) {
                    $index = $i + 1;
                    break;
                }
            }
        }

        $index = min($index, count($beats) - 1);
        $next = $beats[$index];

        return (string) ($next['type'] ?? $next['beat_type'] ?? $next['name'] ?? $currentBeatType);
    }

    /**
     * Compact summary of state_delta for log rows (avoids dumping full payloads).
     *
     * @param  array<string, mixed>  $stateDelta
     * @return array<string, int|string>
     */
    private function summarizeStateDelta(array $stateDelta): array
    {
        return [
            'objects_acquired' => count($stateDelta['objects_acquired'] ?? []),
            'objects_lost' => count($stateDelta['objects_lost'] ?? []),
            'objects_transformed' => count($stateDelta['objects_transformed'] ?? []),
            'conditions_added' => count($stateDelta['conditions_added'] ?? []),
            'conditions_removed' => count($stateDelta['conditions_removed'] ?? []),
            'location_changed' => (string) ($stateDelta['location_changed'] ?? ''),
            'knowledge_gained' => count($stateDelta['knowledge_gained'] ?? []),
            'relationship_changes' => count($stateDelta['relationship_changes'] ?? []),
            'tracked_path_update' => count($stateDelta['tracked_path_update'] ?? []),
            'flags_set' => count($stateDelta['flags_set'] ?? []),
        ];
    }

    /**
     * Build conversation history from previous prompts.
     *
     * @return array<int, array{role: string, text: string}>
     */
    private function buildConversationHistory(Game $game): array
    {
        $history = [];

        $prompts = $game->prompts()
            ->latest()
            ->limit(6)
            ->get()
            ->reverse();

        foreach ($prompts as $p) {
            if ($p->response) {
                $history[] = ['role' => 'narrator', 'text' => strip_tags($p->response)];
            }
            if ($p->prompt && $p->prompt !== '__continue__') {
                $history[] = ['role' => 'player', 'text' => $p->prompt];
            } elseif ($p->prompt === '__continue__') {
                $history[] = ['role' => 'player', 'text' => 'Continue forward'];
            }
        }

        return $history;
    }

    /**
     * Get previous events for continuity context.
     * Looks across chapter boundaries so the AI has continuity at chapter starts.
     *
     * @return array<int, array{position: int, title: string, objectives: string|null}>
     */
    private function getPreviousEvents(Event $currentEvent, int $take = 3): array
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
            ->map(fn (Event $event): array => [
                'position' => $event->position,
                'title' => $event->title,
                'objectives' => $event->objectives,
            ])
            ->values()
            ->all();
    }

    /**
     * Get next events for pacing awareness (titles only — no spoilers).
     * Looks across chapter boundaries so the AI always knows more story lies ahead.
     *
     * @return array<int, array{position: int, title: string}>
     */
    private function getNextEvents(Event $currentEvent, int $take = 2): array
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

        return $events->map(fn (Event $event): array => [
            'position' => $event->position,
            'title' => $event->title,
        ])->all();
    }

    /**
     * Generate AI narration and choices for the current event.
     *
     * @param  array<int, array{role: string, text: string}>  $conversationHistory
     * @param  array{option: string, choice_id: ?string, text: string}|null  $deterministicMatch
     * @return array{
     *     response: string,
     *     choices: string[],
     *     advance_event: bool,
     *     input_classification: string,
     *     mapped_choice_id: string,
     *     mapped_option: string,
     *     state_delta: array<string, mixed>,
     * }
     */
    private function generateNarration(
        string $systemPrompt,
        array $conversationHistory,
        string $playerAction,
        ?array $deterministicMatch = null,
    ): array {
        try {
            /** @var StructuredAgentResponse $response */
            $response = NarrationAgent::make(customInstructions: $systemPrompt)
                ->prompt(
                    view('ai.agents.narration.prompt', [
                        'conversationHistory' => $conversationHistory,
                        'playerAction' => $playerAction,
                        'deterministicMatch' => $deterministicMatch,
                    ])->render()
                );

            return [
                'response' => $response['response'] ?? '',
                'choices' => $response['choices'] ?? ['Continue forward', 'Investigate your surroundings', 'Take a moment to reflect'],
                'advance_event' => (bool) ($response['advance_event'] ?? false),
                'input_classification' => (string) ($response['input_classification'] ?? 'freeform'),
                'mapped_choice_id' => (string) ($response['mapped_choice_id'] ?? ''),
                'mapped_option' => (string) ($response['mapped_option'] ?? ''),
                'state_delta' => is_array($response['state_delta'] ?? null) ? $response['state_delta'] : [],
            ];
        } catch (Throwable) {
            return [
                'response' => '<p>The scene unfolds before you...</p>',
                'choices' => ['Continue forward', 'Investigate your surroundings', 'Take a moment to reflect'],
                'advance_event' => true,
                'input_classification' => 'freeform',
                'mapped_choice_id' => '',
                'mapped_option' => '',
                'state_delta' => [],
            ];
        }
    }
}
