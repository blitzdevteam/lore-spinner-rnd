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
        $category = $this->story->category?->title ?? 'Fiction';

        $systemPromptData = $this->story->system_prompt;
        $toneAndStyle = $systemPromptData['tone_and_style'] ?? '';

        $visualVoice = $this->resolveVisualVoice($category, $toneAndStyle);

        return <<<PROMPT
        Create a cinematic wide-format movie poster cover image for an interactive story.

        STORY TITLE: "{$title}"
        GENRE: {$category}
        SYNOPSIS: {$teaser}
        TONE: {$toneAndStyle}

        VISUAL DIRECTION:
        {$visualVoice}

        UNIVERSAL REQUIREMENTS:
        - Cinematic widescreen composition (landscape orientation, 3:2 ratio)
        - Photorealistic or painterly-realist rendering — NO flat vector, NO cartoon, NO icon art
        - A single iconic hero image that captures the emotional core of the story
        - Dramatic, considered lighting that defines the mood — strong shadows, atmospheric depth
        - Rich, layered environment with foreground, midground, and background detail
        - The composition should feel like a premium Hollywood or prestige TV title card
        - Leave clear visual breathing room across the lower third (for title overlay)
        - No text, no letters, no words, no titles, no watermarks
        - No UI elements, no frames, no borders
        PROMPT;
    }

    private function resolveVisualVoice(string $category, string $tone): string
    {
        $category = strtolower($category);
        $tone = strtolower($tone);

        if (str_contains($category, 'horror') || str_contains($tone, 'dread') || str_contains($tone, 'terror') || str_contains($tone, 'paranoia')) {
            return implode("\n", [
                '- Gothic expressionist atmosphere — deep shadows, sickly candlelight, oppressive darkness',
                '- Unsettling geometry: claustrophobic rooms, tilted perspectives, things that feel wrong',
                '- Desaturated palette with one bleeding accent color (crimson, bile-yellow, sickly green)',
                '- The feel of a German Expressionist film reshot with modern cinematography',
                '- Dread should be palpable — something is wrong and the image knows it',
            ]);
        }

        if (str_contains($category, 'mystery') || str_contains($category, 'detective') || str_contains($tone, 'deduction') || str_contains($tone, 'investigation')) {
            return implode("\n", [
                '- Foggy Victorian or gaslit noir atmosphere — amber lamplight cutting through grey mist',
                '- A figure (silhouette or partial reveal) commanding a dramatic environment',
                '- Deep focus: sharp foreground clue or object, soft mysterious background',
                '- Cold-warm contrast: icy blues and greens against warm gaslight oranges',
                '- The feel of a prestige BBC crime drama or Conan Doyle film adaptation',
            ]);
        }

        if (str_contains($category, 'fantasy') || str_contains($tone, 'wonder') || str_contains($tone, 'magical') || str_contains($tone, 'whimsy')) {
            return implode("\n", [
                '- Lush, magical naturalism — impossible landscapes that feel both real and dreamlike',
                '- Saturated jewel tones: emerald greens, sapphire blues, warm golds',
                '- Scale contrast: small figure in a vast, wondrous world',
                '- Soft volumetric light filtering through fantastical foliage or architecture',
                '- The feel of a Guillermo del Toro or Studio Ghibli live-action production',
            ]);
        }

        if (str_contains($category, 'sci-fi') || str_contains($category, 'science fiction') || str_contains($tone, 'future') || str_contains($tone, 'technology')) {
            return implode("\n", [
                '- Hard sci-fi cinematography — clean steel, neon-lit corridors, vast cosmic backdrops',
                '- Cool blue-teal palette with stark white light sources',
                '- Human scale dwarfed by technological or cosmic enormity',
                '- The feel of Arrival, Ex Machina, or Blade Runner 2049',
            ]);
        }

        if (str_contains($category, 'thriller') || str_contains($tone, 'tension') || str_contains($tone, 'danger') || str_contains($tone, 'stakes')) {
            return implode("\n", [
                '- High-contrast noir-thriller cinematography — deep blacks, harsh key light',
                '- Motion implied in a frozen moment: a figure mid-decision, a shadow at the threshold',
                '- Desaturated base with single color temperature punch (cool or warm, not both)',
                '- The feel of a David Fincher or Denis Villeneuve film still',
            ]);
        }

        if (str_contains($category, 'romance') || str_contains($tone, 'longing') || str_contains($tone, 'love') || str_contains($tone, 'passion')) {
            return implode("\n", [
                '- Intimate cinematic realism — golden hour light, soft bokeh, warm tones',
                '- Two figures or one figure mid-emotion in a significant setting',
                '- Muted golds, dusty roses, warm shadows',
                '- The feel of a Wong Kar-wai or Joe Wright adaptation',
            ]);
        }

        if (str_contains($category, 'adventure') || str_contains($tone, 'epic') || str_contains($tone, 'journey')) {
            return implode("\n", [
                '- Sweeping epic cinematography — vast landscapes, dramatic skies, heroic scale',
                '- Warm adventurous palette: burnt oranges, deep teals, golden sunlight',
                '- A figure at the threshold of something enormous and unknown',
                '- The feel of Peter Jackson, Ridley Scott, or an Osprey illustrated cover come to life',
            ]);
        }

        return implode("\n", [
            '- Prestige drama cinematography — rich, considered lighting, layered environment',
            '- Naturalistic color grading with one dominant mood temperature',
            '- A character or landscape image that suggests story depth and emotional weight',
            '- The feel of a premium literary adaptation (HBO, A24, BBC Films)',
        ]);
    }
}
