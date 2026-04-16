<?php

declare(strict_types=1);

namespace App\Jobs\Story;

use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Facades\Prism;
use Throwable;

final class StoryBannerGeneratorJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 60;

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

            $response = Prism::image()
                ->using('openai', 'gpt-image-1')
                ->withPrompt($prompt)
                ->withClientOptions(['timeout' => 120, 'connect_timeout' => 30])
                ->withProviderOptions([
                    'size' => '1536x1024',
                    'quality' => 'high',
                    'output_format' => 'png',
                    'background' => 'auto',
                ])
                ->generate();

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

        STYLE REQUIREMENTS:
        - Vintage storybook illustration, wide panoramic composition
        - An iconic wide scene that represents the essence of the story
        - Main characters posed in a symbolic and visually clear way, placed off-center left to leave space for text overlay on the right
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
