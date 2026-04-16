<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\Game\CreateGameAction;
use App\Actions\Game\ProcessGameTurnAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Game\StoreGameRequest;
use App\Models\Game;
use App\Models\Story;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

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

    public function begin(Game $game, ProcessGameTurnAction $processTurn): RedirectResponse
    {
        if ($game->prompts()->exists()) {
            return to_route('user.games.show', $game);
        }

        $firstNarration = $processTurn->generateFirstNarration($game);

        $game->prompts()->create([
            'event_id' => $game->current_event_id,
            'response' => $firstNarration['response'],
            'choices' => $firstNarration['choices'],
        ]);

        return to_route('user.games.show', $game);
    }
}
