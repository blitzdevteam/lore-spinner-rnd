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

        // Pipeline Upgrade V2: a session is only fully COMPLETED when its
        // RuntimeNarratorAssemblyJob has cached `runtime_narrator_prompt`. If
        // every session_status is COMPLETED but any one of them is missing
        // the assembled prompt, treat the run as PARTIAL_COMPLETION so the
        // editor can re-run the assembly job manually.
        $missingAssembledPrompt = $sessions
            ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
            ->filter(fn ($s) => $s->runtime_narrator_prompt === null || $s->runtime_narrator_prompt === '')
            ->count();

        $status = match (true) {
            $completed === $total && $missingAssembledPrompt === 0 => AdaptationStatusEnum::COMPLETED,
            $failed === $total => AdaptationStatusEnum::FAILED,
            default => AdaptationStatusEnum::PARTIAL_COMPLETION,
        };

        $adaptation->update(['adaptation_status' => $status]);
    }
}
