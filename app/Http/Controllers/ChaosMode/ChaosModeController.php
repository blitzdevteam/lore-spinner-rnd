<?php

declare(strict_types=1);

namespace App\Http\Controllers\ChaosMode;

use App\Ai\Agents\Chaos\ChaosNarrationSchema;
use App\ChaosMode\ChaosStoryConfig;
use App\Http\Controllers\Controller;
use App\Models\ChaosSession;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Response;
use Laravel\Ai\ObjectSchema;
use Prism\Prism\Facades\Prism;
use Throwable;

/**
 * Chaos Mode runtime — multi-story, multi-session.
 *
 * Architecture:
 *   - The AI controls narration, pacing, and movement INSIDE the active session.
 *   - The runtime controls only which session is loaded, persistent state,
 *     conversation log, and the technical boundary between sessions.
 *   - No advance_event, no scene_note, no suggested_playhead. The AI is given
 *     the full session script + adaptation spine and trusted to move through it.
 *
 * Story selection: every chaos-enabled story is whitelisted in ChaosStoryConfig.
 * Session context is loaded entirely from the DB:
 *   - StoryAdaptation.story_session_map gives the per-session event range and
 *     dramatic question.
 *   - SessionAdaptation per session_number provides cold open, beat map,
 *     authored choices, close design, and next-session seed.
 *   - Event rows in the range are streamed into the prompt as the full source
 *     script for the current session.
 *
 * Session continuation: when the AI returns `session_complete: true`, the
 * runtime exposes `/chaos-mode/continue` which creates a NEW ChaosSession at
 * `story_session_number + 1`, seeded with the prior session's memory + world
 * state and the new session's `opens_with` handoff.
 */
final class ChaosModeController extends Controller
{
    /**
     * Single source of truth for every chaos-mode model.
     *
     * - provider           Prism provider key.
     * - temperature        Default sampling temperature (overridable per request).
     * - reasoning_effort   Only set for OpenAI reasoning models. Keeping this at
     *                     'low' is the difference between ~6s and ~40s per turn
     *                     on gpt-5.x. The OpenAI Responses API accepts
     *                     none|low|medium|high|xhigh; 'low' keeps the
     *                     narrative quality without paying the full
     *                     thinking-time cost. gpt-4.1 is not a reasoning
     *                     model so this is null there.
     */
    private const MODEL_CONFIG = [
        'gpt-5.4'           => ['provider' => 'openai',    'temperature' => 1.0,  'reasoning_effort' => 'low'],
        'gpt-5.4-mini'      => ['provider' => 'openai',    'temperature' => 0.95, 'reasoning_effort' => 'low'],
        'gpt-5.2'           => ['provider' => 'openai',    'temperature' => 1.0,  'reasoning_effort' => 'low'],
        'gpt-4.1'           => ['provider' => 'openai',    'temperature' => 1.0,  'reasoning_effort' => null],
        'claude-opus-4-7'   => ['provider' => 'anthropic', 'temperature' => 1.0,  'reasoning_effort' => null],
        'claude-sonnet-4-6' => ['provider' => 'anthropic', 'temperature' => 1.0,  'reasoning_effort' => null],
        'claude-haiku-4-5'  => ['provider' => 'anthropic', 'temperature' => 0.95, 'reasoning_effort' => null],
    ];

    private const DEFAULT_MODEL = 'gpt-5.2';

    /**
     * Allowed model slugs. Keep the same list on every endpoint.
     *
     * @return array<int, string>
     */
    private static function allowedModels(): array
    {
        return array_keys(self::MODEL_CONFIG);
    }

    private static function defaultTemperatureFor(string $model): float
    {
        return (float) (self::MODEL_CONFIG[$model]['temperature'] ?? 1.0);
    }

