<?php

declare(strict_types=1);

namespace App\Actions\Game;

use App\Models\Game;
use App\Models\Story;
use App\Models\User;
use App\Services\ChaosEngineService;

final readonly class CreateGameAction
{
    public function handle(User $user, Story $story): Game
    {
        return $user->games()->create([
            'story_id'               => $story->id,
            'current_session_number' => 1,
            'model'                  => ChaosEngineService::DEFAULT_MODEL,
            'alignment_scaffold'     => ['chaotic' => 0, 'lawful' => 0, 'neutral' => 0],
        ]);
    }
}
