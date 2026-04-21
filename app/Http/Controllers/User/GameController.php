<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\Game\CreateGameAction;
use App\Ai\Agents\NarrationAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Game\StoreGameRequest;
use App\Models\Event;
use App\Models\Game;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

final class GameController extends Controller
{
    public function index(): RedirectResponse
    {
        return to_route('index');
    }

    public function show(Game $game): Response
    {
        $game->load([
            'story',
            'currentEvent.chapter',
            'prompts:id,game_id,event_id,response,choices,prompt',
            'prompts.event',
        ]);

        return inertia('User/Games/Show', [
            'game' => $game->toResource()
        ]);
    }

    public function store(
        #[CurrentUser] User $user,
        StoreGameRequest $request,
        CreateGameAction $createGameAction
    ): RedirectResponse {
        $story = Story::find($request->string('story_id')->toString());

        $existingGame = $user->games()->whereBelongsTo($story)->first();

        if ($existingGame) {
            return to_route('user.games.show', $existingGame);
        }

        $game = $createGameAction->handle($user, $story);

        return to_route('user.games.show', $game);
    }

    public function reset(Game $game, CreateGameAction $createGameAction): RedirectResponse
    {
        $story = $game->story;
        $firstChapter = $story->chapters()->orderBy('position')->first();
        $firstEvent = $firstChapter?->events()->orderBy('position')->first();

        if ($firstEvent === null) {
            return to_route('user.games.show', $game);
        }

        $startEvent = $createGameAction->resolveStartEvent($story, $firstEvent);

        $game->prompts()->delete();
        $game->update([
            'current_event_id' => $startEvent->id,
            'current_session_number' => null,
            'current_beat_type' => null,
            'branching_choices_taken' => null,
            'tracked_dimensions' => null,
            'branch_resolution_log' => null,
        ]);

        return to_route('user.games.show', $game);
    }

    public function begin(Game $game): RedirectResponse
    {
        if ($game->prompts()->exists()) {
            return to_route('user.games.show', $game);
        }

        $story = $game->story;
        $firstEvent = $game->currentEvent;
        $aiResult = $this->generateFirstNarration($story, $firstEvent);

        $game->prompts()->create([
            'event_id' => $firstEvent->id,
            'response' => $aiResult['response'],
            'choices' => $aiResult['choices'],
        ]);

        return to_route('user.games.show', $game);
    }

    /**
     * @return array{response: string, choices: string[]}
     */
    private function generateFirstNarration(Story $story, Event $firstEvent): array
    {
        $storyData = $story->system_prompt ?? [];

        $nextEvents = Event::query()
            ->where('chapter_id', $firstEvent->chapter_id)
            ->where('position', '>', $firstEvent->position)
            ->orderBy('position')
            ->take(2)
            ->get()
            ->map(fn (Event $event): array => [
                'position' => $event->position,
                'title' => $event->title,
            ])
            ->all();

        $sessionAdaptation = null;

        if ($firstEvent->session_number !== null) {
            $sessionAdaptation = SessionAdaptation::query()
                ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $story->id))
                ->where('session_number', $firstEvent->session_number)
                ->first();

            if ($sessionAdaptation?->session_status !== SessionAdaptationStatusEnum::COMPLETED) {
                $sessionAdaptation = null;
            }
        }

        $systemPrompt = view('ai.agents.narration.system-prompt', [
            'characterName' => $storyData['character_name'] ?? null,
            'worldRules' => $storyData['world_rules'] ?? [],
            'toneAndStyle' => $storyData['tone_and_style'] ?? null,
            'previousEvents' => [],
            'currentEvent' => [
                'position' => $firstEvent->position,
                'title' => $firstEvent->title,
                'content' => $firstEvent->content,
                'objectives' => $firstEvent->objectives,
                'attributes' => $firstEvent->attributes,
            ],
            'nextEvents' => $nextEvents,
            'sessionAdaptation' => $sessionAdaptation,
            'isSessionStart' => true,
        ])->render();

        try {
            /** @var StructuredAgentResponse $response */
            $response = NarrationAgent::make(customInstructions: $systemPrompt)
                ->prompt(
                    view('ai.agents.narration.prompt', [
                        'conversationHistory' => [],
                        'playerAction' => '',
                    ])->render()
                );

            return [
                'response' => $response['response'] ?? '<p>The scene unfolds before you...</p>',
                'choices' => $response['choices'] ?? ['Begin your adventure'],
            ];
        } catch (Throwable) {
            return [
                'response' => '<p>' . nl2br(e($firstEvent->content)) . '</p>',
                'choices' => ['Begin your adventure'],
            ];
        }
    }
}
