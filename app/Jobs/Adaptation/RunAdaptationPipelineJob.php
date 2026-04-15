<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Models\Story;
use App\Models\StoryAdaptation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

final class RunAdaptationPipelineJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        private Story $story,
        private bool $force = false,
    ) {
        $this->onQueue('adaptation');
    }

    public function handle(): void
    {
        $adaptation = $this->story->adaptation;

        if ($adaptation && $adaptation->adaptation_status === AdaptationStatusEnum::COMPLETED) {
            if (! $this->force) {
                return;
            }
        }

        if ($adaptation) {
            DB::transaction(function () use ($adaptation): void {
                $adaptation->sessionAdaptations()->delete();

                // Story already has events() defined as hasManyThrough(Event::class, Chapter::class)
                // in the existing codebase — no new relationship needed.
                $this->story->events()->update(['session_number' => null]);

                $adaptation->update([
                    'format_detection' => null,
                    'ip_audit' => null,
                    'story_session_map' => null,
                    'adaptation_status' => AdaptationStatusEnum::PENDING,
                ]);
            });
        } else {
            $adaptation = StoryAdaptation::create([
                'story_id' => $this->story->id,
                'adaptation_status' => AdaptationStatusEnum::PENDING,
            ]);
        }

        Bus::chain([
            new FormatDetectionJob($this->story),
            new IpAuditJob($this->story),
            new StorySessionMapJob($this->story),
        ])->onQueue('adaptation')->dispatch();
    }
}
