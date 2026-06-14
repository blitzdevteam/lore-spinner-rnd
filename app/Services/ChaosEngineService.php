<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\Chaos\ChaosNarrationSchema;
use App\Ai\Agents\Chaos\NativeObjectSchema;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\Prism;
use Throwable;

/**
 * Chaos Engine — shared AI narration logic for both the experimental
 * /chaos-mode page and the production /user/games/{id} flow.
 *
 * This service owns every piece of the Chaos V2 narration pipeline:
 *   - Session context loading (runtime_narrator_prompt + events)
 *   - System prompt assembly (injection points)
 *   - Prism agent call (ChaosNarrationSchema, multi-provider)
 *   - World state + alignment merging
 *   - Memory accumulation
 *
 * Both ChaosModeController (experimental) and GameController/PromptController
 * (production) inject this service. There is no duplicated engine code.
 */
final class ChaosEngineService
{
    private const COMPLETE_LIST_STATE_FIELDS = ['conditions', 'items', 'unresolved_promises'];

    private const KEYED_DELTA_STATE_FIELDS = ['object_states', 'relationship_updates', 'world_flags'];

    private const APPEND_ONLY_STATE_FIELDS = ['knowledge', 'notes', 'player_style'];

    public const MODEL_CONFIG = [
        'gpt-5.5'           => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'gpt-5.4'           => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'gpt-5.4-mini'      => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'gpt-5.2'           => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'gpt-4.1'           => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'chat-latest'       => ['provider' => 'openai',    'temperature' => 0.9,  'reasoning_effort' => null],
        'claude-opus-4-8'   => ['provider' => 'anthropic', 'temperature' => 1.0,  'reasoning_effort' => null],
        'claude-opus-4-7'   => ['provider' => 'anthropic', 'temperature' => 1.0,  'reasoning_effort' => null],
        'claude-sonnet-4-6' => ['provider' => 'anthropic', 'temperature' => 0.9,  'reasoning_effort' => null],
        'claude-haiku-4-5'  => ['provider' => 'anthropic', 'temperature' => 0.95, 'reasoning_effort' => null],
    ];

    public const DEFAULT_MODEL = 'claude-haiku-4-5';

    /** Production /user/games narration — tighter than chaos-mode lab defaults. */
    public const GAME_TEMPERATURE = 0.4;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * @return array<int, string>
     */
    public function allowedModels(): array
    {
        return array_keys(self::MODEL_CONFIG);
    }

    public function defaultTemperatureFor(string $model): float
    {
        return (float) (self::MODEL_CONFIG[$model]['temperature'] ?? 1.0);
    }

    public function gameTemperatureFor(string $model): float
    {
        return self::GAME_TEMPERATURE;
    }

