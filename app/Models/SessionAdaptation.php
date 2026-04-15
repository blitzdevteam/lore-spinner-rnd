<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $story_adaptation_id
 * @property int $session_number
 * @property SessionAdaptationStatusEnum $session_status
 * @property array|null $entry_point_diagnosis
 * @property array|null $session_architecture
 * @property array|null $session_choice_design
 * @property array|null $choice_consequence_map
 * @property array|null $session_close_design
 * @property array|null $editorial_verification
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StoryAdaptation $storyAdaptation
 */
final class SessionAdaptation extends Model
{
    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'session_status' => SessionAdaptationStatusEnum::class,
        'entry_point_diagnosis' => 'json',
        'session_architecture' => 'json',
        'session_choice_design' => 'json',
        'choice_consequence_map' => 'json',
        'session_close_design' => 'json',
        'editorial_verification' => 'json',
    ];

    /**
     * @return BelongsTo<StoryAdaptation, $this>
     */
    public function storyAdaptation(): BelongsTo
    {
        return $this->belongsTo(StoryAdaptation::class);
    }
}
