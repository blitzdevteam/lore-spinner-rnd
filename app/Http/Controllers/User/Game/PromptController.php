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

        // Continue-on-authored-branch fallback: when the player hits continue and the
        // previous turn's choices were a deterministic match for an authored branching
        // set (S1_C1/S1_C2/S1_C3 etc.), treat the continue as taking the passive path
        // (option C — the opportunistic/silent path in every authored S1 design).
        // Without this, an authored branch served correctly is silently bypassed and
        // the dimension is never recorded, breaking all cross-session payoffs.
        if ($isContinue && $deterministicMatch === null && $sessionAdaptation !== null) {
            $lastPrompt = $game->prompts()->latest()->first();
            $lastChoices = is_array($lastPrompt?->choices) ? $lastPrompt->choices : [];

            foreach ($lastChoices as $choiceText) {
                $choiceText = (string) $choiceText;
                if ($choiceText === '') {
                    continue;
                }

                $match = $this->matchAuthoredChoice($choiceText, $sessionAdaptation);

                if ($match !== null && $match['choice_id'] !== null) {
                    $passiveText = (string) ($lastChoices[2] ?? $choiceText);

                    $deterministicMatch = [
                        'option' => 'C',
                        'choice_id' => $match['choice_id'],
                        'text' => $passiveText,
                    ];

                    Log::channel('narration')->info('narration.continue_authored_default', [
                        'game_id' => $game->id,
                        'event_id' => $currentEvent->id,
                        'choice_id' => $match['choice_id'],
                        'defaulted_option' => 'C',
                    ]);

                    break;
                }
            }
        }

        $systemPrompt = $this->renderSystemPrompt(
            game: $game,
            story: $game->story,
            currentEvent: $currentEvent,
            turnCount: $turnCount,
            sessionAdaptation: $sessionAdaptation,
            worldState: $game->world_state ?? [],
            deterministicMatch: $deterministicMatch,
            isContinue: $isContinue,
        );

        try {
            $aiResult = $this->generateNarration(
                systemPrompt: $systemPrompt,
                conversationHistory: $conversationHistory,
                playerAction: $isContinue ? 'Continue forward' : $prompt,
                deterministicMatch: $deterministicMatch,
            );
        } catch (Throwable) {
            // The narration call already logged the failure (narration.llm_failed). No prompt
            // row is created here — the player's input is preserved in the previous prompt's
            // ->update() above, so a retry submits cleanly. We surface a banner instead of a
            // fake "scene unfolds" stub so playtests see the failure honestly.
            return back()->with('error', 'Narration hiccuped — your input was preserved. Please retry.');
        }

        $shouldAdvance = $aiResult['advance_event'];

        if (! $shouldAdvance && $turnCount >= 5) {
            $shouldAdvance = true;
        }

        $nextEvent = null;
        $gameUpdate = [];

        if ($shouldAdvance) {
            $nextEvent = $this->findNextEvent($currentEvent, $game->story_id);

            if ($nextEvent) {
                if ($nextEvent->session_number !== null
                    && $nextEvent->session_number !== $currentEvent->session_number) {
                    $nextEvent = $this->applySessionTransitionCut($nextEvent, $game);
                }

                // Games columns must always mirror the event they point at, so the
                // narration log and any downstream consumer (Filament, trace, future
                // UI) see a single source of truth. Writing unconditionally (even
                // when the new event has a nullable session_number) prevents the
                // denormalized column from drifting silently.
                $gameUpdate['current_event_id'] = $nextEvent->id;
                $gameUpdate['current_session_number'] = $nextEvent->session_number;
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

        // Record the event that was *narrated* this turn, not the event being advanced to.
        // When advance_event=true fires, $currentEvent is the scene the narrator just rendered;
        // $nextEvent is where the next turn will open. Writing $nextEvent here would make the
        // next turn see turn_count>=1 for the new event and suppress isFirstTurnInEvent, which
        // causes the narrator to skip the new scene's opening entirely.
        $game->prompts()->create([
            'event_id' => $currentEvent->id,
            'response' => $aiResult['response'],
            'choices' => $aiResult['choices'],
        ]);

        $game->refresh();
        $game->load('currentEvent');

        // session_number_before/after always reference the event table (authoritative).
        // game_current_session_number_after is logged separately so any denormalization
        // drift between events.session_number and games.current_session_number surfaces.
        $sessionNumberAfter = $game->currentEvent?->session_number;

        Log::channel('narration')->info('narration.turn', [
            'game_id' => $game->id,
            'event_id_before' => $currentEvent->id,
            'event_id_after' => $game->current_event_id,
            'session_number_before' => $currentEvent->session_number,
            'session_number_after' => $sessionNumberAfter,
            'game_current_session_number_after' => $game->current_session_number,
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
        Game $game,
        \App\Models\Story $story,
        Event $currentEvent,
        int $turnCount = 0,
        ?SessionAdaptation $sessionAdaptation = null,
        array $worldState = [],
        ?array $deterministicMatch = null,
        bool $isContinue = false,
    ): string {
        $storyData = $story->system_prompt ?? [];

        $isSessionStart = false;

        if ($sessionAdaptation?->entry_point_diagnosis) {
            $isSessionStart = $currentEvent->id === ($sessionAdaptation->entry_point_diagnosis['start_event_id'] ?? null)
                && $turnCount === 0;
        }

        // Exit-point detection: mirror the entry-point logic using the explicit authored
        // session_close_trigger_event_id produced by Phase 7. A legacy fallback (last events
        // of the session's declared event_range) is preserved for adaptations produced before
        // the field existed; newly-adapted stories will never hit that branch.
        $isSessionEnd = false;
        $sessionCloseDesign = null;

        if ($sessionAdaptation?->session_close_design && $turnCount === 0) {
            $closeDesign = $sessionAdaptation->session_close_design;
            $triggerEventId = $closeDesign['session_close_trigger_event_id'] ?? null;

            if ($triggerEventId !== null) {
                $isSessionEnd = $currentEvent->id === (int) $triggerEventId;
            } else {
                $sessionEventRange = $sessionAdaptation->entry_point_diagnosis['session_event_range'] ?? null;
                if (is_string($sessionEventRange) && str_contains($sessionEventRange, '-')) {
                    [, $rangeEnd] = array_map('intval', explode('-', $sessionEventRange, 2));
                    $isSessionEnd = $currentEvent->position >= ($rangeEnd - 4);
                }
            }

            if ($isSessionEnd) {
                $sessionCloseDesign = $closeDesign;
            }
        }

        return view('ai.agents.narration.system-prompt', [
            'characterName' => $storyData['character_name'] ?? null,
            'worldRules' => $storyData['world_rules'] ?? [],
            'toneAndStyle' => $storyData['tone_and_style'] ?? null,
            'previousEvents' => $this->getPreviousEvents($currentEvent, 3),
            'currentEvent' => [
                'position'        => $currentEvent->position,
                'title'           => $currentEvent->title,
                'content'         => $currentEvent->content,
                'objectives'      => $currentEvent->objectives,
                'attributes'      => $currentEvent->attributes,
                'requires_choice' => $currentEvent->requires_choice ?? true,
            ],
            'nextEvents' => $this->getNextEvents($currentEvent, 3),
            'turnCount' => $turnCount,
            'isFirstTurnInEvent' => $turnCount === 0,
            'sessionAdaptation' => $sessionAdaptation,
            'isSessionStart' => $isSessionStart,
            'isSessionEnd' => $isSessionEnd,
            'sessionCloseDesign' => $sessionCloseDesign,
            'worldState' => $worldState,
            'deterministicMatch' => $deterministicMatch,
            'playerChoiceEchoes' => $this->resolvePlayerChoiceEchoes($game, $sessionAdaptation),
            'isContinue' => $isContinue,
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

        $candidates = $this->extractAuthoredOptions(
            (array) ($sessionAdaptation->session_choice_design ?? [])
        );

        if ($candidates === []) {
            return null;
        }

        $normalizedInput = $this->normalizeForMatch($playerInput);
        $inputTokens = array_filter(explode(' ', $normalizedInput), fn ($t) => mb_strlen($t) > 2);

        if (empty($inputTokens)) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $option = $candidate['option'];
            $text = $candidate['text'];
            $choiceId = $candidate['choice_id'];

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
                    'choice_id' => $choiceId,
                    'text' => $text,
                ];
            }
        }

        return $bestScore >= 0.6 ? $best : null;
    }

    /**
     * Flatten the nested session_choice_design shape into a list of authored option candidates.
     *
     * Real shape produced by the adaptation pipeline (see database/exports/adapptation-third-try.json):
     *   {
     *     branching_choice_1: { choice_id, option_a: {text, ...}, option_b: {text}, option_c: {text}, ... },
     *     branching_choice_2: { ... },
     *     branching_choice_3: { ... },
     *     expressive_choices: [
     *       { source_moment, option_a: {text}, option_b: {text}, option_c: {text}, ... },
     *       ...
     *     ],
     *   }
     *
     * Each branching slot emits 3 candidates (A/B/C with its choice_id). Each expressive item emits 3
     * candidates with choice_id = null (these are tonal/expressive, not pre-authored branch slots).
     *
     * @param  array<string, mixed>  $design
     * @return list<array{option: 'A'|'B'|'C', choice_id: ?string, text: string}>
     */
    private function extractAuthoredOptions(array $design): array
    {
        $candidates = [];

        foreach (['branching_choice_1', 'branching_choice_2', 'branching_choice_3'] as $slot) {
            $entry = $design[$slot] ?? null;
            if (! is_array($entry)) {
                continue;
            }

            $choiceId = isset($entry['choice_id']) ? (string) $entry['choice_id'] : null;

            foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c'] as $option => $key) {
                $optionEntry = $entry[$key] ?? null;
                $text = is_array($optionEntry)
                    ? (string) ($optionEntry['text'] ?? '')
                    : (string) ($optionEntry ?? '');

                if ($text === '') {
                    continue;
                }

                $candidates[] = [
                    'option' => $option,
                    'choice_id' => $choiceId,
                    'text' => $text,
                ];
            }
        }

        $expressive = $design['expressive_choices'] ?? null;
        if (is_array($expressive)) {
            foreach ($expressive as $item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c'] as $option => $key) {
                    $optionEntry = $item[$key] ?? null;
                    $text = is_array($optionEntry)
                        ? (string) ($optionEntry['text'] ?? '')
                        : (string) ($optionEntry ?? '');

                    if ($text === '') {
                        continue;
                    }

                    $candidates[] = [
                        'option' => $option,
                        'choice_id' => null,
                        'text' => $text,
                    ];
                }
            }
        }

        return $candidates;
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

        // Trim knowledge to the 40 most recent entries. Earlier facts from completed events
        // are already covered by previousEvents[].objectives passed in the system prompt,
        // so retaining them here duplicates context and inflates token cost.
        if (count($worldState['knowledge']) > 40) {
            $worldState['knowledge'] = array_slice($worldState['knowledge'], -40);
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

        // Flags are anti-loop markers only. Keep the 25 most recent — older ones come from
        // long-past events where the event has already advanced, so the loop they guarded
        // against can no longer occur. This caps the per-turn token cost of the flags block.
        if (count($worldState['flags']) > 25) {
            $worldState['flags'] = array_slice($worldState['flags'], -25);
        }

        // Normalise relationship keys: deduplicate case-insensitive variants that refer to
        // the same character (e.g. "the Mouse" vs "Mouse"). Keep the last-written value.
        if (! empty($worldState['relationships'])) {
            $normalised = [];
            foreach ($worldState['relationships'] as $character => $shift) {
                $key = mb_strtolower(ltrim((string) $character, 'tT he '));
                $canonical = $character;
                // If a normalised form already exists, prefer the shorter/cleaner key
                foreach (array_keys($normalised) as $existing) {
                    if (mb_strtolower(ltrim($existing, 'tT he ')) === $key) {
                        $canonical = strlen($existing) <= strlen((string) $character) ? $existing : $character;
                        unset($normalised[$existing]);
                        break;
                    }
                }
                $normalised[$canonical] = $shift;
            }
            $worldState['relationships'] = $normalised;
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
     * Build a small list of in-session echo strings derived from the player's actual
     * branching choices and the adaptation consequence map. Only emits entries where
     * there is a concrete current_session_echo or immediate_effect written by the
     * adaptation layer — never fabricates. Returns [] when nothing meaningful is found
     * so the prompt block is omitted entirely.
     *
     * @return list<string>
     */
    private function resolvePlayerChoiceEchoes(Game $game, ?SessionAdaptation $sessionAdaptation): array
    {
        if ($sessionAdaptation === null) {
            return [];
        }

        $branchingChoices = $game->branching_choices_taken ?? [];
        if ($branchingChoices === []) {
            return [];
        }

        $cmap = $sessionAdaptation->choice_consequence_map ?? null;
        if (! is_array($cmap) || $cmap === []) {
            return [];
        }

        $echoes = [];

        foreach ($branchingChoices as $taken) {
            $choiceId = (string) ($taken['choice_id'] ?? '');
            $option = strtolower((string) ($taken['option'] ?? ''));

            if ($choiceId === '' || $option === '') {
                continue;
            }

            // Match the taken choice to its consequence map entry by choice_id suffix
            // e.g. S1_C1 -> consequence_map_choice_1, S1_C2 -> consequence_map_choice_2
            $mapKey = null;
            foreach (array_keys($cmap) as $key) {
                // Extract the numeric suffix from both sides and compare
                preg_match('/(\d+)$/', $choiceId, $choiceNum);
                preg_match('/(\d+)$/', (string) $key, $mapNum);
                if (isset($choiceNum[1], $mapNum[1]) && $choiceNum[1] === $mapNum[1]) {
                    $mapKey = $key;
                    break;
                }
            }

            if ($mapKey === null || ! isset($cmap[$mapKey])) {
                continue;
            }

            $pathKey = 'path_' . $option;
            $pathData = $cmap[$mapKey][$pathKey] ?? null;

            if (! is_array($pathData)) {
                continue;
            }

            // Prefer current_session_echo as it's written for in-session flavour;
            // fall back to immediate_effect if echo is absent.
            $echo = (string) ($pathData['current_session_echo'] ?? $pathData['immediate_effect'] ?? '');

            if ($echo === '' || str_starts_with($echo, 'N/A')) {
                continue;
            }

            $echoes[] = $echo;
        }

        return $echoes;
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
    private function getNextEvents(Event $currentEvent, int $take = 3): array
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

            $stateDelta = is_array($response['state_delta'] ?? null) ? $response['state_delta'] : [];

            Log::channel('narration')->info('narration.llm_success', [
                'response_bytes' => strlen((string) ($response['response'] ?? '')),
                'state_delta_keys_present' => array_keys($stateDelta),
                'input_classification' => $response['input_classification'] ?? null,
                'mapped_option' => $response['mapped_option'] ?? null,
                'mapped_choice_id' => $response['mapped_choice_id'] ?? null,
                'system_prompt_bytes' => strlen($systemPrompt),
                'history_turns' => count($conversationHistory),
            ]);

            return [
                'response' => $response['response'] ?? '',
                'choices' => $response['choices'] ?? [],
                'advance_event' => (bool) ($response['advance_event'] ?? false),
                'input_classification' => (string) ($response['input_classification'] ?? ''),
                'mapped_choice_id' => (string) ($response['mapped_choice_id'] ?? ''),
                'mapped_option' => (string) ($response['mapped_option'] ?? ''),
                'state_delta' => $stateDelta,
            ];
        } catch (Throwable $e) {
            Log::channel('narration')->error('narration.llm_failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'system_prompt_bytes' => strlen($systemPrompt),
                'history_turns' => count($conversationHistory),
                'deterministic_match' => $deterministicMatch !== null,
                'player_action_first_120' => mb_substr($playerAction, 0, 120),
            ]);

            throw $e;
        }
    }
}
