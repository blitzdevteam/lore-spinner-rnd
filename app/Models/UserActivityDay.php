<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $activity_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
final class UserActivityDay extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    #[\Override]
    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
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
     * Record today as an active day for the given user.
     * Safe to call multiple times — idempotent via unique constraint.
     */
    public static function record(int $userId): void
    {
        $today = now()->toDateString();

        DB::table('user_activity_days')->upsert(
            [
                'user_id'       => $userId,
                'activity_date' => $today,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            uniqueBy: ['user_id', 'activity_date'],
            update: ['updated_at' => now()],
        );
    }
}
