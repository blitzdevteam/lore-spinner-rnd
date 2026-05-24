<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $story_id
 * @property int|null $user_id
 * @property int $story_session_number
 * @property string $model
 * @property array<int, array{role: string, text: string}>|null $conversation_history
 * @property array<string, mixed>|null $world_state
 * @property array{chaotic:int, lawful:int, neutral:int}|null $alignment_scaffold
 * @property string|null $session_memory
 * @property string|null $symbolic_memory
 * @property string|null $defining_choice_id
 * @property string|null $defining_choice_line
 * @property bool $is_climactic_choice
 * @property bool $session_complete
 * @property int $turn_count
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Story|null $story
 * @property-read User|null $user
 */
final class ChaosSession extends Model
{
    use HasUlids;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'conversation_history' => 'json',
            'world_state'          => 'json',
            'alignment_scaffold'   => 'json',
            'session_complete'     => 'boolean',
            'is_climactic_choice'  => 'boolean',
            'turn_count'           => 'integer',
            'story_session_number' => 'integer',
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
}
