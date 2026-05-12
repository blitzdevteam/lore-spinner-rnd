<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Collaboration note left by one writer for another, scoped to a chapter.
 *
 * @property int $id
 * @property int $story_id
 * @property int $chapter_id
 * @property int|null $event_id
 * @property int|null $writer_id
 * @property string $author_name
 * @property string $body
 * @property bool $is_resolved
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class WriterLabNote extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_resolved' => 'boolean',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
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

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Writer, $this> */
    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class);
    }
}
