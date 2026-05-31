<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $game_id
 * @property int|null $session_number
 * @property string $response
 * @property array $choices
 * @property string|null $prompt
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Game $game
 */
final class Prompt extends Model
{
    use HasUlids;

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'choices' => 'array',
    ];

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
