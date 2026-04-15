<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;

/**
 * Destructively removes all stories and their related data.
 *
 * Deletes: games, prompts, comments, chapters, events,
 * story/session adaptations, media collections.
 *
 * Usage:
 *   php artisan stories:wipe              (interactive confirmation)
 *   php artisan stories:wipe --force      (skip confirmation)
 */
final class WipeStoriesCommand extends Command
{
    protected $signature = 'stories:wipe {--force : Skip confirmation prompt}';

    protected $description = 'Remove ALL stories and their related data (games, chapters, events, adaptations, media)';

    public function handle(): int
    {
        $stories = Story::all();

        if ($stories->isEmpty()) {
            $this->info('No stories to remove.');

            return self::SUCCESS;
        }

        $this->warn("This will permanently delete {$stories->count()} story(ies) and ALL related data:");
        foreach ($stories as $story) {
            $this->line("  - {$story->title} (ID: {$story->id})");
        }

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to wipe everything?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        foreach ($stories as $story) {
            foreach ($story->games as $game) {
                $game->prompts()->delete();
            }
            $story->games()->delete();
            $story->comments()->delete();

            if ($story->adaptation) {
                $story->adaptation->sessionAdaptations()->delete();
                $story->adaptation->delete();
            }

            foreach ($story->chapters as $chapter) {
                $chapter->events()->delete();
                $chapter->clearMediaCollection('cover');
            }
            $story->chapters()->delete();

            $story->clearMediaCollection('script');
            $story->clearMediaCollection('cover');
            $story->clearMediaCollection('gallery');
            $story->delete();

            $this->info("Removed: {$story->title}");
        }

        $this->info('All stories wiped.');

        return self::SUCCESS;
    }
}
