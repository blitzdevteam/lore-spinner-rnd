<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class SpeechifyTtsService
{
    /**
     * Synthesize narration text to MP3 bytes via Speechify REST API.
     *
     * @throws RuntimeException
     */
    public function synthesize(string $text, string $voiceId = ''): string
    {
        $apiKey = (string) config('services.speechify.api_key');
        $voiceId = $voiceId !== '' ? $voiceId : (string) config('services.speechify.voice_id', 'george');
        $model = (string) config('services.speechify.model', 'simba-english');

        if (! filled($apiKey)) {
            throw new RuntimeException('Speechify voice generation is not configured.');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])->timeout(120)->post('https://api.speechify.ai/v1/audio/speech', [
            'input'        => $text,
            'voice_id'     => $voiceId,
            'audio_format' => 'mp3',
            'model'        => $model,
        ]);

        if (! $response->successful()) {
            logger()->warning('Speechify TTS failed', [
                'status'   => $response->status(),
                'voice_id' => $voiceId,
                'body'     => $response->json() ?? $response->body(),
            ]);

            throw new RuntimeException('Speechify voice generation unavailable.');
        }

        $audioData = $response->json('audio_data');

        if (! is_string($audioData) || $audioData === '') {
            throw new RuntimeException('Speechify returned no audio data.');
        }

        $decoded = base64_decode($audioData, true);

        if ($decoded === false || $decoded === '') {
            throw new RuntimeException('Speechify returned invalid audio data.');
        }

        return $decoded;
    }

    /**
     * Detect cache files written before base64 decoding was implemented.
     */
    public function cachedBytesLookLikeAudio(string $bytes): bool
    {
        if ($bytes === '') {
            return false;
        }

        if (str_starts_with(ltrim($bytes), '{')) {
            return false;
        }

        return true;
    }
}
