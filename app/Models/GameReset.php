<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable event log — one row per player-initiated story reset.
 *
 * had_prior_completion = true means the player had already completed the story
 * (games.completed_at was set) before choosing to replay it.
 *
 * Replays = GameReset::where('had_prior_completion', true)
 *
 * @property int $id
 * @property string $game_id
 * @property int $user_id
 * @property int $story_id
 * @property bool $had_prior_completion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Game $game
 * @property-read User $user
 * @property-read Story $story
 */
final class GameReset extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    #[\Override]
    protected function casts(): array
    {
        return [
            'had_prior_completion' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
