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

final class GenerateMissingImagesCommand extends Command
{
    protected $signature = 'images:generate-missing
                            {--stories : Only generate story covers}
                            {--banners : Only generate story banners}
                            {--chapters : Only generate chapter covers}
                            {--creators : Only generate creator avatars}';

    protected $description = 'Dispatch AI image generation jobs for stories, chapters, and creators that are missing images.';

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

        $this->info('All image generation jobs dispatched. Run your queue worker to process them.');

        return self::SUCCESS;
    }

    private function generateStoryCovers(): void
    {
        $stories = Story::query()
            ->whereDoesntHave('media', function ($query) {
                $query->where('collection_name', 'cover');
            })
            ->get();

        $this->info("Found {$stories->count()} stories without covers.");

        $stories->each(function (Story $story): void {
            StoryCoverGeneratorJob::dispatch($story);
            $this->line("  → Dispatched cover generation for: {$story->title}");
        });
    }

    private function generateStoryBanners(): void
    {
        $stories = Story::query()
            ->whereDoesntHave('media', function ($query) {
                $query->where('collection_name', 'banner');
            })
            ->get();

        $this->info("Found {$stories->count()} stories without banners.");

        $stories->each(function (Story $story): void {
            StoryBannerGeneratorJob::dispatch($story);
            $this->line("  → Dispatched banner generation for: {$story->title}");
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

        $this->info("Found {$chapters->count()} chapters without covers.");

        $chapters->each(function (Chapter $chapter): void {
            ChapterCoverGeneratorJob::dispatch($chapter);
            $this->line("  → Dispatched cover generation for: Ch{$chapter->position} — {$chapter->title} ({$chapter->story?->title})");
        });
    }

    private function generateCreatorAvatars(): void
    {
        $creators = Creator::query()
            ->whereDoesntHave('media', function ($query) {
                $query->where('collection_name', 'avatar');
            })
            ->get();

        $this->info("Found {$creators->count()} creators without avatars.");

        $creators->each(function (Creator $creator): void {
            CreatorAvatarGeneratorJob::dispatch($creator);
            $this->line("  → Dispatched avatar generation for: {$creator->full_name}");
        });
    }
}
