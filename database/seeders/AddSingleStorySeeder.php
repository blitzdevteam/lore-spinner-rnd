<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Chapter\ChapterStatusEnum;
use App\Enums\Story\StoryRatingEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Jobs\Chapter\ChapterExtractorJob;
use App\Jobs\Event\EventExtractorJob;
use App\Jobs\Story\StoryOpeningGeneratorJob;
use App\Jobs\Story\SystemPromptGeneratorJob;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Creator;
use App\Models\Story;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Seed a single story from scratch.
 *
 * - Creates the creator if missing
 * - Converts PDF → TXT if no .txt exists
 * - Runs the full extraction pipeline (chapters → events → system prompt → opening)
 * - Attaches covers from database/stories/covers/ if present
 *
 * To wipe existing stories first, run: php artisan stories:wipe
 *
 * Usage: php artisan db:seed --class=AddSingleStorySeeder --force
 */
final class AddSingleStorySeeder extends Seeder
{
    private const int MAX_RETRIES = 3;

    private const int RETRY_DELAY_SECONDS = 10;

    public function run(): void
    {
        $previousQueue = config('queue.default');
        config(['queue.default' => 'sync']);

        try {
            $config = $this->getStoryConfig();
            $this->processStory($config);
        } finally {
            config(['queue.default' => $previousQueue]);
        }
    }

    private function processStory(array $config): void
    {
        $scriptPath = database_path('stories/' . $config['script']);

        if (! File::exists($scriptPath) && isset($config['source_pdf'])) {
            $pdfPath = database_path('stories/' . $config['source_pdf']);

            if (File::exists($pdfPath)) {
                $this->command->info('Converting PDF → TXT...');
                $this->convertPdf($pdfPath, $scriptPath);
            }
        }

        if (! File::exists($scriptPath)) {
            $this->command->error("Script not found: {$config['script']}");

            return;
        }

        $creator = $this->ensureCreator($config);
        $category = Category::firstOrCreate(['title' => $config['category']]);

        $this->command->info("Creating: {$config['title']}");

        $story = Story::create([
            'category_id' => $category->id,
            'creator_id' => $creator->id,
            'title' => $config['title'],
            'slug' => Str::slug($config['title']),
            'teaser' => $config['teaser'],
            'opening' => $config['opening'] ?? null,
            'status' => StoryStatusEnum::AWAITING_EXTRACTING_CHAPTERS_REQUEST->value,
            'rating' => $config['rating'],
            'published_at' => now(),
        ]);

        $story->addMedia($scriptPath)
            ->preservingOriginal()
            ->toMediaCollection('script');

        $this->command->info('Extracting chapters...');
        $this->withRetry(fn () => ChapterExtractorJob::dispatchSync($story->fresh()));

        $story->refresh();
        $chapterCount = $story->chapters()->count();
        $this->command->info("{$chapterCount} chapters extracted.");

        foreach ($story->chapters()->orderBy('position')->get() as $chapter) {
            $this->command->info("Extracting events: {$chapter->title}");
            $this->withRetry(function () use ($chapter): void {
                $chapter->events()->delete();
                EventExtractorJob::dispatchSync($chapter->fresh());
            });
            $chapter->refresh();

            if ($chapter->events()->count() === 0 && $chapter->status !== ChapterStatusEnum::READY_TO_PLAY) {
                $chapter->update(['status' => ChapterStatusEnum::READY_TO_PLAY]);
            }

            $this->command->info("  {$chapter->events()->count()} events.");
        }

        $this->command->info('Generating system prompt...');
        $this->withRetry(fn () => SystemPromptGeneratorJob::dispatchSync($story));

        if ($story->opening) {
            $this->command->info('Opening provided — skipping AI generation.');
        } else {
            $this->command->info('Generating cinematic opening...');
            $this->withRetry(fn () => StoryOpeningGeneratorJob::dispatchSync($story->fresh()));
        }

        $story->update(['status' => StoryStatusEnum::PUBLISHED->value]);
        $this->command->info('Published!');

        $this->attachMissingImages($story, $config['slug']);
    }