    /**
     * Chaos Mode landing page.
     *
     * Lists every chaos-enabled story plus whether it has the DB data
     * required to actually run (adaptation + at least one session adaptation).
     */
    public function show(): Response
    {
        $configured = ChaosStoryConfig::all();
        $slugs      = array_column($configured, 'slug');

        $stories = Story::query()
            ->whereIn('slug', $slugs)
            ->with(['adaptation', 'adaptation.sessionAdaptations', 'media'])
            ->get(['id', 'title', 'slug']);

        $payload = array_map(function (array $row) use ($stories) {
            $story = $stories->firstWhere('slug', $row['slug']);

            $hasAdaptation = $story?->adaptation !== null;
            $sessionCount  = (int) ($story?->adaptation?->sessionAdaptations?->count() ?? 0);

            $cover = $story?->getFirstMediaUrl('cover') ?: null;

            return [
                'slug'             => $row['slug'],
                'title'            => $row['title'],
                'tagline'          => $row['tagline'],
                'protagonist'      => $row['protagonist'],
                'available'        => $story !== null && $hasAdaptation && $sessionCount > 0,
                'total_sessions'   => $sessionCount,
                'cover'            => $cover ?: null,
            ];
        }, $configured);

        return inertia('ChaosMode', [
            'stories' => $payload,
        ]);
    }

