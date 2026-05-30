<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Chapter\ChapterCoverGeneratorJob;
use App\Jobs\Creator\CreatorAvatarGeneratorJob;
use App\Jobs\Story\StoryBannerGeneratorJob;
use App\Jobs\Story\StoryCoverGeneratorJob;
use App\Models\Chapter;
use App\Models\Creator;
use App\Models\Story;
use Illuminate\Console\Command;
use Throwable;

final class GenerateMissingImagesCommand extends Command
{
    protected $signature = 'images:generate-missing
                            {--stories : Only generate story covers}
                            {--banners : Only generate story banners}
                            {--chapters : Only generate chapter covers}
                            {--creators : Only generate creator avatars}';

    protected $description = 'Synchronously generate AI images for stories, chapters, and creators that are missing images.';

    private int $totalCreated = 0;

    private int $totalFailed = 0;

    public function handle(): int
    {
        $generateAll = ! $this->option('stories') && ! $this->option('banners') && ! $this->option('chapters') && ! $this->option('creators');

        if ($generateAll || $this->option('stories')) {
            $this->generateStoryCovers();
        }

        if ($generateAll || $this->option('banners')) {
            $this->generateStoryBanners();
        }

        if ($generateAll || $this->option('chapters')) {
            $this->generateChapterCovers();
        }

        if ($generateAll || $this->option('creators')) {
            $this->generateCreatorAvatars();
        }

        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("Done. Created: {$this->totalCreated} | Failed: {$this->totalFailed}");

        return self::SUCCESS;
    }

    private function runJob(object $job, string $collection, object $model, string $label): void
    {
        $hadMedia = (bool) $model->getFirstMedia($collection);

        try {
            $job->handle();
            $model->refresh();
            $hasMedia = (bool) $model->getFirstMedia($collection);

            if (! $hadMedia && $hasMedia) {
                $this->line("  <fg=green>✔</> Created — {$label}");
                $this->totalCreated++;
            } else {
                $this->line("  <fg=yellow>–</> Skipped (already exists) — {$label}");
            }
        } catch (Throwable $e) {
            $this->line("  <fg=red>✘</> Failed — {$label}");
            $this->line("      <fg=red>{$e->getMessage()}</>");
            $this->totalFailed++;
        }
    }

    private function generateStoryCovers(): void
    {
        $stories = Story::query()
            ->whereDoesntHave('media', function ($query) {
                $query->where('collection_name', 'cover');
            })
            ->get();

        $this->newLine();
        $this->line("<fg=cyan;options=bold>Story Covers</> ({$stories->count()} missing)");

        $stories->each(function (Story $story): void {
            $this->runJob(
                new StoryCoverGeneratorJob($story),
                'cover',
                $story,
                $story->title,
            );
        });
    }

    private function generateStoryBanners(): void
    {
        $stories = Story::query()
            ->whereDoesntHave('media', function ($query) {
                $query->where('collection_name', 'banner');
            })
            ->get();

        $this->newLine();
        $this->line("<fg=cyan;options=bold>Story Banners</> ({$stories->count()} missing)");

        $stories->each(function (Story $story): void {
            $this->runJob(
                new StoryBannerGeneratorJob($story),
                'banner',
                $story,
                $story->title,
            );
        });
    }

    private function generateChapterCovers(): void
    {
        $chapters = Chapter::query()
            ->with('story')
            ->whereDoesntHave('media', function ($query) {
                $query->where('collection_name', 'cover');
            })
            ->get();

        $this->newLine();
        $this->line("<fg=cyan;options=bold>Chapter Covers</> ({$chapters->count()} missing)");

        $chapters->each(function (Chapter $chapter): void {
            $this->runJob(
                new ChapterCoverGeneratorJob($chapter),
                'cover',
                $chapter,
                "Ch{$chapter->position} — {$chapter->title} ({$chapter->story?->title})",
            );
        });
    }

    private function generateCreatorAvatars(): void
    {
        $creators = Creator::query()
            ->whereDoesntHave('media', function ($query) {
                $query->where('collection_name', 'avatar');
            })
            ->get();

        $this->newLine();
        $this->line("<fg=cyan;options=bold>Creator Avatars</> ({$creators->count()} missing)");

        $creators->each(function (Creator $creator): void {
            $this->runJob(
                new CreatorAvatarGeneratorJob($creator),
                'avatar',
                $creator,
                $creator->full_name,
            );
        });
    }
}
