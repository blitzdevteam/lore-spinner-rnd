<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable snapshot created before each activate. Used for restore.
 *
 * Two snapshot shapes are supported:
 *  - snapshot_kind = 'session'  → legacy: snapshot_events scoped to one draft,
 *                                  snapshot_adaptation is a single session adaptation.
 *  - snapshot_kind = 'chapter'  → full chapter capture: snapshot_events covers EVERY
 *                                  event in the chapter, snapshot_adaptations is the
 *                                  array of every SessionAdaptation row whose events
 *                                  live in the chapter.
 *
 * @property int $id
 * @property int $story_id
 * @property int|null $chapter_id
 * @property string $snapshot_kind
 * @property int $session_number
 * @property int $version_number
 * @property array $snapshot_events
 * @property array|null $snapshot_adaptation
 * @property array|null $snapshot_adaptations
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
            'snapshot_events'      => 'array',
            'snapshot_adaptation'  => 'array',
            'snapshot_adaptations' => 'array',
            'is_active'            => 'boolean',
            'created_at'           => 'datetime',
        ];
    }

    /** @return BelongsTo<Story, $this> */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
