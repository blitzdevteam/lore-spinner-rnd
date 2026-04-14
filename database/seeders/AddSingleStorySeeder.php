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
use App\Models\Game;
use App\Models\Story;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Wipe every existing story and seed a single new one from scratch.
 *
 * - Removes ALL stories (with chapters, events, games, prompts, media)
 * - Creates the creator if missing
 * - Converts PDF → TXT if no .txt exists
 * - Runs the full extraction pipeline (chapters → events → system prompt → opening)
 * - Attaches covers from database/stories/covers/ if present
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
            $this->removeAllStories();

            $config = $this->getStoryConfig();
            $this->processStory($config);
        } finally {
            config(['queue.default' => $previousQueue]);
        }
    }

    private function removeAllStories(): void
    {
        $stories = Story::all();

        if ($stories->isEmpty()) {
            $this->command->info('No existing stories to remove.');

            return;
        }

        $this->command->info("Removing {$stories->count()} existing stories...");

        foreach ($stories as $story) {
            foreach ($story->games as $game) {
                $game->prompts()->delete();
            }
            $story->games()->delete();
            $story->comments()->delete();

            foreach ($story->chapters as $chapter) {
                $chapter->events()->delete();
                $chapter->clearMediaCollection('cover');
            }
            $story->chapters()->delete();

            $story->clearMediaCollection('script');
            $story->clearMediaCollection('cover');
            $story->clearMediaCollection('gallery');
            $story->delete();

            $this->command->info("  Removed: {$story->title}");
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
            'opening' => null,
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

        $this->command->info('Generating cinematic opening...');
        $this->withRetry(fn () => StoryOpeningGeneratorJob::dispatchSync($story->fresh()));

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
            'title' => "Alice's Adventures in Wonderland",
            'slug' => 'alices-adventures-in-wonderland',
            'category' => 'Fantasy Adventure',
            'script' => "Alice's Adventures in Wonderland_script.txt",
            'source_pdf' => "RnD/Alice's Adventures in Wonderland.pdf",
            'teaser' => 'A curious girl tumbles down a rabbit hole into a fantastical underground world where nothing is quite what it seems, and every encounter grows curiouser and curiouser.',
            'rating' => StoryRatingEnum::EVERYONE->value,
            'creator' => [
                'first_name' => 'The Classics, Unbound',
                'last_name' => '',
                'username' => 'theclassicsunbound',
                'email' => 'classics@lorespinner.com',
                'bio' => "Enter the world's most iconic classic stories—now immersive, interactive adventures where your choices reshape timeless legends.",
                'avatar' => 'THE CLASSICS, UNBOUND - PROFILE PIC.jpg',
            ],
        ];
    }
}
