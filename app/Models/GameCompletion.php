<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable completion record — one row per completed story cycle.
 *
 * This is the analytics source of truth for story completions.
 * games.completed_at holds the latest/mutable state; this table preserves
 * every completion across all story cycles for a game.
 *
 * Written by GameController@nextSession() when the final session completes.
 * Upserted on (game_id, story_cycle_number) for idempotency.
 *
 * Analytics use:
 *   Story Completions = GameCompletion::where('completed_at', '>=', Analytics::startDate())
 *
 * @property int $id
 * @property string $game_id
 * @property int $user_id
 * @property int $story_id
 * @property int $story_cycle_number
 * @property Carbon $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Game $game
 * @property-read User $user
 * @property-read Story $story
 */
final class GameCompletion extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    #[\Override]
    protected function casts(): array
    {
        return [
            'story_cycle_number' => 'integer',
            'completed_at'       => 'datetime',
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
