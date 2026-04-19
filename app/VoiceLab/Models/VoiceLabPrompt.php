<?php

declare(strict_types=1);

namespace App\VoiceLab\Models;

use App\VoiceLab\Enums\VoiceLabRoleEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $session_id
 * @property VoiceLabRoleEnum $role
 * @property string $text
 * @property array|null $choices
 * @property int|null $audio_ms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read VoiceLabSession $session
 */
final class VoiceLabPrompt extends Model
{
    use HasUlids;

    protected $table = 'voice_lab_prompts';

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'role' => VoiceLabRoleEnum::class,
            'choices' => 'array',
        ];
    }

    /**
     * @return BelongsTo<VoiceLabSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(VoiceLabSession::class, 'session_id');
    }
}
