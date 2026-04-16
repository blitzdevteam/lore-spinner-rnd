<?php

declare(strict_types=1);

namespace App\Jobs\Creator;

use App\Models\Creator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Facades\Prism;
use Throwable;

final class CreatorAvatarGeneratorJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Creator $creator,
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
            // Skip if avatar already exists via media library
            if ($this->creator->getFirstMedia('avatar')) {
                Log::info("CreatorAvatarGeneratorJob: Avatar already exists for creator [{$this->creator->id}], skipping.");

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
                    'size' => '1024x1024',
                    'quality' => 'high',
                    'output_format' => 'png',
                    'background' => 'auto',
                ]);
            }

            $response = $builder->generate();

            $image = $response->firstImage();

            if (! $image || ! $image->base64) {
                Log::warning("CreatorAvatarGeneratorJob: No image generated for creator [{$this->creator->id}].");

                return;
            }

            $media = $this->creator
                ->addMediaFromBase64($image->base64)
                ->usingFileName("avatar-{$this->creator->id}.png")
                ->toMediaCollection('avatar');

            if (! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
                $media->delete();
                throw new \RuntimeException("CreatorAvatarGeneratorJob: File not found on disk [{$media->disk}] after save — config may be stale. Retrying.");
            }

            Log::info("CreatorAvatarGeneratorJob: Generated avatar for creator [{$this->creator->id}] on disk [{$media->disk}] — \"{$this->creator->full_name}\".");
        } catch (Throwable $throwable) {
            Log::error("CreatorAvatarGeneratorJob: Failed for creator [{$this->creator->id}]: {$throwable->getMessage()}");

            throw $throwable;
        }
    }

    /**
     * Build the image generation prompt for the creator avatar.
     */
    private function buildPrompt(): string
    {
        $name = $this->creator->full_name;
        $bio = $this->creator->bio ?? '';

        $bioContext = $bio
            ? "The creator describes themselves as: \"{$bio}\""
            : 'No bio provided — use a mysterious, enigmatic vibe.';

        return <<<PROMPT
        Create a stylized fantasy avatar portrait for a story creator/author.

        CREATOR NAME: "{$name}"
        {$bioContext}

        STYLE REQUIREMENTS:
        - Stylized portrait — NOT photorealistic, painterly digital art style
        - Dark background with subtle magical particle effects
        - Teal/cyan (#54f4da) accent lighting as rim light or magical glow
        - Warm golden secondary highlights
        - Mysterious, creative, authorial vibe — like a storyteller or game master
        - Centered face/bust composition suitable for a circular avatar crop
        - No text, no letters, no words, no watermarks
        - Fantasy aesthetic consistent with a dark interactive storytelling platform
        PROMPT;
    }
}
