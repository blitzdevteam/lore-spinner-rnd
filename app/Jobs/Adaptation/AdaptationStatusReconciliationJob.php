<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class AdaptationStatusReconciliationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        private Story $story,
    ) {
        $this->onQueue('adaptation');
    }

    public function handle(): void
    {
        $adaptation = $this->story->adaptation;

        if (! $adaptation) {
            return;
        }

        $sessions = $adaptation->sessionAdaptations;

        if ($sessions->isEmpty()) {
            return;
        }

        $completed = $sessions->where('session_status', SessionAdaptationStatusEnum::COMPLETED)->count();
        $failed = $sessions->where('session_status', SessionAdaptationStatusEnum::FAILED)->count();
        $total = $sessions->count();

        $status = match (true) {
            $completed === $total => AdaptationStatusEnum::COMPLETED,
            $failed === $total => AdaptationStatusEnum::FAILED,
            default => AdaptationStatusEnum::PARTIAL_COMPLETION,
        };

        $adaptation->update(['adaptation_status' => $status]);
    }
}
