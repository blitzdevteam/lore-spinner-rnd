<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

final class BakeVoiceLabIntroCommand extends Command
{
    protected $signature = 'voicelab:bake-intro
                            {--force : Regenerate the MP3 even if it already exists}';

    protected $description = 'Pre-bake the Voice Lab Session 1 cold open to a static MP3 on the public disk so the first orb tap plays with zero server latency.';

    public function handle(): int
    {
        $intro = config('voice-lab.intro', []);
        $apiKey = config('voice-lab.api_key');
        $voiceId = config('voice-lab.voice_id');
        $modelId = config('voice-lab.model_id', 'eleven_turbo_v2_5');
        $outputFormat = config('voice-lab.output_format', 'mp3_44100_128');
        $voiceSettings = config('voice-lab.voice_settings', []);

        $path = $intro['audio_path'] ?? 'voicelab/session-1-opening.mp3';
        $text = $intro['text'] ?? '';

        if (! is_string($apiKey) || $apiKey === '' || ! is_string($voiceId) || $voiceId === '') {
            $this->error('ElevenLabs is not configured. Set ELEVENLABS_API_KEY and VOICELAB_VOICE_ID.');

            return self::FAILURE;
        }

        if (trim($text) === '') {
            $this->error('config/voice-lab.php intro.text is empty.');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');

        if ($disk->exists($path) && ! $this->option('force')) {
            $this->info("Intro MP3 already exists at {$path}. Use --force to regenerate.");

            return self::SUCCESS;
        }

        $chunks = $this->splitIntoParagraphs($text);

        if (empty($chunks)) {
            $this->error('No narration paragraphs found in intro.text after splitting.');

            return self::FAILURE;
        }

        $this->info("Baking intro: {$path}");
        $this->line("Model: {$modelId}  |  Voice: {$voiceId}  |  Chunks: " . count($chunks));

        $url = "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}/stream?output_format={$outputFormat}";

        $responses = Http::pool(function (Pool $pool) use ($chunks, $apiKey, $url, $modelId, $voiceSettings) {
            return collect($chunks)->map(fn (string $chunk) => $pool
                ->withHeaders(['xi-api-key' => $apiKey])
                ->timeout(120)
                ->post($url, [
                    'text' => $chunk,
                    'model_id' => $modelId,
                    'voice_settings' => $voiceSettings,
                ]))->all();
        });

        $buffers = [];

        foreach ($responses as $i => $response) {
            if (! $response->successful()) {
                $this->error("Chunk {$i} failed: HTTP {$response->status()}");
                $this->line((string) $response->body());

                return self::FAILURE;
            }

            $buffers[] = $response->body();
        }

        $disk->makeDirectory(dirname($path));
        $disk->put($path, implode('', $buffers));

        $bytes = $disk->size($path);
        $this->info("Wrote {$bytes} bytes to public disk at {$path}");

        try {
            $url = $disk->url($path);
            $this->line("Public URL (from disk driver): {$url}");
        } catch (\Throwable) {
            $this->line('Public URL: (driver did not return one — check disk config)');
        }

        $symlink = public_path('storage');
        if (! file_exists($symlink)) {
            $this->warn('public/storage symlink is missing on this environment.');
            $this->warn('Run: php artisan storage:link');
        }

        return self::SUCCESS;
    }

    /**
     * Split on </p> boundaries, strip tags, filter empties.
     * Mirrors RnD Ideas/voice-hallucination-guard.md to prevent drift on long passages.
     *
     * @return list<string>
     */
    private function splitIntoParagraphs(string $html): array
    {
        $parts = preg_split('/<\/p>/i', $html) ?: [];

        return collect($parts)
            ->map(fn (string $part) => trim(strip_tags($part)))
            ->filter(fn (string $part) => $part !== '')
            ->values()
            ->all();
    }
}