    private function ensureCreator(array $config): Creator
    {
        $data = $config['creator'];
        $creator = null;

        Creator::withoutEvents(function () use ($data, &$creator): void {
            $creator = Creator::firstOrCreate(
                ['email' => $data['email']],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'username' => $data['username'],
                    'password' => 'password',
                    'bio' => $data['bio'],
                ]
            );
        });

        DB::table('creators')
            ->where('id', $creator->id)
            ->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'bio' => $data['bio'],
            ]);

        $avatarPath = database_path('stories/avatars/' . $data['avatar']);

        if (File::exists($avatarPath) && ! $creator->getFirstMedia('avatar')) {
            $creator->addMedia($avatarPath)
                ->preservingOriginal()
                ->usingFileName('avatar-' . $creator->id . '.' . pathinfo($avatarPath, PATHINFO_EXTENSION))
                ->toMediaCollection('avatar', 'public');
        }

        $this->command->info("Creator ensured: {$data['first_name']} {$data['last_name']}");

        return $creator;
    }

    private function attachMissingImages(Story $story, string $slug): void
    {
        if (! $story->getFirstMedia('cover')) {
            $coverFile = database_path("stories/covers/{$slug}.png");

            if (File::exists($coverFile)) {
                $story->addMedia($coverFile)
                    ->preservingOriginal()
                    ->usingFileName('cover-' . $slug . '.png')
                    ->toMediaCollection('cover', 'public');
                $this->command->info('Story cover attached.');
            } else {
                $this->command->warn("No story cover found at: covers/{$slug}.png");
            }
        } else {
            $this->command->info('Story cover already exists — skipped.');
        }

        foreach ($story->chapters()->orderBy('position')->get() as $chapter) {
            if ($chapter->getFirstMedia('cover')) {
                continue;
            }

            $chapterFile = database_path("stories/covers/chapters/{$slug}-ch{$chapter->position}.png");

            if (File::exists($chapterFile)) {
                $chapter->addMedia($chapterFile)
                    ->preservingOriginal()
                    ->usingFileName("chapter-cover-{$chapter->id}.png")
                    ->toMediaCollection('cover', 'public');
                $this->command->info("  Chapter {$chapter->position} cover attached.");
            }
        }
    }

    private function convertPdf(string $pdfPath, string $txtPath): void
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($pdfPath);
        $text = $pdf->getText();

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^\d+\.?\s*$/m', '', $text);
        $text = preg_replace("/\n{4,}/", "\n\n\n", $text);
        $text = implode("\n", array_map('rtrim', explode("\n", $text)));
        $text = mb_trim($text) . "\n";

        File::put($txtPath, $text);

        $this->command->info('Saved: ' . basename($txtPath) . ' (' . mb_strlen($text) . ' bytes)');
    }

    private function withRetry(callable $callback): void
    {
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $callback();

                return;
            } catch (Throwable $e) {
                if ($attempt === self::MAX_RETRIES) {
                    throw $e;
                }

                $delay = self::RETRY_DELAY_SECONDS * $attempt;
                $this->command->warn("Attempt {$attempt} failed: {$e->getMessage()}");
                $this->command->warn("Retrying in {$delay}s...");
                sleep($delay);
            }
        }
    }

    // ── Story configuration ─────────────────────────────────────────

    private function getStoryConfig(): array
    {
        return [
            'title' => 'Nocturne',
            'slug' => 'nocturne',
            'category' => 'Mystery & Detective',
            'script' => 'NOCTURNE_Screenplay_script.txt',
            'source_pdf' => 'RnD/NOCTURNE_Screenplay.pdf',
            'teaser' => 'A door opened once. A name buried deep. Three sessions — three revelations. Nocturne is a noir mystery that remembers every choice you make.',
            'rating' => StoryRatingEnum::MATURE->value,
            'opening' => null,
            'creator' => [
                'first_name' => 'LoreSpinner',
                'last_name' => 'Originals',
                'username' => 'lorespinner',
                'email' => 'originals@lorespinner.com',
                'bio' => 'Original interactive stories crafted for choice-driven narrative experiences.',
                'avatar' => 'lorespinner-originals-avatar.jpg',
            ],
        ];
    }
}
