<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Attach outro poster images from public/images/outro/ to story media collections.
 *
 * File naming convention:
 *   public/images/outro/{story-slug}.webp  (also accepts .jpg / .png)
 *
 * Safe to run repeatedly — skips stories that already have an outro poster unless
 * --force is passed.
 *
 * Usage:
 *   php artisan images:attach-outro-posters
 *   php artisan images:attach-outro-posters --story=the-adventure-of-the-speckled-band
 *   php artisan images:attach-outro-posters --force
 */
final class AttachOutroPostersCommand extends Command
{
    private const OUTRO_DIR = 'images/outro';

    private const EXTENSIONS = ['webp', 'jpg', 'jpeg', 'png'];

    protected $signature = 'images:attach-outro-posters
                            {--story= : Limit to a specific story slug or ID}
                            {--force : Re-attach even if an outro poster already exists}';

    protected $description = 'Attach outro poster images from public/images/outro/ to their stories';

    public function handle(): int
    {
        $stories = $this->resolveStories();

        if ($stories->isEmpty()) {
            $this->error('No stories found.');

            return self::FAILURE;
        }

        $attached = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($stories as $story) {
            $this->line("── <fg=cyan>{$story->title}</> (slug: {$story->slug})");

            if (! $this->option('force') && $story->getFirstMedia('outro')) {
                $this->line('   outro: already attached — skipped');
                $skipped++;

                continue;
            }

            $path = $this->findPosterFile($story->slug);

            if (! $path) {
                $this->warn('   outro: no file found in public/'.self::OUTRO_DIR."/{$story->slug}.{webp|jpg|png}");
                $missing++;

                continue;
            }

            if ($this->option('force')) {
                $story->clearMediaCollection('outro');
            }

            $story
                ->addMedia($path)
                ->preservingOriginal()
                ->toMediaCollection('outro');

            $this->info('   outro: attached ✓  ('.basename($path).')');
            $attached++;
        }

        $this->newLine();
        $this->info("Done — attached: {$attached}, skipped: {$skipped}, missing: {$missing}");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Story>
     */
    private function resolveStories(): \Illuminate\Support\Collection
    {
        $filter = $this->option('story');

        if (! $filter) {
            return Story::orderBy('id')->get();
        }

        $story = is_numeric($filter)
            ? Story::find((int) $filter)
            : Story::where('slug', $filter)->first();

        return $story ? collect([$story]) : collect();
    }

    private function findPosterFile(string $slug): ?string
    {
        foreach (self::EXTENSIONS as $ext) {
            $path = public_path(self::OUTRO_DIR."/{$slug}.{$ext}");

            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
