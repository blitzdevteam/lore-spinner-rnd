<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Game;

use App\Actions\Game\ProcessGameTurnAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Game\Prompt\StorePromptRequest;
use App\Models\Game;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class PromptController extends Controller
{
    public function store(
        #[CurrentUser] User $user,
        Game $game,
        StorePromptRequest $request,
        ProcessGameTurnAction $processTurn,
    ): RedirectResponse {
        $prompt = $request->string('prompt')->toString();

        $processTurn->handle($game, $prompt);

        return back();
    }
}