    /**
     * Start a fresh chaos session.
     *
     * Always creates session number 1. The cold open is rendered into the
     * system prompt as $currentScene exactly once. Subsequent turns omit it.
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'story_slug'  => ['required', 'string', 'in:' . implode(',', ChaosStoryConfig::slugs())],
            'model'       => ['nullable', 'string', 'in:' . implode(',', self::allowedModels())],
            'temperature' => ['nullable', 'numeric', 'min:0.5', 'max:1.5'],
        ]);

        $storySlug   = $request->string('story_slug')->toString();
        $model       = $request->string('model', self::DEFAULT_MODEL)->toString();
        $temperature = (float) $request->input('temperature', self::defaultTemperatureFor($model));
        $storyConfig = ChaosStoryConfig::find($storySlug);

        if ($storyConfig === null) {
            return response()->json(['error' => 'Unknown story.'], 422);
        }

        $story = Story::query()
            ->where('slug', $storySlug)
            ->with(['adaptation', 'adaptation.sessionAdaptations'])
            ->first();

        if ($story === null || $story->adaptation === null) {
            return response()->json(['error' => 'This story has no adaptation yet.'], 422);
        }

        $sessionContext = $this->loadSessionContext($story, sessionNumber: 1, openingHandoff: null);

        if (empty($sessionContext['full_session_events'])) {
            return response()->json(['error' => 'This story has no events to narrate yet.'], 422);
        }

        $chaosSession = ChaosSession::create([
            'story_id'             => $story->id,
            'user_id'              => Auth::id(),
            'story_session_number' => 1,
            'model'                => $model,
            'conversation_history' => [],
            'world_state'          => $this->emptyWorldState(),
            'session_memory'       => null,
            'session_complete'     => false,
            'turn_count'           => 0,
            'ip_address'           => $request->ip(),
        ]);

        $systemPrompt = $this->renderSystemPrompt(
            storyConfig:    $storyConfig,
            sessionContext: $sessionContext,
            worldState:     $this->emptyWorldState(),
            sessionMemory:  null,
            currentScene:   $sessionContext['cold_open'] ?? null,
        );

        try {
            $result = $this->callAgent(
                model:               $model,
                systemPrompt:        $systemPrompt,
                conversationHistory: [],
                playerAction:        null,
                protagonist:         $storyConfig['protagonist'],
                temperature:         $temperature,
            );

            $worldState = $this->mergeStateDelta($this->emptyWorldState(), $result['state_delta']);
            $history    = $this->appendNarratorTurn([], $result['response']);
            $memory     = $this->appendMemory(null, $result['session_memory_update']);

            $chaosSession->update([
                'conversation_history' => $history,
                'world_state'          => $worldState,
                'session_memory'       => $memory,
                'session_complete'     => $result['session_complete'],
                'turn_count'           => 1,
            ]);

            Log::channel('narration')->info('chaos.start', [
                'session_id'       => $chaosSession->id,
                'story_slug'       => $storySlug,
                'session_number'   => 1,
                'model'            => $model,
                'temperature'      => $temperature,
                'response_bytes'   => strlen($result['response']),
                'session_complete' => $result['session_complete'],
            ]);

            return response()->json($this->formatResult($chaosSession, $result, $storyConfig));
        } catch (Throwable $e) {
            Log::channel('narration')->error('chaos.start_failed', [
                'session_id' => $chaosSession->id,
                'story_slug' => $storySlug,
                'model'      => $model,
                'exception'  => $e::class,
                'message'    => $e->getMessage(),
            ]);

            return response()->json(['error' => 'The narration engine is currently unavailable. Please try again.'], 500);
        }
    }

    /**
     * Process a player turn inside the currently active chaos session.
     */
    public function turn(Request $request): JsonResponse
    {
        $request->validate([
            'session_id'    => ['required', 'string', 'ulid'],
            'player_action' => ['required', 'string', 'min:1', 'max:500'],
            'model'         => ['nullable', 'string', 'in:' . implode(',', self::allowedModels())],
            'temperature'   => ['nullable', 'numeric', 'min:0.5', 'max:1.5'],
        ]);

        $chaosSession = ChaosSession::query()->findOrFail($request->string('session_id')->toString());

        if ($chaosSession->session_complete) {
            return response()->json([
                'error'             => 'This session is already complete.',
                'session_complete'  => true,
            ], 409);
        }

        $playerAction = $request->string('player_action')->toString();
        $model        = $request->string('model', $chaosSession->model)->toString();
        $temperature  = (float) $request->input('temperature', self::defaultTemperatureFor($model));

        $story = Story::query()
            ->where('id', $chaosSession->story_id)
            ->with(['adaptation', 'adaptation.sessionAdaptations'])
            ->first();

        if ($story === null) {
            return response()->json(['error' => 'Story not found.'], 404);
        }

        $storyConfig = ChaosStoryConfig::find($story->slug);
        if ($storyConfig === null) {
            return response()->json(['error' => 'Story no longer available in chaos mode.'], 410);
        }

        $sessionContext = $this->loadSessionContext(
            story:          $story,
            sessionNumber:  (int) $chaosSession->story_session_number,
            openingHandoff: null,
        );

        $worldState  = $chaosSession->world_state ?? $this->emptyWorldState();
        $history     = (array) ($chaosSession->conversation_history ?? []);

        // Keep last 12 turns for context budget; persisted history stays full.
        $sentHistory = array_slice($history, -12);

        $systemPrompt = $this->renderSystemPrompt(
            storyConfig:    $storyConfig,
            sessionContext: $sessionContext,
            worldState:     $worldState,
            sessionMemory:  $chaosSession->session_memory,
            currentScene:   null, // cold open is in history; never re-anchor it
        );

        try {
            $result = $this->callAgent(
                model:               $model,
                systemPrompt:        $systemPrompt,
                conversationHistory: $sentHistory,
                playerAction:        $playerAction,
                protagonist:         $storyConfig['protagonist'],
                temperature:         $temperature,
            );

            $worldState = $this->mergeStateDelta($worldState, $result['state_delta']);
            $history    = $this->appendPlayerTurn($history, $playerAction, $storyConfig['protagonist']);
            $history    = $this->appendNarratorTurn($history, $result['response']);
            $memory     = $this->appendMemory($chaosSession->session_memory, $result['session_memory_update']);

            $chaosSession->update([
                'conversation_history' => $history,
                'world_state'          => $worldState,
                'session_memory'       => $memory,
                'session_complete'     => $result['session_complete'],
                'turn_count'           => $chaosSession->turn_count + 1,
                'model'                => $model,
            ]);

            Log::channel('narration')->info('chaos.turn', [
                'session_id'       => $chaosSession->id,
                'story_slug'       => $story->slug,
                'session_number'   => (int) $chaosSession->story_session_number,
                'model'            => $model,
                'temperature'      => $temperature,
                'turn'             => $chaosSession->turn_count,
                'session_complete' => $result['session_complete'],
                'player_action'    => mb_substr($playerAction, 0, 80),
                'memory_update'    => mb_substr($result['session_memory_update'], 0, 120),
            ]);

            return response()->json($this->formatResult($chaosSession, $result, $storyConfig));
        } catch (Throwable $e) {
            Log::channel('narration')->error('chaos.turn_failed', [
                'session_id'    => $chaosSession->id,
                'model'         => $model,
                'exception'     => $e::class,
                'message'       => $e->getMessage(),
                'player_action' => mb_substr($playerAction, 0, 80),
            ]);

            return response()->json(['error' => 'The narration hiccuped. Your action was preserved — please try again.'], 500);
        }
    }

