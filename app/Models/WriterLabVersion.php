<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable snapshot created before each activate. Used for restore.
 *
 * @property int $id
 * @property int $story_id
 * @property int $session_number
 * @property int $version_number
 * @property array $snapshot_events
 * @property array|null $snapshot_adaptation
 * @property bool $is_active
 * @property string|null $note
 * @property Carbon $created_at
 */
final class WriterLabVersion extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'snapshot_events'    => 'array',
            'snapshot_adaptation' => 'array',
            'is_active'          => 'boolean',
            'created_at'         => 'datetime',
        ];
    }

    /** @return BelongsTo<Story, $this> */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
