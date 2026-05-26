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
 * Pipeline Upgrade V2 (Daniel's correction 2026-05-24): the system prompt
 * for every turn is the cached `session_adaptations.runtime_narrator_prompt`
 * produced by RuntimeNarratorAssemblyJob. The controller injects four
 * runtime-only blocks into that cached body using marker tokens:
 *
 *   - [SYMBOLIC_MEMORY_INJECTION_POINT]      Section 8 — chaos_sessions.symbolic_memory
 *   - [ALIGNMENT_TILT_INJECTION_POINT]       Section 9 — story-native alignment label
 *                                            derived from alignment_scaffold
 *   - [OPENING_SCENE_INJECTION_POINT]        Section 13 — cold open or arc handoff
 *                                            (turn 1 only)
 *   - [WORLD_STATE_TIERED_INJECTION_POINT]   Section 17 (tail) — Tier 1/2/3 view of
 *                                            chaos_sessions.world_state
 *
 * If `runtime_narrator_prompt` is null for a session, the endpoint returns
 * 422 with a "this story has not been re-adapted under V2" message. There
 * is no fallback to the legacy per-story partials.
 */
final class ChaosModeController extends Controller
{
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
            $sessionAdaptations = $story?->adaptation?->sessionAdaptations;
            $sessionCount  = (int) ($sessionAdaptations?->count() ?? 0);

            // V2 readiness: at least one session has a cached runtime narrator prompt.
            $v2Ready = $sessionAdaptations
                ?->contains(fn (SessionAdaptation $sa) => filled($sa->runtime_narrator_prompt))
                ?? false;

            $cover = $story?->getFirstMediaUrl('cover') ?: null;

            return [
                'slug'             => $row['slug'],
                'title'            => $row['title'],
                'tagline'          => $row['tagline'],
                'protagonist'      => $row['protagonist'],
                'available'        => $story !== null && $hasAdaptation && $sessionCount > 0 && $v2Ready,
                'total_sessions'   => $sessionCount,
                'v2_ready'         => $v2Ready,
                'cover'            => $cover ?: null,
            ];
        }, $configured);

        return inertia('ChaosMode', [
            'stories' => $payload,
        ]);
    }

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

        if ($sessionContext === null) {
            return response()->json([
                'error' => 'This story has not been re-adapted under V2 yet. Re-run the adaptation pipeline (`php artisan stories:run-adaptation '
                    . $storySlug . ' --force`) before starting Chaos Mode.',
            ], 422);
        }

        if (empty($sessionContext['full_session_events'])) {
            return response()->json(['error' => 'This story has no events to narrate yet.'], 422);
        }

        $emptyWorldState = $this->emptyWorldState();
        $emptyAlignment  = $this->emptyAlignmentScaffold();

        $chaosSession = ChaosSession::create([
            'story_id'             => $story->id,
            'user_id'              => Auth::id(),
            'story_session_number' => 1,
            'model'                => $model,
            'conversation_history' => [],
            'world_state'          => $emptyWorldState,
            'alignment_scaffold'   => $emptyAlignment,
            'session_memory'       => null,
            'symbolic_memory'      => null,
            'is_climactic_choice'  => false,
            'session_complete'     => false,
            'turn_count'           => 0,
            'ip_address'           => $request->ip(),
        ]);

        $systemPrompt = $this->renderSystemPrompt(
            sessionContext:      $sessionContext,
            worldState:          $emptyWorldState,
            alignmentScaffold:   $emptyAlignment,
            symbolicMemory:      null,
            currentScene:        $sessionContext['opening_scene'],
            isClimacticPrevious: false,
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

            $worldState        = $this->mergeStateDelta($emptyWorldState, $result['state_delta']);
            $alignmentScaffold = $this->mergeAlignmentDelta($emptyAlignment, $result['alignment_tally_delta']);
            $history           = $this->appendNarratorTurn([], $result['response']);
            $memory            = $this->appendMemory(null, $result['session_memory_update']);
            $symbolicMemory    = $this->appendMemory(null, $result['symbolic_memory_update']);

            $chaosSession->update([
                'conversation_history' => $history,
                'world_state'          => $worldState,
                'alignment_scaffold'   => $alignmentScaffold,
                'session_memory'       => $memory,
                'symbolic_memory'      => $symbolicMemory,
                'is_climactic_choice'  => (bool) $result['is_climactic_choice'],
                'defining_choice_id'   => $result['defining_choice_id'] !== '' ? $result['defining_choice_id'] : null,
                'defining_choice_line' => $result['defining_choice_line'] !== '' ? $result['defining_choice_line'] : null,
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

        if ($sessionContext === null) {
            return response()->json([
                'error' => 'This session has not been re-adapted under V2 yet. Re-run the adaptation pipeline before continuing.',
            ], 422);
        }

        $worldState        = $chaosSession->world_state ?? $this->emptyWorldState();
        $alignmentScaffold = $chaosSession->alignment_scaffold ?? $this->emptyAlignmentScaffold();
        $history           = (array) ($chaosSession->conversation_history ?? []);

        // Keep last 12 turns for context budget; persisted history stays full.
        $sentHistory = array_slice($history, -12);

        $systemPrompt = $this->renderSystemPrompt(
            sessionContext:      $sessionContext,
            worldState:          $worldState,
            alignmentScaffold:   $alignmentScaffold,
            symbolicMemory:      $chaosSession->symbolic_memory,
            currentScene:        null, // cold open is in history; never re-anchor it
            isClimacticPrevious: (bool) $chaosSession->is_climactic_choice,
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

            $worldState        = $this->mergeStateDelta($worldState, $result['state_delta']);
            $alignmentScaffold = $this->mergeAlignmentDelta($alignmentScaffold, $result['alignment_tally_delta']);
            $history           = $this->appendPlayerTurn($history, $playerAction, $storyConfig['protagonist']);
            $history           = $this->appendNarratorTurn($history, $result['response']);
            $memory            = $this->appendMemory($chaosSession->session_memory, $result['session_memory_update']);
            $symbolicMemory    = $this->appendMemory($chaosSession->symbolic_memory, $result['symbolic_memory_update']);

            $chaosSession->update([
                'conversation_history' => $history,
                'world_state'          => $worldState,
                'alignment_scaffold'   => $alignmentScaffold,
                'session_memory'       => $memory,
                'symbolic_memory'      => $symbolicMemory,
                'is_climactic_choice'  => (bool) $result['is_climactic_choice'],
                'defining_choice_id'   => $result['defining_choice_id'] !== '' ? $result['defining_choice_id'] : $chaosSession->defining_choice_id,
                'defining_choice_line' => $result['defining_choice_line'] !== '' ? $result['defining_choice_line'] : $chaosSession->defining_choice_line,
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
                'is_climactic'     => (bool) $result['is_climactic_choice'],
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

        $arcRow         = $this->findArcProgressionRow($story->adaptation, $nextSessionNumber);
        $openingHandoff = (string) ($arcRow['opens_with'] ?? '');

        $sessionContext = $this->loadSessionContext($story, $nextSessionNumber, $openingHandoff);

        if ($sessionContext === null) {
            return response()->json([
                'error' => 'The next session has not been re-adapted under V2 yet. Re-run the adaptation pipeline before continuing.',
            ], 422);
        }

        if (empty($sessionContext['full_session_events'])) {
            return response()->json(['error' => 'The next session has no events to narrate.'], 422);
        }

        $model       = $request->string('model', $previous->model)->toString();
        $temperature = (float) $request->input('temperature', self::defaultTemperatureFor($model));

        // Prefer V2 sidecar carry-over when present, fall back to legacy world_state.
        $carriedWorldState        = $previous->world_state ?? $this->emptyWorldState();
        $carriedAlignmentScaffold = $previous->alignment_scaffold ?? $this->emptyAlignmentScaffold();
        $carriedSymbolicMemory    = $previous->symbolic_memory;

        $chaosSession = ChaosSession::create([
            'story_id'             => $story->id,
            'user_id'              => Auth::id(),
            'story_session_number' => $nextSessionNumber,
            'model'                => $model,
            'conversation_history' => [],
            'world_state'          => $carriedWorldState,
            'alignment_scaffold'   => $carriedAlignmentScaffold,
            'session_memory'       => $previous->session_memory,
            'symbolic_memory'      => $carriedSymbolicMemory,
            'is_climactic_choice'  => false,
            'session_complete'     => false,
            'turn_count'           => 0,
            'ip_address'           => $request->ip(),
        ]);

        $sceneForOpener = trim($openingHandoff) !== ''
            ? $openingHandoff
            : ($sessionContext['opening_scene'] ?? '');

        $systemPrompt = $this->renderSystemPrompt(
            sessionContext:      $sessionContext,
            worldState:          $carriedWorldState,
            alignmentScaffold:   $carriedAlignmentScaffold,
            symbolicMemory:      $carriedSymbolicMemory,
            currentScene:        $sceneForOpener,
            isClimacticPrevious: false,
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

            $worldState        = $this->mergeStateDelta($carriedWorldState, $result['state_delta']);
            $alignmentScaffold = $this->mergeAlignmentDelta($carriedAlignmentScaffold, $result['alignment_tally_delta']);
            $history           = $this->appendNarratorTurn([], $result['response']);
            $memory            = $this->appendMemory($previous->session_memory, $result['session_memory_update']);
            $symbolicMemory    = $this->appendMemory($carriedSymbolicMemory, $result['symbolic_memory_update']);

            $chaosSession->update([
                'conversation_history' => $history,
                'world_state'          => $worldState,
                'alignment_scaffold'   => $alignmentScaffold,
                'session_memory'       => $memory,
                'symbolic_memory'      => $symbolicMemory,
                'is_climactic_choice'  => (bool) $result['is_climactic_choice'],
                'defining_choice_id'   => $result['defining_choice_id'] !== '' ? $result['defining_choice_id'] : null,
                'defining_choice_line' => $result['defining_choice_line'] !== '' ? $result['defining_choice_line'] : null,
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
     * Load the cached runtime narrator prompt plus per-session context
     * needed to assemble the injection points and the opening scene.
     *
     * Returns null when the session's `runtime_narrator_prompt` is null
     * (i.e. the story has not been re-adapted under V2 yet). Callers must
     * surface the 422 "re-run adaptation" message in that case.
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
    private function loadSessionContext(Story $story, int $sessionNumber, ?string $openingHandoff): ?array
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
                'id' => (int) $e->id,
                'chapter_id' => (int) $e->chapter_id,
                'chapter_position' => (int) $e->chapter_position,
                'chapter_title' => (string) $e->chapter_title,
                'position' => (int) $e->position,
                'session_number' => (int) $e->session_number,
                'title' => (string) $e->title,
                'content' => (string) $e->content,
                'objectives' => $e->objectives,
            ])
            ->all();

        $entry = (array) ($sessionAdaptation->entry_point_diagnosis ?? []);

        // If the caller didn't pass an explicit handoff, fall back to the
        // arc_progression row for this session (when present).
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

    // -------------------------------------------------------------------------
    // Prompt assembly + AI invocation
    // -------------------------------------------------------------------------

    /**
     * Inject runtime-only blocks (symbolic memory, alignment tilt, opening
     * scene, tiered world state) into the cached runtime narrator prompt.
     *
     * The cached body holds the four placeholder markers — we do a literal
     * string replacement so the assembled prompt is identical every turn
     * except for the four injection points.
     *
     * @param  array<string, mixed>  $sessionContext
     * @param  array<string, mixed>  $worldState
     * @param  array{chaotic:int, lawful:int, neutral:int}  $alignmentScaffold
     */
    private function renderSystemPrompt(
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
            worldState: $worldState,
            isClimactic: $isClimacticPrevious,
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
     * Story-native alignment translator. The narrator NEVER sees the literal
     * counter values or the words chaotic/lawful/neutral — only the IP-native
     * label derived from the dominant axis. When the scaffold is empty or
     * balanced, the "mixed" label is used.
     *
     * @param  array<int, array<string, mixed>>  $alignmentLabels  Phase 2 Task 9 output.
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

        $dominant = 'mixed';
        $threshold = (int) ceil($total * 0.4);
        $top = max($chaotic, $lawful, $neutral);
        $tieCount = (int) ($chaotic === $top) + (int) ($lawful === $top) + (int) ($neutral === $top);

        if ($tieCount === 1 && $top >= $threshold) {
            $dominant = match (true) {
                $chaotic === $top => 'chaotic',
                $lawful === $top => 'lawful',
                default => 'neutral',
            };
        }

        $label = null;
        $description = null;
        $voiceSignature = null;
        foreach ($alignmentLabels as $row) {
            $mapsTo = strtolower((string) ($row['maps_to_internal'] ?? ''));
            if ($mapsTo === $dominant) {
                $label = (string) ($row['label'] ?? '');
                $description = (string) ($row['narrative_consequences'] ?? ($row['description'] ?? ''));
                $voiceSignature = (string) ($row['voice_signature'] ?? '');
                break;
            }
        }

        if ($label === null || $label === '') {
            return 'The player\'s alignment has tilted, but no story-native label is configured for "' . $dominant
                . '". Narrate without surfacing the alignment, but lean toward this tendency in tone.';
        }

        $lines = [
            'STORY-NATIVE ALIGNMENT TILT: "' . $label . '" (hidden — never surface the literal label or any RPG terminology).',
        ];
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
     * Tiered persistent state loader. Section 17's tail of the cached
     * prompt is filled with this block.
     *
     *   Tier 1 — always loaded:   spine of the world state (location, items,
     *                             conditions, knowledge, notes, player style).
     *   Tier 2 — scene-connected: object_states, relationship_updates,
     *                             world_flags, unresolved_promises, emotional
     *                             ledger entries that touch the current scene.
     *   Tier 3 — climactic load:  full unfiltered persistent state — only
     *                             injected on the turn AFTER a Choice #3 / #4
     *                             style climactic moment, or every 4 turns
     *                             as a safety net.
     *
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
        $lines = ['PERSISTENT STATE — TIER 1 (always loaded):'];
        $location = trim((string) ($w['location'] ?? ''));
        $lines[] = 'Location: ' . ($location !== '' ? $location : '(unset)');
        $lines[] = 'Items: ' . $this->joinList((array) ($w['items'] ?? []));
        $lines[] = 'Conditions: ' . $this->joinList((array) ($w['conditions'] ?? []));
        $lines[] = 'Knowledge: ' . $this->joinList((array) ($w['knowledge'] ?? []));
        $lines[] = 'Notes: ' . $this->joinList((array) ($w['notes'] ?? []));
        $lines[] = 'Player style: ' . $this->joinList((array) ($w['player_style'] ?? []));

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

        $object = $this->filterByHaystack((array) ($w['object_states'] ?? []), $haystack);
        $relationships = $this->filterByHaystack((array) ($w['relationship_updates'] ?? []), $haystack);
        $flags = $this->filterByHaystack((array) ($w['world_flags'] ?? []), $haystack);
        $promises = (array) ($w['unresolved_promises'] ?? []);

        $ledger = (array) ($w['emotional_ledger'] ?? []);
        $recentLedger = array_slice($ledger, -6);
        $ledgerLines = array_map(
            static fn (array $entry) => sprintf('%s: %s', $entry['category'] ?? 'note', $entry['entry'] ?? ''),
            array_filter($recentLedger, static fn ($e) => is_array($e)),
        );

        $lines = ['PERSISTENT STATE — TIER 2 (scene-connected):'];
        $lines[] = 'Object states: ' . $this->joinList($object);
        $lines[] = 'Relationships: ' . $this->joinList($relationships);
        $lines[] = 'World flags: ' . $this->joinList($flags);
        $lines[] = 'Unresolved promises: ' . $this->joinList($promises);
        $lines[] = 'Recent emotional ledger: ' . $this->joinList($ledgerLines);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $w
     */
    private function formatTier3(array $w): string
    {
        $ledger = (array) ($w['emotional_ledger'] ?? []);
        $allLedger = array_map(
            static fn (array $entry) => sprintf('%s: %s', $entry['category'] ?? 'note', $entry['entry'] ?? ''),
            array_filter($ledger, static fn ($e) => is_array($e)),
        );

        $lines = ['PERSISTENT STATE — TIER 3 (climactic load — previous turn resolved a moral-weight or session-end choice):'];
        $lines[] = 'All object states: ' . $this->joinList((array) ($w['object_states'] ?? []));
        $lines[] = 'All relationships: ' . $this->joinList((array) ($w['relationship_updates'] ?? []));
        $lines[] = 'All world flags: ' . $this->joinList((array) ($w['world_flags'] ?? []));
        $lines[] = 'Full emotional ledger: ' . $this->joinList($allLedger);

        return implode("\n", $lines);
    }

    /**
     * Keep only entries that mention something present in the current scene
     * (matched against event titles + content lowercased). Falls back to
     * returning the entire list when no entries match — better to include too
     * much state than to elide it when the scene context is thin.
     *
     * @param  array<int, string>  $entries
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
     * Single chaos narration turn.
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

        $lastException = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response   = $request->asStructured();
                $structured = $response->structured ?? [];

                $alignment = (array) ($structured['alignment_tally_delta'] ?? []);

                return [
                    'response'              => (string) ($structured['response'] ?? ''),
                    'choices'               => (array)  ($structured['choices'] ?? []),
                    'session_complete'      => (bool)   ($structured['session_complete'] ?? false),
                    'state_delta'           => (array)  ($structured['state_delta'] ?? []),
                    'alignment_tally_delta' => [
                        'chaotic' => (int) ($alignment['chaotic'] ?? 0),
                        'lawful'  => (int) ($alignment['lawful'] ?? 0),
                        'neutral' => (int) ($alignment['neutral'] ?? 0),
                    ],
                    'is_climactic_choice'   => (bool)   ($structured['is_climactic_choice'] ?? false),
                    'defining_choice_id'    => (string) ($structured['defining_choice_id'] ?? ''),
                    'defining_choice_line'  => (string) ($structured['defining_choice_line'] ?? ''),
                    'symbolic_memory_update' => (string) ($structured['symbolic_memory_update'] ?? ''),
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

        if ($config['provider'] === 'anthropic') {
            return ['use_tool_calling' => true];
        }

        return [];
    }

    // -------------------------------------------------------------------------
    // World state, history, formatting helpers
    // -------------------------------------------------------------------------

    /**
     * Merge a state_delta (the new literary-memory shape) into the persisted
     * world_state. Each scalar/list field replaces its previous value when
     * the agent emits one; emotional_ledger_entries are APPENDED to the
     * existing ledger rather than overwriting.
     *
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

        foreach (['conditions', 'items', 'object_states', 'relationship_updates', 'world_flags',
                  'knowledge', 'notes', 'player_style', 'unresolved_promises'] as $key) {
            $value = $delta[$key] ?? null;
            $merged[$key] = is_array($value)
                ? array_values(array_filter(array_map('strval', $value), static fn ($v) => $v !== ''))
                : (array) ($previous[$key] ?? []);
        }

        $previousLedger = (array) ($previous['emotional_ledger'] ?? []);
        $newLedger = [];
        foreach ((array) ($delta['emotional_ledger_entries'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $category = trim((string) ($entry['category'] ?? ''));
            $text = trim((string) ($entry['entry'] ?? ''));
            if ($category === '' || $text === '') {
                continue;
            }
            $newLedger[] = ['category' => $category, 'entry' => $text];
        }

        $merged['emotional_ledger'] = array_values(array_merge($previousLedger, $newLedger));

        return $merged;
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
    private function emptyWorldState(): array
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
    private function emptyAlignmentScaffold(): array
    {
        return ['chaotic' => 0, 'lawful' => 0, 'neutral' => 0];
    }

    /**
     * @param  array{chaotic:int, lawful:int, neutral:int}  $previous
     * @param  array{chaotic:int, lawful:int, neutral:int}  $delta
     * @return array{chaotic:int, lawful:int, neutral:int}
     */
    private function mergeAlignmentDelta(array $previous, array $delta): array
    {
        return [
            'chaotic' => max(0, (int) ($previous['chaotic'] ?? 0) + (int) ($delta['chaotic'] ?? 0)),
            'lawful'  => max(0, (int) ($previous['lawful'] ?? 0) + (int) ($delta['lawful'] ?? 0)),
            'neutral' => max(0, (int) ($previous['neutral'] ?? 0) + (int) ($delta['neutral'] ?? 0)),
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
     * @param  array{response:string, choices:array<int,string>, session_complete:bool, state_delta:array<string,mixed>, alignment_tally_delta:array{chaotic:int,lawful:int,neutral:int}, is_climactic_choice:bool, defining_choice_id:string, defining_choice_line:string, symbolic_memory_update:string, session_memory_update:string}  $result
     * @param  array{slug:string, title:string, protagonist:string, tagline:string, tts_voice_id:string|null}  $storyConfig
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
            'session_id'             => $chaosSession->id,
            'story_slug'             => $storyConfig['slug'],
            'story_title'            => $storyConfig['title'],
            'protagonist'            => $storyConfig['protagonist'],
            'session_number'         => $sessionNumber,
            'total_sessions'         => $totalSessions,
            'has_next_session'       => $hasNext,
            'response'               => $result['response'],
            'choices'                => $result['choices'],
            'session_complete'       => $result['session_complete'],
            'world_state'            => $chaosSession->world_state,
            'symbolic_memory'        => $chaosSession->symbolic_memory,
            'defining_choice_id'     => $chaosSession->defining_choice_id,
            'defining_choice_line'   => $chaosSession->defining_choice_line,
            'is_climactic_choice'    => (bool) $chaosSession->is_climactic_choice,
            'session_memory_update'  => $result['session_memory_update'],
            'symbolic_memory_update' => $result['symbolic_memory_update'],
            'turn_count'             => $chaosSession->turn_count,
        ];
    }
}
