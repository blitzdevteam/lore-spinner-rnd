<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $story_id
 * @property int $user_id
 * @property int $current_event_id
 * @property int|null $current_session_number
 * @property string|null $current_beat_type
 * @property array|null $branching_choices_taken
 * @property array|null $tracked_dimensions
 * @property array|null $branch_resolution_log
 * @property array|null $world_state
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Story $story
 * @property-read User $user
 * @property-read Event $currentEvent
 * @property-read Collection<int, Prompt> $prompts
 */
final class Game extends Model
{
    use HasUlids;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'branching_choices_taken' => 'json',
            'tracked_dimensions' => 'json',
            'branch_resolution_log' => 'json',
            'world_state' => 'json',
        ];
    }

    /**
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function currentEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'current_event_id');
    }

    /**
     * Intentionally returns an UNORDERED HasMany.
     *
     * Earlier this relation chained ->oldest() so iteration was always
     * chronological. That collided with call sites doing ->latest()->first()
     * and ->latest()->limit(6): Laravel stacks ORDER BY clauses, so the
     * resulting SQL was ORDER BY created_at ASC, created_at DESC and the DB
     * honored the first clause — silently returning the oldest row from
     * latest() and the oldest 6 rows from the conversation-history query.
     *
     * Call sites that need a specific order MUST add it explicitly
     * (oldest() for UI rendering, latest() for newest-first reads).
     *
     * @return HasMany<Prompt, $this>
     */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }
}
