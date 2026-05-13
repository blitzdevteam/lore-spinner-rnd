<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Re-attach physical media assets (covers, banners, chapter covers) from git-committed
 * files in database/stories/ to their story/chapter records.
 *
 * Safe to run after every deploy — skips anything already attached.
 *
 * Asset naming conventions (all relative to database/stories/):
 *   Story cover:   covers/{slug}.png
 *   Story banner:  RnD/Banner/{slug}.png
 *   Chapter cover: covers/chapters/{slug}-ch{position}.png
 *
 * Usage: php artisan images:attach-assets [--story=slug-or-id] [--force]
 */
final class AttachStoryAssetsCommand extends Command
{
    protected $signature = 'images:attach-assets
                            {--story= : Limit to a specific story slug or ID}
                            {--force : Re-attach even if media already exists}';

    protected $description = 'Re-attach committed image assets (covers, banners, chapter covers) to stories';

    public function handle(): int
    {
        $stories = $this->resolveStories();

        if ($stories->isEmpty()) {
            $this->error('No stories found.');

            return self::FAILURE;
        }

        foreach ($stories as $story) {
            $this->info("── {$story->title} (slug: {$story->slug})");
            $this->attachStoryCover($story);
            $this->attachStoryBanner($story);
            $this->attachChapterCovers($story);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function resolveStories()
    {
        $filter = $this->option('story');

        if (! $filter) {
            return Story::with('chapters')->orderBy('id')->get();
        }

        $story = is_numeric($filter)
            ? Story::with('chapters')->find((int) $filter)
            : Story::with('chapters')->where('slug', $filter)->first();

        return $story ? collect([$story]) : collect();
    }

    private function attachStoryCover(Story $story): void
    {
        if (! $this->option('force') && $story->getFirstMedia('cover')) {
            $this->line("  cover:  already attached — skipped");

            return;
        }

        $path = database_path("stories/covers/{$story->slug}.png");

        if (! File::exists($path)) {
            $this->warn("  cover:  MISSING at covers/{$story->slug}.png");

            return;
        }

        $story->clearMediaCollection('cover');
        $story->addMedia($path)
            ->preservingOriginal()
            ->usingFileName("cover-{$story->slug}.png")
            ->toMediaCollection('cover', 'public');

        $this->line("  cover:  attached ✓");
    }

    private function attachStoryBanner(Story $story): void
    {
        if (! $this->option('force') && $story->getFirstMedia('banner')) {
            $this->line("  banner: already attached — skipped");

            return;
        }

        $path = database_path("stories/RnD/Banner/{$story->slug}.png");

        if (! File::exists($path)) {
            $this->warn("  banner: MISSING at RnD/Banner/{$story->slug}.png");

            return;
        }

        $story->clearMediaCollection('banner');
        $story->addMedia($path)
            ->preservingOriginal()
            ->usingFileName("banner-{$story->slug}.png")
            ->toMediaCollection('banner', 'public');

        $this->line("  banner: attached ✓");
    }

    private function attachChapterCovers(Story $story): void
    {
        foreach ($story->chapters()->orderBy('position')->get() as $chapter) {
            if (! $this->option('force') && $chapter->getFirstMedia('cover')) {
                $this->line("  Ch{$chapter->position}: already attached — skipped");

                continue;
            }

            $path = database_path("stories/covers/chapters/{$story->slug}-ch{$chapter->position}.png");

            if (! File::exists($path)) {
                $this->warn("  Ch{$chapter->position}: MISSING at covers/chapters/{$story->slug}-ch{$chapter->position}.png");

                continue;
            }

            $chapter->clearMediaCollection('cover');
            $chapter->addMedia($path)
                ->preservingOriginal()
                ->usingFileName("chapter-cover-{$chapter->id}.png")
                ->toMediaCollection('cover', 'public');

            $this->line("  Ch{$chapter->position}: attached ✓");
        }
    }
}
