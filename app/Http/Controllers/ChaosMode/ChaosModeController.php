<?php

declare(strict_types=1);

namespace App\Http\Controllers\ChaosMode;

use App\Ai\Agents\Chaos\ChaosNarrationAgent;
use App\Ai\Agents\Chaos\ChaosNarrationAgentClaudeOpus;
use App\Ai\Agents\Chaos\ChaosNarrationAgentClaudeSonnet;
use App\Ai\Agents\Chaos\ChaosNarrationAgentGpt41;
use App\Ai\Agents\Chaos\ChaosNarrationAgentGpt54;
use App\Ai\Agents\Chaos\ChaosNarrationAgentGpt55;
use App\Http\Controllers\Controller;
use App\Models\ChaosSession;
use App\Models\Event;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Response;
use Throwable;

/**
 * Chaos Mode runtime.
 *
 * Architecture:
 *   - The AI controls narration, pacing, and movement INSIDE the active session.
 *   - The runtime controls only which session is loaded, persistent state,
 *     conversation log, and the technical boundary between sessions.
 *   - There is no advance_event, no scene_note, no suggested_playhead. The AI
 *     is given the full session script and the adaptation spine, and trusted
 *     to move through it naturally.
 *
 * For the demo, Session 1 is hardcoded:
 *   - Event range 1–23 (from adapptation-third-try.json session_allocation[0].event_range)
 *   - Session adaptation packet is read from the adaptation export JSON.
 *   - Events are read directly from the DB. No fallback.
 */
final class ChaosModeController extends Controller
{
    /**
     * Alice's story slug. Hardcoded for the demo — chaos mode is Alice-only for now.
     */
    private const STORY_SLUG = 'alices-adventures-in-wonderland';

    /**
     * Session 1 event range — driven by adapptation-third-try.json
     * "session_allocation[0].event_range": "1-23".
     */
    private const SESSION_1_EVENT_START = 1;
    private const SESSION_1_EVENT_END   = 23;

    /**
     * Path (relative to base_path) to the adaptation export used as the
     * Session Packet source for the demo.
     */
    private const ADAPTATION_EXPORT_PATH = 'database/exports/adapptation-third-try.json';

    /**
     * Allowed model slugs. Keep the same list on both endpoints.
     */
    private const ALLOWED_MODELS = [
        'gpt-5.5',
        'gpt-5.4',
        'gpt-5.2',
        'gpt-4.1',
        'claude-opus-4-7',
        'claude-sonnet-4-6',
    ];

    /**
     * Chaos Mode entry page.
     */
    public function show(): Response
    {
        $story = Story::query()
            ->where('slug', self::STORY_SLUG)
            ->first(['id', 'title', 'slug']);

        return inertia('ChaosMode', [
            'storyTitle' => $story?->title ?? "Alice's Adventures in Wonderland",
            'storyExists' => $story !== null,
        ]);
    }

