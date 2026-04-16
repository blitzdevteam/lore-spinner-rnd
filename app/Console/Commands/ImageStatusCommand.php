<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Chapter;
use App\Models\Creator;
use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ImageStatusCommand extends Command
{
    protected $signature = 'images:status';

    protected $description = 'Show the status of all generated images — media records, files on disk, and what is missing.';

    public function handle(): int
    {
        $this->newLine();
        $this->info('=== IMAGE STATUS REPORT ===');
        $this->newLine();

        $this->reportStories();
        $this->reportChapters();
        $this->reportCreators();
        $this->reportMediaSummary();
        $this->reportDiskConfig();

        return self::SUCCESS;
    }

    private function reportStories(): void
    {
        $stories = Story::with('media')->get();

        $this->info("STORIES ({$stories->count()} total)");
        $this->line(str_repeat('─', 60));

        foreach ($stories as $story) {
            $this->line("  [{$story->id}] {$story->title}");

            $coverMedia = $story->getFirstMedia('cover');
            $bannerMedia = $story->getFirstMedia('banner');

            $this->reportMedia('    Cover ', $coverMedia);
            $this->reportMedia('    Banner', $bannerMedia);
        }

        $this->newLine();
    }

    private function reportChapters(): void
    {
        $chapters = Chapter::with(['media', 'story'])->orderBy('story_id')->orderBy('position')->get();

        $this->info("CHAPTERS ({$chapters->count()} total)");
        $this->line(str_repeat('─', 60));

        foreach ($chapters as $chapter) {
            $storyTitle = $chapter->story?->title ?? '?';
            $this->line("  [{$chapter->id}] Ch{$chapter->position} — {$chapter->title} ({$storyTitle})");

            $coverMedia = $chapter->getFirstMedia('cover');
            $this->reportMedia('    Cover ', $coverMedia);
        }

        $this->newLine();
    }

    private function reportCreators(): void
    {
        $creators = Creator::with('media')->get();

        $this->info("CREATORS ({$creators->count()} total)");
        $this->line(str_repeat('─', 60));

        foreach ($creators as $creator) {
            $this->line("  [{$creator->id}] {$creator->full_name}");

            $avatarMedia = $creator->getFirstMedia('avatar');
            $this->reportMedia('    Avatar', $avatarMedia);
        }

        $this->newLine();
    }

    private function reportMedia(string $label, ?Media $media): void
    {
        if (! $media) {
            $this->warn("{$label}: ✗ No media record");

            return;
        }

        $disk = $media->disk;
        $path = $media->getPathRelativeToRoot();
        $fileExists = Storage::disk($disk)->exists($path);
        $size = $fileExists ? $this->formatBytes(Storage::disk($disk)->size($path)) : '—';

        if ($fileExists) {
            $this->line("{$label}: ✓ disk={$disk} size={$size} file={$path}");
        } else {
            $this->error("{$label}: ⚠ GHOST — media record exists but file missing on disk={$disk} file={$path}");
        }
    }

    private function reportMediaSummary(): void
    {
        $total = Media::count();
        $byCollection = Media::query()
            ->selectRaw('collection_name, disk, count(*) as cnt')
            ->groupBy('collection_name', 'disk')
            ->get();

        $this->info('MEDIA SUMMARY');
        $this->line(str_repeat('─', 60));
        $this->line("  Total media records: {$total}");

        foreach ($byCollection as $row) {
            $this->line("  {$row->collection_name} on [{$row->disk}]: {$row->cnt}");
        }

        $ghosts = 0;
        Media::each(function (Media $media) use (&$ghosts) {
            if (! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
                $ghosts++;
            }
        });

        if ($ghosts > 0) {
            $this->error("  Ghost records (file missing): {$ghosts}");
        } else {
            $this->line('  Ghost records (file missing): 0');
        }

        $this->newLine();
    }

    private function reportDiskConfig(): void
    {
        $this->info('DISK CONFIG');
        $this->line(str_repeat('─', 60));

        $publicDisk = config('filesystems.disks.public');
        $driver = $publicDisk['driver'] ?? 'unknown';
        $this->line("  public disk driver: {$driver}");

        if ($driver === 's3') {
            $bucket = $publicDisk['bucket'] ?? '?';
            $endpoint = $publicDisk['endpoint'] ?? '?';
            $this->line("  bucket: {$bucket}");
            $this->line("  endpoint: {$endpoint}");
        } elseif ($driver === 'local') {
            $root = $publicDisk['root'] ?? '?';
            $this->line("  root: {$root}");
            $this->warn('  ⚠ Local driver — files will NOT persist across deploys on Laravel Cloud!');
        }

        $this->newLine();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).'MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).'KB';
        }

        return $bytes.'B';
    }
}