    /**
     * Load the cached runtime narrator prompt + per-session context needed to
     * assemble injection points and the opening scene.
     *
     * Returns null when runtime_narrator_prompt is null (story not V2-adapted).
     *
     * @return array{
     *     session_number: int,
     *     total_sessions: int,
     *     opening_handoff: string,
     *     opening_scene: string,
     *     runtime_prompt: string,
     *     alignment_labels: array<int, array<string, mixed>>,
     *     full_session_events: array<int, array{position:int, title:string, content:string, objectives:?string}>,
     * }|null
     */
    public function loadSessionContext(Story $story, int $sessionNumber, ?string $openingHandoff): ?array
    {
        /** @var StoryAdaptation|null $adaptation */
        $adaptation = $story->adaptation;

        /** @var SessionAdaptation|null $sessionAdaptation */
        $sessionAdaptation = $adaptation?->sessionAdaptations
            ?->firstWhere('session_number', $sessionNumber);

        if ($sessionAdaptation === null || $sessionAdaptation->runtime_narrator_prompt === null) {
            return null;
        }

        $storyMap   = (array) ($adaptation->story_session_map ?? []);
        $allocation = $this->findAllocationRow($storyMap, $sessionNumber);

        $events = Event::query()
            ->whereHas('chapter', fn ($q) => $q->where('story_id', $story->id))
            ->where('session_number', $sessionNumber)
            ->join('chapters', 'events.chapter_id', '=', 'chapters.id')
            ->orderBy('chapters.position')
            ->orderBy('events.position')
            ->get([
                'events.id',
                'events.chapter_id',
                'events.position',
                'events.title',
                'events.content',
                'events.objectives',
                'events.session_number',
                'chapters.position as chapter_position',
                'chapters.title as chapter_title',
            ])
            ->map(fn (Event $e) => [
                'id'               => (int) $e->id,
                'chapter_id'       => (int) $e->chapter_id,
                'chapter_position' => (int) $e->chapter_position,
                'chapter_title'    => (string) $e->chapter_title,
                'position'         => (int) $e->position,
                'session_number'   => (int) $e->session_number,
                'title'            => (string) $e->title,
                'content'          => (string) $e->content,
                'objectives'       => $e->objectives,
            ])
            ->all();

        $entry = (array) ($sessionAdaptation->entry_point_diagnosis ?? []);

        $openingHandoff = $this->normalizeOpeningScene($openingHandoff);

        if ($openingHandoff === '') {
            $arcRow         = $this->findArcProgressionRow($adaptation, $sessionNumber);
            $openingHandoff = $this->normalizeOpeningScene($arcRow['opens_with'] ?? '');
        }

        $coldOpen = $this->normalizeOpeningScene($entry['cold_open'] ?? '');

        $openingScene = trim($openingHandoff) !== ''
            ? $openingHandoff
            : $coldOpen;

        $totalSessions = (int) ($adaptation?->sessionAdaptations?->count() ?? 0);

        return [
            'session_number'      => $sessionNumber,
            'total_sessions'      => $totalSessions,
            'opening_handoff'     => $openingHandoff,
            'opening_scene'       => $openingScene,
            'runtime_prompt'      => (string) $sessionAdaptation->runtime_narrator_prompt,
            'alignment_labels'    => (array) ($storyMap['alignment_labels'] ?? []),
            'full_session_events' => $events,
        ];
    }

    /**
     * Inject the four runtime-only blocks into the cached runtime narrator
     * prompt using marker token replacement.
     *
     * @param  array<string, mixed>  $sessionContext
     * @param  array<string, mixed>  $worldState
     * @param  array{chaotic:int, lawful:int, neutral:int}  $alignmentScaffold
     */
    public function renderSystemPrompt(
        array $sessionContext,
        array $worldState,
        array $alignmentScaffold,
        ?string $symbolicMemory,
        ?string $currentScene,
        bool $isClimacticPrevious,
    ): string {
        $prompt = $sessionContext['runtime_prompt'];

        $symbolicBlock = trim((string) $symbolicMemory) !== ''
            ? trim((string) $symbolicMemory)
            : '(No symbolic memory yet. The protagonist is still becoming.)';

        $alignmentBlock = $this->renderAlignmentTilt(
            (array) ($sessionContext['alignment_labels'] ?? []),
            $alignmentScaffold,
        );

        $openingBlock = trim((string) $currentScene) !== ''
            ? trim((string) $currentScene)
            : '(This is a continuation turn. Resume from the conversation history; do not re-cold-open.)';

        $worldStateBlock = $this->renderTieredWorldState(
            worldState:    $worldState,
            isClimactic:   $isClimacticPrevious,
            sessionEvents: $sessionContext['full_session_events'] ?? [],
        );

        return strtr($prompt, [
            '[SYMBOLIC_MEMORY_INJECTION_POINT]'    => $symbolicBlock,
            '[ALIGNMENT_TILT_INJECTION_POINT]'     => $alignmentBlock,
            '[OPENING_SCENE_INJECTION_POINT]'      => $openingBlock,
            '[WORLD_STATE_TIERED_INJECTION_POINT]' => $worldStateBlock,
        ]);
    }