    /**
     * Generate the opening narration.
     *
     * The cold open is rendered as $currentScene exactly once, here. On
     * subsequent turns it is omitted — the conversation history carries it.
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'model' => ['nullable', 'string', 'in:' . implode(',', self::ALLOWED_MODELS)],
        ]);

        $model       = $request->string('model', 'gpt-5.2')->toString();
        $session1    = $this->loadCurrentSessionContext();
        $story       = Story::query()->where('slug', self::STORY_SLUG)->first(['id']);

        $chaosSession = ChaosSession::create([
            'story_id'             => $story?->id,
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
            worldState:   $this->emptyWorldState(),
            currentScene: $session1['cold_open'] ?? null,
            session1:     $session1,
        );

        try {
            $result = $this->callAgent(
                model:               $model,
                systemPrompt:        $systemPrompt,
                conversationHistory: [],
                playerAction:        null,
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
                'session_id'      => $chaosSession->id,
                'model'           => $model,
                'response_bytes'  => strlen($result['response']),
                'session_complete' => $result['session_complete'],
            ]);

            return response()->json($this->formatResult($chaosSession, $result));
        } catch (Throwable $e) {
            Log::channel('narration')->error('chaos.start_failed', [
                'session_id' => $chaosSession->id,
                'model'      => $model,
                'exception'  => $e::class,
                'message'    => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Wonderland is currently unavailable. Please try again.'], 500);
        }
    }

    /**
     * Process a player turn.
     *
     * Runtime responsibilities here are deliberately small:
     *   - Load the ChaosSession by id
     *   - Pass the full session packet + script every turn (static for Session 1)
     *   - Merge the AI's state_delta into world_state
     *   - Append the narrator turn + session_memory_update
     *   - Note when session_complete flips true (runtime would load Session 2)
     *
     * The AI owns playhead and pacing inside the session.
     */
    public function turn(Request $request): JsonResponse
    {
        $request->validate([
            'session_id'    => ['required', 'string', 'ulid'],
            'player_action' => ['required', 'string', 'min:1', 'max:500'],
            'model'         => ['nullable', 'string', 'in:' . implode(',', self::ALLOWED_MODELS)],
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

        $session1    = $this->loadCurrentSessionContext();
        $worldState  = $chaosSession->world_state ?? $this->emptyWorldState();
        $history     = (array) ($chaosSession->conversation_history ?? []);

        // Keep last 12 turns for context budget. Persisted history is full; sent history is windowed.
        $sentHistory = array_slice($history, -12);

        $systemPrompt = $this->renderSystemPrompt(
            worldState:   $worldState,
            currentScene: null, // cold open is in history; never re-anchor it
            session1:     $session1,
        );

        try {
            $result = $this->callAgent(
                model:               $model,
                systemPrompt:        $systemPrompt,
                conversationHistory: $sentHistory,
                playerAction:        $playerAction,
            );

            $worldState = $this->mergeStateDelta($worldState, $result['state_delta']);
            $history    = $this->appendPlayerTurn($history, $playerAction);
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
                'session_id'        => $chaosSession->id,
                'model'             => $model,
                'turn'              => $chaosSession->turn_count,
                'session_complete'  => $result['session_complete'],
                'player_action'     => mb_substr($playerAction, 0, 80),
                'memory_update'     => mb_substr($result['session_memory_update'], 0, 120),
            ]);

            return response()->json($this->formatResult($chaosSession, $result));
        } catch (Throwable $e) {
            Log::channel('narration')->error('chaos.turn_failed', [
                'session_id'    => $chaosSession->id,
                'model'         => $model,
                'exception'     => $e::class,
                'message'       => $e->getMessage(),
                'player_action' => mb_substr($playerAction, 0, 80),
            ]);

            return response()->json(['error' => 'Wonderland hiccuped. Your action was preserved — please try again.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Load the dramatic spine + full source script for the currently active
     * session. Demo: hardcoded to Session 1 of Alice. No fallback.
     *
     * @return array{
     *     cold_open: string,
     *     emotional_promise: string,
     *     dramatic_question: string,
     *     emotional_register: string,
     *     beat_map: array<int, array<string, mixed>>,
     *     authored_choices: array<int, array<string, mixed>>,
     *     session_destination: string,
     *     next_session_seed: string,
     *     full_session_events: array<int, array{position:int, title:string, content:string, objectives:?string}>,
     * }
     */
    private function loadCurrentSessionContext(): array
    {
        $adaptation = $this->loadAdaptationExport();
        $session1   = $this->extractSession1($adaptation);

        $events = Event::query()
            ->whereHas('chapter.story', fn ($q) => $q->where('slug', self::STORY_SLUG))
            ->whereBetween('position', [self::SESSION_1_EVENT_START, self::SESSION_1_EVENT_END])
            ->orderBy('position')
            ->get(['position', 'title', 'content', 'objectives'])
            ->map(fn (Event $e) => [
                'position'   => (int) $e->position,
                'title'      => (string) $e->title,
                'content'    => (string) $e->content,
                'objectives' => $e->objectives,
            ])
            ->all();

        $entry         = $session1['entry_point_diagnosis'] ?? [];
        $allocation    = $adaptation['story_wide']['story_session_map']['session_allocation'][0] ?? [];
        $architecture  = $session1['session_architecture'] ?? [];
        $choiceDesign  = $session1['session_choice_design'] ?? [];
        $closeDesign   = $session1['session_close_design'] ?? [];

        $authoredChoices = array_values(array_filter([
            $choiceDesign['branching_choice_1'] ?? null,
            $choiceDesign['branching_choice_2'] ?? null,
            $choiceDesign['branching_choice_3'] ?? null,
        ]));

        $sessionDestination = $closeDesign['hook_transition']
            ?? $closeDesign['session_end_choice']['choice_question']
            ?? '';

        $nextSessionSeed = $architecture['next_session_awareness']['seed_for_next_session'] ?? '';

        return [
            'cold_open'           => (string) ($entry['cold_open'] ?? ''),
            'emotional_promise'   => (string) ($entry['emotional_promise'] ?? ''),
            'dramatic_question'   => (string) ($allocation['primary_dramatic_question'] ?? ''),
            'emotional_register'  => (string) ($allocation['emotional_register'] ?? ''),
            'beat_map'            => (array)  ($architecture['beat_map'] ?? []),
            'authored_choices'    => $authoredChoices,
            'session_destination' => (string) $sessionDestination,
            'next_session_seed'   => (string) $nextSessionSeed,
            'full_session_events' => $events,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadAdaptationExport(): array
    {
        $path = base_path(self::ADAPTATION_EXPORT_PATH);

        if (! File::exists($path)) {
            Log::channel('narration')->warning('chaos.adaptation_export_missing', ['path' => $path]);
            return [];
        }

        $decoded = json_decode(File::get($path), associative: true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $adaptation
     * @return array<string, mixed>
     */
    private function extractSession1(array $adaptation): array
    {
        foreach ((array) ($adaptation['sessions'] ?? []) as $sessionRow) {
            if (($sessionRow['session_number'] ?? null) === 1) {
                return (array) $sessionRow;
            }
        }
        return [];
    }

    /**
     * @param  array<string, mixed>  $worldState
     * @param  array<string, mixed>|null  $session1
     */
    private function renderSystemPrompt(array $worldState, ?string $currentScene, ?array $session1): string
    {
        return view('ai.agents.chaos.system-prompt', [
            'worldState'   => $worldState,
            'currentScene' => $currentScene,
            'session1'     => $session1,
        ])->render();
    }

    /**
     * @param  array<int, array{role:string, text:string}>  $conversationHistory
     * @return array{response:string, choices:array<int,string>, session_complete:bool, state_delta:array<string,mixed>, session_memory_update:string}
     */
    private function callAgent(
        string $model,
        string $systemPrompt,
        array $conversationHistory,
        ?string $playerAction,
    ): array {
        $promptText = view('ai.agents.chaos.turn-prompt', [
            'conversationHistory' => $conversationHistory,
            'playerAction'        => $playerAction,
        ])->render();

        $agent = match ($model) {
            'gpt-5.5'           => ChaosNarrationAgentGpt55::make(customInstructions: $systemPrompt),
            'gpt-5.4'           => ChaosNarrationAgentGpt54::make(customInstructions: $systemPrompt),
            'gpt-4.1'           => ChaosNarrationAgentGpt41::make(customInstructions: $systemPrompt),
            'claude-opus-4-7'   => ChaosNarrationAgentClaudeOpus::make(customInstructions: $systemPrompt),
            'claude-sonnet-4-6' => ChaosNarrationAgentClaudeSonnet::make(customInstructions: $systemPrompt),
            default             => ChaosNarrationAgent::make(customInstructions: $systemPrompt),
        };

        /** @var \Laravel\Ai\Responses\StructuredAgentResponse $response */
        $response = $agent->prompt($promptText);

        return [
            'response'              => (string) ($response['response'] ?? ''),
            'choices'               => (array)  ($response['choices'] ?? []),
            'session_complete'      => (bool)   ($response['session_complete'] ?? false),
            'state_delta'           => (array)  ($response['state_delta'] ?? []),
            'session_memory_update' => (string) ($response['session_memory_update'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    private function mergeStateDelta(array $previous, array $delta): array
    {
        $merged = $this->emptyWorldState();

        // location: take new if non-empty, otherwise keep old
        $merged['location'] = trim((string) ($delta['location'] ?? '')) !== ''
            ? (string) $delta['location']
            : (string) ($previous['location'] ?? '');

        // The AI returns the COMPLETE current list for these fields each turn,
        // so we trust the delta. If the AI returns an empty array we treat it
        // as "this field is now empty" — which matches the schema description.
        foreach (['conditions', 'items', 'relationships', 'knowledge', 'notes'] as $key) {
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
    private function appendPlayerTurn(array $history, string $action): array
    {
        $history[] = ['role' => 'player', 'text' => $action];
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
     * @return array<string, mixed>
     */
    private function formatResult(ChaosSession $chaosSession, array $result): array
    {
        return [
            'session_id'            => $chaosSession->id,
            'response'              => $result['response'],
            'choices'               => $result['choices'],
            'session_complete'      => $result['session_complete'],
            'world_state'           => $chaosSession->world_state,
            'session_memory_update' => $result['session_memory_update'],
            'turn_count'            => $chaosSession->turn_count,
        ];
    }
}
