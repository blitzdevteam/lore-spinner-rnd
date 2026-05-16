<?php

declare(strict_types=1);

namespace App\Http\Controllers\ChaosMode;

use App\Ai\Agents\Chaos\ChaosNarrationAgent;
use App\Ai\Agents\Chaos\ChaosNarrationAgentClaudeOpus;
use App\Ai\Agents\Chaos\ChaosNarrationAgentClaudeSonnet;
use App\Ai\Agents\Chaos\ChaosNarrationAgentGpt41;
use App\Http\Controllers\Controller;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Response;
use Throwable;

final class ChaosModeController extends Controller
{
    /**
     * Chaos Mode entry page.
     * Loads Alice story metadata from DB if it exists (for title/banner).
     * Does not require auth — chaos mode is an open experimental runtime.
     */
    public function show(): Response
    {
        $story = Story::query()
            ->where('slug', 'alices-adventures-in-wonderland')
            ->first(['id', 'title', 'slug']);

        return inertia('ChaosMode', [
            'storyTitle' => $story?->title ?? "Alice's Adventures in Wonderland",
            'storyExists' => $story !== null,
        ]);
    }

    /**
     * Generate the opening narration — Alice arrives at the bottom of the rabbit-hole.
     * The client sends the chosen model. No prior conversation exists yet.
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'model' => ['nullable', 'string', 'in:gpt-5.2,gpt-4.1,claude-opus-4-5,claude-sonnet-4-5'],
        ]);

        $model = $request->string('model', 'gpt-5.2')->toString();

        $systemPrompt = $this->renderSystemPrompt(worldState: [], currentScene: null);

        try {
            $result = $this->callAgent(
                model: $model,
                systemPrompt: $systemPrompt,
                conversationHistory: [],
                playerAction: null,
            );

            Log::channel('narration')->info('chaos.start', [
                'model' => $model,
                'response_bytes' => strlen((string) ($result['response'] ?? '')),
            ]);

            return response()->json($this->formatResult($result));
        } catch (Throwable $e) {
            Log::channel('narration')->error('chaos.start_failed', [
                'model' => $model,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Wonderland is currently unavailable. Please try again.'], 500);
        }
    }

    /**
     * Process a player turn.
     * The client owns all state (conversation_history, world_state) and sends it each turn.
     * The server is stateless — it renders context, calls the agent, and returns the result.
     */
    public function turn(Request $request): JsonResponse
    {
        $request->validate([
            'player_action'         => ['required', 'string', 'min:1', 'max:500'],
            'model'                 => ['nullable', 'string', 'in:gpt-5.2,gpt-4.1,claude-opus-4-5,claude-sonnet-4-5'],
            'conversation_history'  => ['nullable', 'array', 'max:20'],
            'world_state'           => ['nullable', 'array'],
        ]);

        $playerAction        = $request->string('player_action')->toString();
        $model               = $request->string('model', 'gpt-5.2')->toString();
        $conversationHistory = $request->array('conversation_history', []);
        $worldState          = $request->array('world_state', []);

        // Keep history to the last 12 turns to stay within context budget
        if (count($conversationHistory) > 12) {
            $conversationHistory = array_slice($conversationHistory, -12);
        }

        $systemPrompt = $this->renderSystemPrompt(
            worldState: $worldState,
            currentScene: null,
        );

        try {
            $result = $this->callAgent(
                model: $model,
                systemPrompt: $systemPrompt,
                conversationHistory: $conversationHistory,
                playerAction: $playerAction,
            );

            Log::channel('narration')->info('chaos.turn', [
                'model' => $model,
                'history_turns' => count($conversationHistory),
                'advance_scene' => $result['advance_scene'] ?? false,
                'scene_note' => $result['scene_note'] ?? '',
                'player_action_first_80' => mb_substr($playerAction, 0, 80),
            ]);

            return response()->json($this->formatResult($result));
        } catch (Throwable $e) {
            Log::channel('narration')->error('chaos.turn_failed', [
                'model' => $model,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'player_action' => mb_substr($playerAction, 0, 80),
            ]);

            return response()->json(['error' => 'Wonderland hiccuped. Your action was preserved — please try again.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function renderSystemPrompt(array $worldState, ?string $currentScene): string
    {
        return view('ai.agents.chaos.system-prompt', [
            'worldState'   => $worldState,
            'currentScene' => $currentScene,
        ])->render();
    }

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
            'gpt-4.1'          => ChaosNarrationAgentGpt41::make(customInstructions: $systemPrompt),
            'claude-opus-4-5'  => ChaosNarrationAgentClaudeOpus::make(customInstructions: $systemPrompt),
            'claude-sonnet-4-5' => ChaosNarrationAgentClaudeSonnet::make(customInstructions: $systemPrompt),
            default            => ChaosNarrationAgent::make(customInstructions: $systemPrompt),
        };

        /** @var \Laravel\Ai\Responses\StructuredAgentResponse $response */
        $response = $agent->prompt($promptText);

        return [
            'response'     => (string) ($response['response'] ?? ''),
            'choices'      => (array)  ($response['choices'] ?? []),
            'advance_scene' => (bool)  ($response['advance_scene'] ?? false),
            'scene_note'   => (string) ($response['scene_note'] ?? 'Chapter I — Down the Rabbit-Hole'),
            'world_update' => (array)  ($response['world_update'] ?? []),
        ];
    }

    private function formatResult(array $result): array
    {
        return [
            'response'     => $result['response'],
            'choices'      => $result['choices'],
            'advance_scene' => $result['advance_scene'],
            'scene_note'   => $result['scene_note'],
            'world_update' => $result['world_update'],
        ];
    }
}