    /**
     * Call the Chaos narration agent via Prism. Retries once on failure.
     *
     * Pass $playerAction = null for the opening turn (session start).
     * Pass the string 'Continue the story forward.' for the continue button.
     *
     * @param  array<int, array{role:string, text:string}>  $conversationHistory
     * @return array{
     *     response:string,
     *     choices:array<int,string>,
     *     session_complete:bool,
     *     state_delta:array<string,mixed>,
     *     alignment_tally_delta: array{chaotic:int, lawful:int, neutral:int},
     *     is_climactic_choice:bool,
     *     defining_choice_id:string,
     *     defining_choice_line:string,
     *     symbolic_memory_update:string,
     *     session_memory_update:string,
     * }
     */
    public function callAgent(
        string $model,
        string $systemPrompt,
        array $conversationHistory,
        ?string $playerAction,
        string $protagonist,
        float $temperature = 1.0,
    ): array {
        $config = self::MODEL_CONFIG[$model] ?? self::MODEL_CONFIG[self::DEFAULT_MODEL];

        $promptText = view('ai.agents.chaos.turn-prompt', [
            'conversationHistory' => $conversationHistory,
            'playerAction'        => $playerAction,
            'protagonist'         => $protagonist,
        ])->render();

        $schemaArray     = ChaosNarrationSchema::definition(new JsonSchemaTypeFactory);
        $providerOptions = $this->providerOptionsFor($config);

        $request = Prism::structured()
            ->using($config['provider'], $model)
            ->withSchema(new NativeObjectSchema($schemaArray))
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($promptText)
            ->usingTemperature($temperature)
            ->withClientOptions(['timeout' => 90])
            ->withProviderOptions($providerOptions);

        if ($config['provider'] === 'anthropic') {
            $request = $request->withMaxTokens(64_000);
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response   = $request->asStructured();
                $structured = $response->structured ?? [];

                $alignment = (array) ($structured['alignment_tally_delta'] ?? []);

                return [
                    'response'               => (string) ($structured['response'] ?? ''),
                    'choices'                => (array)  ($structured['choices'] ?? []),
                    'session_complete'       => (bool)   ($structured['session_complete'] ?? false),
                    'state_delta'            => (array)  ($structured['state_delta'] ?? []),
                    'alignment_tally_delta'  => [
                        'chaotic' => (int) ($alignment['chaotic'] ?? 0),
                        'lawful'  => (int) ($alignment['lawful'] ?? 0),
                        'neutral' => (int) ($alignment['neutral'] ?? 0),
                    ],
                    'is_climactic_choice'    => (bool)   ($structured['is_climactic_choice'] ?? false),
                    'defining_choice_id'     => (string) ($structured['defining_choice_id'] ?? ''),
                    'defining_choice_line'   => (string) ($structured['defining_choice_line'] ?? ''),
                    'symbolic_memory_update' => (string) ($structured['symbolic_memory_update'] ?? ''),
                    'session_memory_update'  => (string) ($structured['session_memory_update'] ?? ''),
                ];
            } catch (Throwable $e) {
                $lastException = $e;

                if ($attempt < 2) {
                    usleep(300_000);
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Agent call failed with no captured exception.');
    }

    /**
     * Merge a state_delta into persisted world_state.
     *
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    public function mergeStateDelta(array $previous, array $delta): array
    {
        $merged = $this->emptyWorldState();

        $merged['location'] = trim((string) ($delta['location'] ?? '')) !== ''
            ? (string) $delta['location']
            : (string) ($previous['location'] ?? '');

        foreach (self::COMPLETE_LIST_STATE_FIELDS as $key) {
            $value = $delta[$key] ?? null;
            $merged[$key] = is_array($value)
                ? $this->cleanStringList($value)
                : $this->cleanStringList($previous[$key] ?? []);
        }

        foreach (self::KEYED_DELTA_STATE_FIELDS as $key) {
            $value = $delta[$key] ?? null;
            $merged[$key] = is_array($value)
                ? $this->mergeKeyedStateList((array) ($previous[$key] ?? []), $value)
                : $this->cleanStringList($previous[$key] ?? []);
        }

        foreach (self::APPEND_ONLY_STATE_FIELDS as $key) {
            $value = $delta[$key] ?? null;
            $merged[$key] = is_array($value)
                ? $this->appendUniqueStringList((array) ($previous[$key] ?? []), $value)
                : $this->cleanStringList($previous[$key] ?? []);
        }

        $previousLedger = (array) ($previous['emotional_ledger'] ?? []);
        $newLedger      = [];
        foreach ((array) ($delta['emotional_ledger_entries'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $category = trim((string) ($entry['category'] ?? ''));
            $text     = trim((string) ($entry['entry'] ?? ''));
            if ($category === '' || $text === '') {
                continue;
            }
            $newLedger[] = ['category' => $category, 'entry' => $text];
        }

        $merged['emotional_ledger'] = array_values(array_merge($previousLedger, $newLedger));

        return $merged;
    }

    /**
     * @param  array{chaotic:int, lawful:int, neutral:int}  $previous
     * @param  array{chaotic:int, lawful:int, neutral:int}  $delta
     * @return array{chaotic:int, lawful:int, neutral:int}
     */
    public function mergeAlignmentDelta(array $previous, array $delta): array
    {
        return [
            'chaotic' => max(0, (int) ($previous['chaotic'] ?? 0) + (int) ($delta['chaotic'] ?? 0)),
            'lawful'  => max(0, (int) ($previous['lawful'] ?? 0) + (int) ($delta['lawful'] ?? 0)),
            'neutral' => max(0, (int) ($previous['neutral'] ?? 0) + (int) ($delta['neutral'] ?? 0)),
        ];
    }

    public function appendMemory(?string $existing, string $update): ?string
    {
        $update = trim($update);
        if ($update === '') {
            return $existing;
        }

        return $existing ? trim($existing) . "\n" . $update : $update;
    }

    /**
     * @param  array<int, array{role:string, text:string}>  $history
     * @return array<int, array{role:string, text:string}>
     */
    public function appendNarratorTurn(array $history, string $responseHtml): array
    {
        $history[] = ['role' => 'narrator', 'text' => $this->stripHtml($responseHtml)];

        return $history;
    }

    /**
     * @param  array<int, array{role:string, text:string}>  $history
     * @return array<int, array{role:string, text:string}>
     */
    public function appendPlayerTurn(array $history, string $action, string $protagonist = 'the protagonist'): array
    {
        $history[] = ['role' => 'player', 'text' => $action, 'protagonist' => $protagonist];

        return $history;
    }

    public function stripHtml(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @return array{
     *   location:string,
     *   conditions:array<int,string>,
     *   items:array<int,string>,
     *   object_states:array<int,string>,
     *   relationship_updates:array<int,string>,
     *   world_flags:array<int,string>,
     *   knowledge:array<int,string>,
     *   notes:array<int,string>,
     *   player_style:array<int,string>,
     *   unresolved_promises:array<int,string>,
     *   emotional_ledger:array<int, array{category:string, entry:string}>,
     * }
     */
    public function emptyWorldState(): array
    {
        return [
            'location'             => '',
            'conditions'           => [],
            'items'                => [],
            'object_states'        => [],
            'relationship_updates' => [],
            'world_flags'          => [],
            'knowledge'            => [],
            'notes'                => [],
            'player_style'         => [],
            'unresolved_promises'  => [],
            'emotional_ledger'     => [],
        ];
    }

    /**
     * @return array{chaotic:int, lawful:int, neutral:int}
     */
    public function emptyAlignmentScaffold(): array
    {
        return ['chaotic' => 0, 'lawful' => 0, 'neutral' => 0];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array<int, array<string, mixed>>  $alignmentLabels
     * @param  array{chaotic:int, lawful:int, neutral:int}  $scaffold
     */
    private function renderAlignmentTilt(array $alignmentLabels, array $scaffold): string
    {
        $chaotic = (int) ($scaffold['chaotic'] ?? 0);
        $lawful  = (int) ($scaffold['lawful'] ?? 0);
        $neutral = (int) ($scaffold['neutral'] ?? 0);

        $total = $chaotic + $lawful + $neutral;
        if ($total === 0) {
            return 'No player tendency has declared itself yet. Keep all story-native tonal registers available without naming alignment.';
        }

        $dominant  = 'mixed';
        $threshold = (int) ceil($total * 0.4);
        $top       = max($chaotic, $lawful, $neutral);
        $tieCount  = (int) ($chaotic === $top) + (int) ($lawful === $top) + (int) ($neutral === $top);

        if ($tieCount === 1 && $top >= $threshold) {
            $dominant = match (true) {
                $chaotic === $top => 'chaotic',
                $lawful === $top  => 'lawful',
                default           => 'neutral',
            };
        }

        $label          = null;
        $description    = null;
        $voiceSignature = null;
        foreach ($alignmentLabels as $row) {
            $mapsTo = strtolower((string) ($row['maps_to_internal'] ?? ''));
            if ($mapsTo === $dominant) {
                $label          = (string) ($row['label'] ?? '');
                $description    = (string) ($row['narrative_consequences'] ?? ($row['description'] ?? ''));
                $voiceSignature = (string) ($row['voice_signature'] ?? '');
                break;
            }
        }

        if ($label === null || $label === '') {
            return 'The player\'s alignment has tilted, but no story-native label is configured for "' . $dominant
                . '". Narrate without surfacing the alignment, but lean toward this tendency in tone.';
        }

        $lines   = ['STORY-NATIVE ALIGNMENT TILT: "' . $label . '" (hidden — never surface the literal label or any RPG terminology).'];
        if ($description !== '') {
            $lines[] = 'Behavioural shape: ' . $description;
        }
        if ($voiceSignature !== '') {
            $lines[] = 'Voice signature: ' . $voiceSignature;
        }
        $lines[] = 'Tune the narrator\'s voice toward this tendency, but never call it out.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $worldState
     * @param  array<int, array<string, mixed>>  $sessionEvents
     */
    private function renderTieredWorldState(array $worldState, bool $isClimactic, array $sessionEvents): string
    {
        $tier1 = $this->formatTier1($worldState);
        $tier2 = $this->formatTier2($worldState, $sessionEvents);

        if ($isClimactic) {
            $tier3 = $this->formatTier3($worldState);

            return implode("\n\n", array_filter([$tier1, $tier2, $tier3]));
        }

        return implode("\n\n", array_filter([$tier1, $tier2]));
    }

    /**
     * @param  array<string, mixed>  $w
     */
    private function formatTier1(array $w): string
    {
        $lines    = ['PERSISTENT STATE — TIER 1 (always loaded):'];
        $location = trim((string) ($w['location'] ?? ''));
        $lines[]  = 'Location: ' . ($location !== '' ? $location : '(unset)');
        $lines[]  = 'Items: ' . $this->joinList((array) ($w['items'] ?? []));
        $lines[]  = 'Conditions: ' . $this->joinList((array) ($w['conditions'] ?? []));
        $lines[]  = 'Knowledge: ' . $this->joinList((array) ($w['knowledge'] ?? []));
        $lines[]  = 'Notes: ' . $this->joinList((array) ($w['notes'] ?? []));
        $lines[]  = 'Player style: ' . $this->joinList((array) ($w['player_style'] ?? []));

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $w
     * @param  array<int, array<string, mixed>>  $sessionEvents
     */
    private function formatTier2(array $w, array $sessionEvents): string
    {
        $haystack = strtolower(implode(' ', array_map(
            static fn ($e) => (string) ($e['title'] ?? '') . ' ' . (string) ($e['content'] ?? ''),
            $sessionEvents,
        )));

        $allObjects       = (array) ($w['object_states'] ?? []);
        $allRelationships = (array) ($w['relationship_updates'] ?? []);
        $allFlags         = (array) ($w['world_flags'] ?? []);

        $object               = $this->filterByHaystack($allObjects, $haystack);
        $relationships        = $this->filterByHaystack($allRelationships, $haystack);
        $flags                = $this->filterByHaystack($allFlags, $haystack);
        $dormantObjects       = array_values(array_diff($allObjects, $object));
        $dormantRelationships = array_values(array_diff($allRelationships, $relationships));
        $dormantFlags         = array_values(array_diff($allFlags, $flags));
        $promises             = (array) ($w['unresolved_promises'] ?? []);

        $ledger       = (array) ($w['emotional_ledger'] ?? []);
        $recentLedger = array_slice($ledger, -6);
        $ledgerLines  = array_map(
            static fn (array $entry) => sprintf('%s: %s', $entry['category'] ?? 'note', $entry['entry'] ?? ''),
            array_filter($recentLedger, static fn ($e) => is_array($e)),
        );

        $lines   = ['PERSISTENT STATE — TIER 2 (scene-connected):'];
        $lines[] = 'Object states: ' . $this->joinList($object);
        $lines[] = 'Relationships: ' . $this->joinList($relationships);
        $lines[] = 'World flags: ' . $this->joinList($flags);
        $lines[] = 'Other carried object states: ' . $this->joinList($dormantObjects);
        $lines[] = 'Other carried relationships: ' . $this->joinList($dormantRelationships);
        $lines[] = 'Other carried world flags: ' . $this->joinList($dormantFlags);
        $lines[] = 'Unresolved promises: ' . $this->joinList($promises);
        $lines[] = 'Recent emotional ledger: ' . $this->joinList($ledgerLines);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $w
     */
    private function formatTier3(array $w): string
    {
        $ledger    = (array) ($w['emotional_ledger'] ?? []);
        $allLedger = array_map(
            static fn (array $entry) => sprintf('%s: %s', $entry['category'] ?? 'note', $entry['entry'] ?? ''),
            array_filter($ledger, static fn ($e) => is_array($e)),
        );

        $lines   = ['PERSISTENT STATE — TIER 3 (climactic load — previous turn resolved a moral-weight or session-end choice):'];
        $lines[] = 'All object states: ' . $this->joinList((array) ($w['object_states'] ?? []));
        $lines[] = 'All relationships: ' . $this->joinList((array) ($w['relationship_updates'] ?? []));
        $lines[] = 'All world flags: ' . $this->joinList((array) ($w['world_flags'] ?? []));
        $lines[] = 'Full emotional ledger: ' . $this->joinList($allLedger);

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $entries
     * @return array<int, string>
     */
    private function filterByHaystack(array $entries, string $haystack): array
    {
        if ($haystack === '') {
            return $entries;
        }

        $filtered = [];
        foreach ($entries as $entry) {
            $name = strtolower((string) (explode(':', (string) $entry, 2)[0] ?? ''));
            if ($name !== '' && str_contains($haystack, $name)) {
                $filtered[] = $entry;
            }
        }

        return $filtered === [] ? $entries : $filtered;
    }

    /**
     * @param  array<int, string>  $list
     */
    private function joinList(array $list): string
    {
        $list = array_values(array_filter(array_map('trim', $list), static fn ($v) => $v !== ''));

        return $list === [] ? '(none)' : implode(' • ', $list);
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function cleanStringList(mixed $value): array
    {
        return array_values(array_filter(
            array_map(static fn ($item) => trim((string) $item), (array) $value),
            static fn (string $item) => $item !== '',
        ));
    }

    /**
     * @param  array<int, mixed>  $previous
     * @param  array<int, mixed>  $delta
     * @return array<int, string>
     */
    private function mergeKeyedStateList(array $previous, array $delta): array
    {
        $entriesByKey = [];
        $orderedKeys  = [];

        foreach (array_merge($this->cleanStringList($previous), $this->cleanStringList($delta)) as $entry) {
            $key = $this->stateEntryKey($entry);

            if (! array_key_exists($key, $entriesByKey)) {
                $orderedKeys[] = $key;
            }

            $entriesByKey[$key] = $entry;
        }

        return array_values(array_map(
            static fn (string $key) => $entriesByKey[$key],
            $orderedKeys,
        ));
    }

    private function stateEntryKey(string $entry): string
    {
        $name = trim((string) (explode(':', $entry, 2)[0] ?? $entry));

        return strtolower($name !== '' ? $name : $entry);
    }

    /**
     * @param  array<int, mixed>  $previous
     * @param  array<int, mixed>  $delta
     * @return array<int, string>
     */
    private function appendUniqueStringList(array $previous, array $delta): array
    {
        $seen   = [];
        $merged = [];

        foreach (array_merge($this->cleanStringList($previous), $this->cleanStringList($delta)) as $entry) {
            $key = strtolower($entry);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $merged[]   = $entry;
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $storyMap
     * @return array<string, mixed>
     */
    private function findAllocationRow(array $storyMap, int $sessionNumber): array
    {
        foreach ((array) ($storyMap['session_allocation'] ?? []) as $row) {
            if ((int) ($row['session_number'] ?? 0) === $sessionNumber) {
                return (array) $row;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function findArcProgressionRow(?StoryAdaptation $adaptation, int $sessionNumber): array
    {
        $storyMap = (array) ($adaptation?->story_session_map ?? []);
        foreach ((array) ($storyMap['arc_progression'] ?? []) as $row) {
            if ((int) ($row['session_number'] ?? 0) === $sessionNumber) {
                return (array) $row;
            }
        }

        return [];
    }

    private function normalizeOpeningScene(mixed $value): string
    {
        $text = trim((string) $value);

        return in_array(strtolower($text), ['n/a', 'na', 'none', 'null', '(none)'], true)
            ? ''
            : $text;
    }

    /**
     * @param  array{provider:string, temperature:float, reasoning_effort:?string}  $config
     * @return array<string, mixed>
     */
    private function providerOptionsFor(array $config): array
    {
        if ($config['provider'] === 'openai') {
            $options = ['schema' => ['strict' => true]];

            if (! empty($config['reasoning_effort'])) {
                $options['reasoning'] = ['effort' => $config['reasoning_effort']];
            }

            return $options;
        }

        return [];
    }
}
