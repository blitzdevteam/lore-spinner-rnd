<?php

declare(strict_types=1);

namespace App\Jobs\Story;

use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Facades\Prism;
use Throwable;

final class StoryCoverGeneratorJob implements ShouldQueue
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
        private Story $story,
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
        try {
            // Skip if cover already exists
            if ($this->story->getFirstMediaUrl('cover')) {
                Log::info("StoryCoverGeneratorJob: Cover already exists for story [{$this->story->id}], skipping.");

                return;
            }

            $prompt = $this->buildPrompt();

            $provider = env('IMAGE_PROVIDER', 'openai');
            $model = env('IMAGE_MODEL', 'gpt-image-1.5');

            $builder = Prism::image()
                ->using($provider, $model)
                ->withPrompt($prompt)
                ->withClientOptions(['timeout' => 120, 'connect_timeout' => 30]);

            if ($provider === 'openai') {
                $builder->withProviderOptions([
                    'size' => '1536x1024',
                    'quality' => 'high',
                    'output_format' => 'png',
                    'background' => 'auto',
                ]);
            }

            $response = $builder->generate();

            $image = $response->firstImage();

            if (! $image || ! $image->base64) {
                Log::warning("StoryCoverGeneratorJob: No image generated for story [{$this->story->id}].");

                return;
            }

            $media = $this->story
                ->addMediaFromBase64($image->base64)
                ->usingFileName("cover-{$this->story->id}.png")
                ->toMediaCollection('cover');

            if (! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
                $media->delete();
                throw new \RuntimeException("StoryCoverGeneratorJob: File not found on disk [{$media->disk}] after save — config may be stale. Retrying.");
            }

            Log::info("StoryCoverGeneratorJob: Generated cover for story [{$this->story->id}] on disk [{$media->disk}] — \"{$this->story->title}\".");
        } catch (Throwable $throwable) {
            Log::error("StoryCoverGeneratorJob: Failed for story [{$this->story->id}]: {$throwable->getMessage()}");

            throw $throwable;
        }
    }

    /**
     * Build the image generation prompt based on story metadata.
     */
    private function buildPrompt(): string
    {
        $title = $this->story->title;
        $teaser = $this->story->teaser ?? '';
        $category = $this->story->category?->title ?? 'Fantasy';

        $systemPromptData = $this->story->system_prompt;
        $toneAndStyle = $systemPromptData['tone_and_style'] ?? '';

        return <<<PROMPT
        Create a vintage storybook cover illustration for an interactive story.

        STORY TITLE: "{$title}"
        GENRE/CATEGORY: {$category}
        SYNOPSIS: {$teaser}
        TONE: {$toneAndStyle}

        STYLE REQUIREMENTS:
        - Vintage storybook cover illustration, centered composition
        - An iconic scene that represents the essence of the story
        - Main characters posed in a symbolic and visually clear way
        - Environment reflecting the story world
        - Flat vector illustration, minimal shading
        - Limited harmonious color palette, soft gradient background glow
        - Strong silhouettes, simple shapes
        - Retro editorial illustration style, 1960s poster aesthetic
        - Decorative frame elements, symmetrical layout
        - Nostalgic and magical mood, clean storytelling design
        - Cover art, bold integrated title typography
        - High clarity, visually striking composition
        - Landscape orientation, suitable as a wide cover image
        - No text, no letters, no words, no titles, no watermarks
        - No UI elements
        PROMPT;
    }
}
