<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Models\Story;
use App\Support\Adaptation\IpTrimmingIntegrity;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Re-runs IP trimming for every chapter, merges into ip_trimming without
 * restarting FormatDetection / Voice Lock, then optionally re-runs the
 * per-session adaptation chain for sessions mapped to previously missing chapters.
 */
final class RepairIpTrimmingJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @param  array<int, int>|null  $rerunSessionNumbers  null = auto-detect from missing chapters
     */
    public function __construct(
        private Story $story,
        private ?array $rerunSessionNumbers = null,
    ) {
        $this->onQueue('adaptation');
    }

    /** @throws Throwable */
    public function handle(): void
    {
        $adaptation = $this->story->adaptation;

        if (! $adaptation) {
            throw new \RuntimeException("Story {$this->story->id} has no adaptation row.");
        }

        $missingChapters = IpTrimmingIntegrity::chaptersMissingFromIpTrimming($this->story);
        $rerunSessionNumbers = $this->rerunSessionNumbers
            ?? IpTrimmingIntegrity::sessionNumbersForChapters($this->story, $missingChapters);

        if ($missingChapters->isEmpty()) {
            Log::info('ip_trimming.repair_skipped: already complete', ['story_id' => $this->story->id]);
            AdaptationStatusReconciliationJob::dispatch($this->story)->onQueue('adaptation');

            return;
        }

        Log::info('ip_trimming.repair_start', [
            'story_id' => $this->story->id,
            'missing_chapters' => $missingChapters->pluck('position')->all(),
            'rerun_sessions' => $rerunSessionNumbers,
        ]);

        $chapters = $this->story->chapters()->orderBy('position')->get();
        $storyId = $this->story->id;

        Bus::batch(
            $chapters->map(fn ($chapter) => new IpTrimmingChapterJob($this->story, $chapter))->all()
        )->onQueue('adaptation')
            ->finally(function () use ($storyId, $rerunSessionNumbers): void {
                $story = Story::findOrFail($storyId);

                IpTrimmingMergeJob::dispatch(
                    $story,
                    continuePipeline: false,
                    rerunSessionNumbers: $rerunSessionNumbers,
                )->onQueue('adaptation');
            })
            ->dispatch();
    }
}
