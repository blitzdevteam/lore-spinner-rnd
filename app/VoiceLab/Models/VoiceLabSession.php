<?php

declare(strict_types=1);

namespace App\VoiceLab\Models;

use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property int|null $story_id
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Story|null $story
 * @property-read Collection<int, VoiceLabPrompt> $prompts
 */
final class VoiceLabSession extends Model
{
    use HasUlids;

    protected $table = 'voice_lab_sessions';

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'ended_at' => 'datetime',
        ];
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

    /**
     * @return HasMany<VoiceLabPrompt, $this>
     */
    public function prompts(): HasMany
    {
        return $this->hasMany(VoiceLabPrompt::class, 'session_id')->oldest();
    }
}