    /**
     * Continue from a completed session into the next session in the arc.
     *
     * Creates a NEW ChaosSession at story_session_number + 1, carries the
     * world_state + session_memory forward, and feeds the next session's
     * `opens_with` handoff as the opening scene.
     */
    public function continueSession(Request $request): JsonResponse
    {
        $request->validate([
            'session_id'  => ['required', 'string', 'ulid'],
            'model'       => ['nullable', 'string', 'in:' . implode(',', self::allowedModels())],
            'temperature' => ['nullable', 'numeric', 'min:0.5', 'max:1.5'],
        ]);

        $previous = ChaosSession::query()->findOrFail($request->string('session_id')->toString());

        if (! $previous->session_complete) {
            return response()->json(['error' => 'This session is not complete yet.'], 409);
        }

        $story = Story::query()
            ->where('id', $previous->story_id)
            ->with(['adaptation', 'adaptation.sessionAdaptations'])
            ->first();

        if ($story === null) {
            return response()->json(['error' => 'Story not found.'], 404);
        }

        $storyConfig = ChaosStoryConfig::find($story->slug);
        if ($storyConfig === null) {
            return response()->json(['error' => 'Story no longer available in chaos mode.'], 410);
        }

        $nextSessionNumber = (int) $previous->story_session_number + 1;
        $totalSessions     = (int) $story->adaptation->sessionAdaptations->count();

        if ($nextSessionNumber > $totalSessions) {
            return response()->json([
                'error'       => 'This story has no more sessions to play.',
                'story_done'  => true,
            ], 410);
        }

        // The handoff: the `opens_with` from arc_progression tells the AI where
        // the next session picks up. Without it the narrator would re-cold-open.
        $arcRow         = $this->findArcProgressionRow($story->adaptation, $nextSessionNumber);
        $openingHandoff = (string) ($arcRow['opens_with'] ?? '');

        $sessionContext = $this->loadSessionContext($story, $nextSessionNumber, $openingHandoff);

        if (empty($sessionContext['full_session_events'])) {
            return response()->json(['error' => 'The next session has no events to narrate.'], 422);
        }

        $model       = $request->string('model', $previous->model)->toString();
        $temperature = (float) $request->input('temperature', self::defaultTemperatureFor($model));

        $chaosSession = ChaosSession::create([
            'story_id'             => $story->id,
            'user_id'              => Auth::id(),
            'story_session_number' => $nextSessionNumber,
            'model'                => $model,
            'conversation_history' => [],
            // Carry world state + memory across the boundary so emergent facts persist.
            'world_state'          => $previous->world_state ?? $this->emptyWorldState(),
            'session_memory'       => $previous->session_memory,
            'session_complete'     => false,
            'turn_count'           => 0,
            'ip_address'           => $request->ip(),
        ]);

        // Build the scene context for this session opener. Prefer the authored
        // `opens_with` handoff; fall back to the per-session cold open.
        $sceneForOpener = trim($openingHandoff) !== ''
            ? $openingHandoff
            : ($sessionContext['cold_open'] ?? null);

        $systemPrompt = $this->renderSystemPrompt(
            storyConfig:    $storyConfig,
            sessionContext: $sessionContext,
            worldState:     $chaosSession->world_state ?? $this->emptyWorldState(),
            sessionMemory:  $chaosSession->session_memory,
            currentScene:   $sceneForOpener,
        );

        try {
            $result = $this->callAgent(
                model:               $model,
                systemPrompt:        $systemPrompt,
                conversationHistory: [],
                playerAction:        null,
                protagonist:         $storyConfig['protagonist'],
                temperature:         $temperature,
            );

            $worldState = $this->mergeStateDelta($chaosSession->world_state ?? $this->emptyWorldState(), $result['state_delta']);
            $history    = $this->appendNarratorTurn([], $result['response']);
            $memory     = $this->appendMemory($chaosSession->session_memory, $result['session_memory_update']);

            $chaosSession->update([
                'conversation_history' => $history,
                'world_state'          => $worldState,
                'session_memory'       => $memory,
                'session_complete'     => $result['session_complete'],
                'turn_count'           => 1,
            ]);

            Log::channel('narration')->info('chaos.continue', [
                'previous_session_id' => $previous->id,
                'session_id'          => $chaosSession->id,
                'story_slug'          => $story->slug,
                'session_number'      => $nextSessionNumber,
                'model'               => $model,
                'session_complete'    => $result['session_complete'],
            ]);

            return response()->json($this->formatResult($chaosSession, $result, $storyConfig));
        } catch (Throwable $e) {
            Log::channel('narration')->error('chaos.continue_failed', [
                'session_id' => $chaosSession->id,
                'model'      => $model,
                'exception'  => $e::class,
                'message'    => $e->getMessage(),
            ]);

            return response()->json(['error' => 'The narration engine could not open the next session. Please try again.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // DB-driven session loading
    // -------------------------------------------------------------------------

    /**
     * Load the dramatic spine + full source script for the given session.
     *
     * @return array{
     *     session_number: int,
     *     total_sessions: int,
     *     opening_handoff: string,
     *     cold_open: string,
     *     emotional_promise: string,
     *     dramatic_question: string,
     *     emotional_register: string,
     *     chapters_covered: string,
     *     beat_map: array<int, array<string, mixed>>,
     *     authored_choices: array<int, array<string, mixed>>,
     *     session_destination: string,
     *     next_session_seed: string,
     *     full_session_events: array<int, array{position:int, title:string, content:string, objectives:?string}>,
     * }
     */
    private function loadSessionContext(Story $story, int $sessionNumber, ?string $openingHandoff): array
    {
        /** @var StoryAdaptation|null $adaptation */
        $adaptation = $story->adaptation;
        $storyMap   = (array) ($adaptation?->story_session_map ?? []);

        $allocation = $this->findAllocationRow($storyMap, $sessionNumber);

        $events = Event::query()
            ->whereHas('chapter', fn ($q) => $q->where('story_id', $story->id))
            ->where('session_number', $sessionNumber)
            ->join('chapters', 'events.chapter_id', '=', 'chapters.id')
            ->orderBy('chapters.position')
            ->orderBy('events.position')
            ->get(['events.position', 'events.title', 'events.content', 'events.objectives'])
            ->map(fn (Event $e) => [
                'position'   => (int) $e->position,
                'title'      => (string) $e->title,
                'content'    => (string) $e->content,
                'objectives' => $e->objectives,
            ])
            ->all();

        /** @var SessionAdaptation|null $sessionAdaptation */
        $sessionAdaptation = $adaptation?->sessionAdaptations
            ?->firstWhere('session_number', $sessionNumber);

        $entry        = (array) ($sessionAdaptation?->entry_point_diagnosis ?? []);
        $architecture = (array) ($sessionAdaptation?->session_architecture ?? []);
        $choiceDesign = (array) ($sessionAdaptation?->session_choice_design ?? []);
        $closeDesign  = (array) ($sessionAdaptation?->session_close_design ?? []);

        $authoredChoices = array_values(array_filter([
            $choiceDesign['branching_choice_1'] ?? null,
            $choiceDesign['branching_choice_2'] ?? null,
            $choiceDesign['branching_choice_3'] ?? null,
        ]));

        $sessionDestination = $closeDesign['hook_transition']
            ?? ($closeDesign['session_end_choice']['choice_question'] ?? '');

        $nextSessionSeed = $architecture['next_session_awareness']['seed_for_next_session'] ?? '';

        $totalSessions = (int) ($adaptation?->sessionAdaptations?->count() ?? 0);

        // If the caller didn't pass an explicit handoff, fall back to the
        // arc_progression row for this session (when present).
        if ($openingHandoff === null || trim($openingHandoff) === '') {
            $arcRow         = $this->findArcProgressionRow($adaptation, $sessionNumber);
            $openingHandoff = (string) ($arcRow['opens_with'] ?? '');
        }

        return [
            'session_number'      => $sessionNumber,
            'total_sessions'      => $totalSessions,
            'opening_handoff'     => $openingHandoff,
            'cold_open'           => (string) ($entry['cold_open'] ?? ''),
            'emotional_promise'   => (string) ($entry['emotional_promise'] ?? ''),
            'dramatic_question'   => (string) ($allocation['primary_dramatic_question'] ?? ''),
            'emotional_register'  => (string) ($allocation['emotional_register'] ?? ''),
            'chapters_covered'    => (string) ($allocation['chapters_covered'] ?? ''),
            'beat_map'            => (array)  ($architecture['beat_map'] ?? []),
            'authored_choices'    => $authoredChoices,
            'session_destination' => (string) $sessionDestination,
            'next_session_seed'   => (string) $nextSessionSeed,
            'full_session_events' => $events,
        ];
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

    /**
     * Parse "1-23" into [1, 23]. Accepts "1–23" en-dash too. Returns [null, null]
     * when the input is malformed so callers can fall back to an empty event list.
     *
     * @return array{0:int|null, 1:int|null}
     */
    private function parseEventRange(string $range): array
    {
        $range = str_replace(['—', '–'], '-', trim($range));

        if ($range === '' || ! preg_match('/^(\d+)\s*-\s*(\d+)$/', $range, $m)) {
            return [null, null];
        }

        $start = (int) $m[1];
        $end   = (int) $m[2];

        if ($start <= 0 || $end < $start) {
            return [null, null];
        }

        return [$start, $end];
    }

    // -------------------------------------------------------------------------
    // Prompt assembly + AI invocation
    // -------------------------------------------------------------------------

    /**
     * @param  array{slug:string, title:string, protagonist:string, voice_partial:string, tagline:string}  $storyConfig
     * @param  array<string, mixed>  $sessionContext
     * @param  array<string, mixed>  $worldState
     */
    private function renderSystemPrompt(
        array $storyConfig,
        array $sessionContext,
        array $worldState,
        ?string $sessionMemory,
        ?string $currentScene,
    ): string {
        return view('ai.agents.chaos.system-prompt', [
            'storyConfig'    => $storyConfig,
            'sessionContext' => $sessionContext,
            'worldState'     => $worldState,
            'sessionMemory'  => $sessionMemory,
            'currentScene'   => $currentScene,
        ])->render();
    }

    /**
     * Single chaos narration turn.
     *
     * We talk to Prism directly (skipping Laravel AI's agent pipeline) so we
     * can pass `reasoning.effort` to OpenAI's Responses API. Without that the
     * gpt-5.x reasoning models silently default to `medium` effort and burn
     * 20-40 seconds of thinking time on every turn — the exact symptom that
     * surfaced as "40s latency".
     *
     * @param  array<int, array{role:string, text:string}>  $conversationHistory
     * @return array{response:string, choices:array<int,string>, session_complete:bool, state_delta:array<string,mixed>, session_memory_update:string}
     */
    private function callAgent(
        string $model,
        string $systemPrompt,
        array $conversationHistory,
        ?string $playerAction,
        string $protagonist,
        float $temperature = 1.0,
    ): array {
        $config = self::MODEL_CONFIG[$model] ?? throw new \InvalidArgumentException("Unknown chaos model: {$model}");

        $promptText = view('ai.agents.chaos.turn-prompt', [
            'conversationHistory' => $conversationHistory,
            'playerAction'        => $playerAction,
            'protagonist'         => $protagonist,
        ])->render();

        $schemaArray = ChaosNarrationSchema::definition(new JsonSchemaTypeFactory);

        $providerOptions = $this->providerOptionsFor($config);

        $request = Prism::structured()
            ->using($config['provider'], $model)
            ->withSchema(new ObjectSchema($schemaArray))
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($promptText)
            ->usingTemperature($temperature)
            ->withClientOptions(['timeout' => 90])
            ->withProviderOptions($providerOptions);

        if ($config['provider'] === 'anthropic') {
            $request = $request->withMaxTokens(64_000);
        }

        // Single retry on transient failures. 300 ms back-off — fast enough
        // that a momentary blip doesn't visibly hang the UI.
        $lastException = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response   = $request->asStructured();
                $structured = $response->structured ?? [];

                return [
                    'response'              => (string) ($structured['response'] ?? ''),
                    'choices'               => (array)  ($structured['choices'] ?? []),
                    'session_complete'      => (bool)   ($structured['session_complete'] ?? false),
                    'state_delta'           => (array)  ($structured['state_delta'] ?? []),
                    'session_memory_update' => (string) ($structured['session_memory_update'] ?? ''),
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
     * Build provider-specific options for a Prism structured request.
     *
     * @param  array{provider:string, temperature:float, reasoning_effort:?string}  $config
     * @return array<string, mixed>
     */
    private function providerOptionsFor(array $config): array
    {
        if ($config['provider'] === 'openai') {
            $options = ['schema' => ['strict' => true]];

            if (! empty($config['reasoning_effort'])) {
                // Maps to OpenAI Responses API `reasoning.effort`. Valid
                // values: none|low|medium|high|xhigh. We pin to 'low' for
                // narrative work — fast enough for live play, still coherent.
                $options['reasoning'] = ['effort' => $config['reasoning_effort']];
            }

            return $options;
        }

        if ($config['provider'] === 'anthropic') {
            return ['use_tool_calling' => true];
        }

        return [];
    }

    // -------------------------------------------------------------------------
    // World state, history, formatting helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    private function mergeStateDelta(array $previous, array $delta): array
    {
        $merged = $this->emptyWorldState();

        $merged['location'] = trim((string) ($delta['location'] ?? '')) !== ''
            ? (string) $delta['location']
            : (string) ($previous['location'] ?? '');

        foreach (['conditions', 'items', 'relationships', 'knowledge', 'notes', 'player_style'] as $key) {
            $value = $delta[$key] ?? null;
            $merged[$key] = is_array($value)
                ? array_values(array_filter(array_map('strval', $value), fn ($v) => $v !== ''))
                : (array) ($previous[$key] ?? []);
        }

        return $merged;
    }

    /**
     * @return array{location:string, conditions:array<int,string>, items:array<int,string>, relationships:array<int,string>, knowledge:array<int,string>, notes:array<int,string>}
     */
    private function emptyWorldState(): array
    {
        return [
            'location'      => '',
            'conditions'    => [],
            'items'         => [],
            'relationships' => [],
            'knowledge'     => [],
            'notes'         => [],
            'player_style'  => [],
        ];
    }

    /**
     * @param  array<int, array{role:string, text:string}>  $history
     * @return array<int, array{role:string, text:string}>
     */
    private function appendNarratorTurn(array $history, string $responseHtml): array
    {
        $history[] = ['role' => 'narrator', 'text' => $this->stripHtml($responseHtml)];
        return $history;
    }

    /**
     * @param  array<int, array{role:string, text:string}>  $history
     * @return array<int, array{role:string, text:string}>
     */
    private function appendPlayerTurn(array $history, string $action, string $protagonist = 'the protagonist'): array
    {
        $history[] = ['role' => 'player', 'text' => $action, 'protagonist' => $protagonist];
        return $history;
    }

    private function appendMemory(?string $existing, string $update): ?string
    {
        $update = trim($update);
        if ($update === '') {
            return $existing;
        }
        return $existing ? trim($existing) . "\n" . $update : $update;
    }

    private function stripHtml(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @param  array{response:string, choices:array<int,string>, session_complete:bool, state_delta:array<string,mixed>, session_memory_update:string}  $result
     * @param  array{slug:string, title:string, protagonist:string, voice_partial:string, tagline:string}  $storyConfig
     * @return array<string, mixed>
     */
    private function formatResult(ChaosSession $chaosSession, array $result, array $storyConfig): array
    {
        $totalSessions = 0;
        if ($chaosSession->story_id !== null) {
            /** @var Story|null $story */
            $story = Story::query()->where('id', $chaosSession->story_id)->with('adaptation.sessionAdaptations')->first();
            $totalSessions = (int) ($story?->adaptation?->sessionAdaptations?->count() ?? 0);
        }

        $sessionNumber = (int) $chaosSession->story_session_number;
        $hasNext       = $totalSessions > 0 && $sessionNumber < $totalSessions;

        return [
            'session_id'            => $chaosSession->id,
            'story_slug'            => $storyConfig['slug'],
            'story_title'           => $storyConfig['title'],
            'protagonist'           => $storyConfig['protagonist'],
            'session_number'        => $sessionNumber,
            'total_sessions'        => $totalSessions,
            'has_next_session'      => $hasNext,
            'response'              => $result['response'],
            'choices'               => $result['choices'],
            'session_complete'      => $result['session_complete'],
            'world_state'           => $chaosSession->world_state,
            'session_memory_update' => $result['session_memory_update'],
            'turn_count'            => $chaosSession->turn_count,
        ];
    }
}
