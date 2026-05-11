<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $story_id
 * @property int $chapter_id
 * @property int|null $session_number
 * @property string $type  combine|split|reorder|edit
 * @property array|null $source_event_ids
 * @property string|null $rewritten_content
 * @property string|null $derived_objectives
 * @property array|null $derived_attributes
 * @property string|null $beat_type
 * @property bool $requires_choice
 * @property array|null $canonical_anchors
 * @property array|null $split_parts
 * @property array|null $event_order
 * @property array|null $previous_state
 * @property array|null $adaptation_patch
 * @property string $status  draft|ai_written|writer_approved|activated
 * @property Carbon|null $activated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class WriterLabDraft extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'source_event_ids'  => 'array',
            'derived_attributes' => 'array',
            'canonical_anchors' => 'array',
            'split_parts'       => 'array',
            'event_order'       => 'array',
            'previous_state'    => 'array',
            'adaptation_patch'  => 'array',
            'requires_choice'   => 'boolean',
            'activated_at'      => 'datetime',
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

    /** @param Builder<WriterLabDraft> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotIn('status', ['activated']);
    }
}
