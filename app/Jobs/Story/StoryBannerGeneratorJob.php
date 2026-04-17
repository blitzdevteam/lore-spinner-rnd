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

final class StoryBannerGeneratorJob implements ShouldQueue
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

    public function __construct(
        private Story $story,
    ) {
        $this->onQueue('image-generation');
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        try {
            if ($this->story->getFirstMediaUrl('banner')) {
                Log::info("StoryBannerGeneratorJob: Banner already exists for story [{$this->story->id}], skipping.");

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
                    'size' => '2048x1536',
                    'quality' => 'high',
                    'output_format' => 'png',
                    'background' => 'auto',
                ]);
            }

            $response = $builder->generate();

            $image = $response->firstImage();

            if (! $image || ! $image->base64) {
                Log::warning("StoryBannerGeneratorJob: No image generated for story [{$this->story->id}].");

                return;
            }

            $media = $this->story
                ->addMediaFromBase64($image->base64)
                ->usingFileName("banner-{$this->story->id}.png")
                ->toMediaCollection('banner');

            if (! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
                $media->delete();
                throw new \RuntimeException("StoryBannerGeneratorJob: File not found on disk [{$media->disk}] after save — config may be stale. Retrying.");
            }

            Log::info("StoryBannerGeneratorJob: Generated banner for story [{$this->story->id}] on disk [{$media->disk}] — \"{$this->story->title}\".");
        } catch (Throwable $throwable) {
            Log::error("StoryBannerGeneratorJob: Failed for story [{$this->story->id}]: {$throwable->getMessage()}");

            throw $throwable;
        }
    }

    private function buildPrompt(): string
    {
        $title = $this->story->title;
        $teaser = $this->story->teaser ?? '';
        $category = $this->story->category?->title ?? 'Fantasy';

        $systemPromptData = $this->story->system_prompt;
        $toneAndStyle = $systemPromptData['tone_and_style'] ?? '';

        return <<<PROMPT
        Create a wide cinematic banner illustration for an interactive story homepage hero section.

        STORY TITLE: "{$title}"
        GENRE/CATEGORY: {$category}
        SYNOPSIS: {$teaser}
        TONE: {$toneAndStyle}

        COMPOSITION — CRITICAL:
        - The LEFT 40% of the image must be mostly empty, atmospheric, with soft gradients or subtle environmental details only — this area will have a dark text overlay on top of it
        - All main characters, key objects, and focal action must be placed in the RIGHT 60% of the image
        - Transition from open/atmospheric on the left to detailed/busy on the right

        STYLE REQUIREMENTS:
        - Vintage storybook illustration, wide panoramic composition
        - An iconic wide scene that represents the essence of the story
        - Characters posed in a symbolic and visually clear way on the right side
        - Environment reflecting the story world, expansive and atmospheric
        - Flat vector illustration, minimal shading
        - Limited harmonious color palette, soft gradient background glow
        - Strong silhouettes, simple shapes
        - Retro editorial illustration style, 1960s poster aesthetic
        - Nostalgic and magical mood, clean storytelling design
        - High clarity, visually striking composition
        - Wide landscape orientation suitable as a cinematic hero banner
        - No text, no letters, no words, no titles, no watermarks
        - No UI elements, no borders, no frames
        PROMPT;
    }
}
