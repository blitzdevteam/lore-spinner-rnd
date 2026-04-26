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

        $systemPrompt = $this->renderSystemPrompt(
            story: $game->story,
            currentEvent: $currentEvent,
            turnCount: $turnCount,
        );

        $aiResult = $this->generateNarration(
            systemPrompt: $systemPrompt,
            conversationHistory: $conversationHistory,
            playerAction: $isContinue ? 'Continue forward' : $prompt,
        );

        $shouldAdvance = $aiResult['advance_event'];

        if (! $shouldAdvance && $turnCount >= 5) {
            $shouldAdvance = true;
        }

        if ($shouldAdvance) {
            $nextEvent = $this->findNextEvent($currentEvent, $game->story_id);

            if ($nextEvent) {
                $gameUpdate = ['current_event_id' => $nextEvent->id];

                if ($nextEvent->session_number !== null
                    && $nextEvent->session_number !== $currentEvent->session_number) {
                    $nextEvent = $this->applySessionTransitionCut($nextEvent, $game);
                    $gameUpdate['current_event_id'] = $nextEvent->id;
                    $gameUpdate['current_session_number'] = $nextEvent->session_number;
                }

                $game->update($gameUpdate);
            }
        }

        $resolvedEventId = $shouldAdvance && isset($nextEvent)
            ? $nextEvent->id
            : $currentEvent->id;

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
     */
    private function renderSystemPrompt(
        \App\Models\Story $story,
        Event $currentEvent,
        int $turnCount = 0,
    ): string {
        $storyData = $story->system_prompt ?? [];

        $sessionAdaptation = null;

        if ($currentEvent->session_number !== null) {
            $sessionAdaptation = SessionAdaptation::query()
                ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $story->id))
                ->where('session_number', $currentEvent->session_number)
                ->first();

            if ($sessionAdaptation?->session_status !== SessionAdaptationStatusEnum::COMPLETED) {
                $sessionAdaptation = null;
            }
        }

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
        ])->render();
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
     * @return array{response: string, choices: string[], advance_event: bool}
     */
    private function generateNarration(
        string $systemPrompt,
        array $conversationHistory,
        string $playerAction,
    ): array {
        try {
            /** @var StructuredAgentResponse $response */
            $response = NarrationAgent::make(customInstructions: $systemPrompt)
                ->prompt(
                    view('ai.agents.narration.prompt', [
                        'conversationHistory' => $conversationHistory,
                        'playerAction' => $playerAction,
                    ])->render()
                );

            return [
                'response' => $response['response'] ?? '',
                'choices' => $response['choices'] ?? ['Continue forward', 'Investigate your surroundings', 'Take a moment to reflect'],
                'advance_event' => (bool) ($response['advance_event'] ?? false),
            ];
        } catch (Throwable) {
            return [
                'response' => '<p>The scene unfolds before you...</p>',
                'choices' => ['Continue forward', 'Investigate your surroundings', 'Take a moment to reflect'],
                'advance_event' => true,
            ];
        }
    }
}
