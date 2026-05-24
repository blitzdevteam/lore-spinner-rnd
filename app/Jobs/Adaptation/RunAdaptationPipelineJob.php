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
                    'ip_trimming' => null,
                    'format_detection' => null,
                    'ip_audit' => null,
                    'voice_profile' => null,
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

        // Pipeline Upgrade V2 chain order:
        //   IpTrimming (pre-Phase-1)
        //     -> FormatDetection
        //     -> IpAudit
        //     -> VoiceLock (between Phase 1 and Phase 2 — consumes FULL original source)
        //     -> StorySessionMap (Phase 2 — kicks off the per-session batch internally)
        Bus::chain([
            new IpTrimmingJob($this->story),
            new FormatDetectionJob($this->story),
            new IpAuditJob($this->story),
            new VoiceLockJob($this->story),
            new StorySessionMapJob($this->story),
        ])->onQueue('adaptation')->dispatch();
    }
}
