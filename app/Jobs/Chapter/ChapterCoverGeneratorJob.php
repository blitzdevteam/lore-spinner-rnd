<?php

declare(strict_types=1);

namespace App\Jobs\Chapter;

use App\Models\Chapter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Facades\Prism;
use Throwable;

final class ChapterCoverGeneratorJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public int $timeout = 300;

    public array $backoff = [30, 60, 120, 180, 300];

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new ThrottlesExceptions(2, 2))->byJob(),
        ];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Chapter $chapter,
    ) {
        $this->onQueue('image-generation');
    }

    /**
     * Execute the job.
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        if ($this->chapter->getFirstMedia('cover')) {
            Log::info("ChapterCoverGeneratorJob: Cover already exists for chapter [{$this->chapter->id}], skipping.");

            return;
        }

        $prompt = $this->buildPrompt();

        try {
            $image = $this->generateImage($prompt);
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'safety_violations') || str_contains($e->getMessage(), 'safety system')) {
                Log::warning("ChapterCoverGeneratorJob: Safety filter hit for chapter [{$this->chapter->id}], retrying with sanitized prompt.");
                $image = $this->generateImage($this->buildSafePrompt());
            } else {
                Log::error("ChapterCoverGeneratorJob: Failed for chapter [{$this->chapter->id}]: {$e->getMessage()}");
                throw $e;
            }
        }

        if (! $image || ! $image->base64) {
            Log::warning("ChapterCoverGeneratorJob: No image generated for chapter [{$this->chapter->id}].");

            return;
        }

        $media = $this->chapter
            ->addMediaFromBase64($image->base64)
            ->usingFileName("chapter-cover-{$this->chapter->id}.png")
            ->toMediaCollection('cover');

        if (! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
            $media->delete();
            throw new \RuntimeException("ChapterCoverGeneratorJob: File not found on disk [{$media->disk}] after save — config may be stale. Retrying.");
        }

        Log::info("ChapterCoverGeneratorJob: Generated cover for chapter [{$this->chapter->id}] on disk [{$media->disk}] — \"{$this->chapter->title}\".");
    }

    private function generateImage(string $prompt): ?\Prism\Prism\ValueObjects\GeneratedImage
    {
        $provider = env('IMAGE_PROVIDER', 'openai');
        $model = env('IMAGE_MODEL', 'gpt-image-1.5');

        $builder = Prism::image()
            ->using($provider, $model)
            ->withPrompt($prompt)
            ->withClientOptions(['timeout' => 120, 'connect_timeout' => 30]);

        if ($provider === 'openai') {
            $builder->withProviderOptions([
                'size' => '1024x1024',
                'quality' => 'low',
                'output_format' => 'png',
                'background' => 'auto',
            ]);
        }

        $response = $builder->generate();

        return $response->firstImage();
    }

    /**
     * Build the image generation prompt based on chapter metadata.
     */
    private function buildSafePrompt(): string
    {
        $chapterTitle = $this->chapter->title;
        $storyTitle = $this->chapter->story?->title ?? 'Unknown Story';
        $category = $this->chapter->story?->category?->title ?? 'Fantasy';

        return <<<PROMPT
        Create a vintage storybook scene illustration for a chapter called "{$chapterTitle}" in a {$category} story called "{$storyTitle}".

        STYLE REQUIREMENTS:
        - Flat vector illustration, minimal shading
        - Limited harmonious color palette, soft gradient background glow
        - Strong silhouettes, simple shapes
        - Retro editorial illustration style, 1960s poster aesthetic
        - Nostalgic and magical mood, clean storytelling design
        - High clarity, visually striking composition
        - No text, no letters, no words, no titles, no watermarks
        - No UI elements, no borders, no frames
        - Square composition (1:1 aspect ratio) suitable as a chapter thumbnail
        PROMPT;
    }

    private function buildPrompt(): string
    {
        $chapterTitle = $this->chapter->title;
        $chapterTeaser = mb_substr($this->chapter->teaser ?? '', 0, 500);
        $chapterContent = mb_substr($this->chapter->content ?? '', 0, 2000);
        $storyTitle = $this->chapter->story?->title ?? 'Unknown Story';
        $storyTeaser = mb_substr($this->chapter->story?->teaser ?? '', 0, 300);
        $category = $this->chapter->story?->category?->title ?? 'Fantasy';

        $storySystemPromptData = $this->chapter->story?->system_prompt;
        $toneAndStyle = mb_substr($storySystemPromptData['tone_and_style'] ?? '', 0, 300);

        return <<<PROMPT
        Create a vintage storybook scene illustration for a chapter in an interactive story.

        STORY: "{$storyTitle}" — {$storyTeaser}
        CHAPTER: "{$chapterTitle}"
        CHAPTER TEASER: {$chapterTeaser}
        CHAPTER SETTING: {$chapterContent}
        GENRE: {$category}
        TONE: {$toneAndStyle}

        STYLE REQUIREMENTS:
        - Vintage storybook illustration, centered composition
        - An iconic scene that captures this chapter's key moment
        - Characters posed in a symbolic and visually clear way
        - Environment reflecting the chapter setting
        - Flat vector illustration, minimal shading
        - Limited harmonious color palette, soft gradient background glow
        - Strong silhouettes, simple shapes
        - Retro editorial illustration style, 1960s poster aesthetic
        - Nostalgic and magical mood, clean storytelling design
        - High clarity, visually striking composition
        - No text, no letters, no words, no titles, no watermarks
        - No UI elements, no borders, no frames
        - Square composition (1:1 aspect ratio) suitable as a chapter thumbnail
        PROMPT;
    }
}
