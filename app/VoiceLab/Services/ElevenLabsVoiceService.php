<?php

declare(strict_types=1);

namespace App\VoiceLab\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ElevenLabsVoiceService
{
    public function speak(string $text): string
    {
        $apiKey = config('voice-lab.api_key');
        $voiceId = config('voice-lab.voice_id');
        $modelId = config('voice-lab.model_id', 'eleven_v3');
        $outputFormat = config('voice-lab.output_format', 'mp3_44100_128');
        $voiceSettings = config('voice-lab.voice_settings', []);

        if (! is_string($apiKey) || $apiKey === '' || ! is_string($voiceId) || $voiceId === '') {
            throw new RuntimeException('Voice Lab TTS is not configured.');
        }

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
        ])->timeout(120)->post(
            "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}/stream?output_format={$outputFormat}",
            [
                'text' => $text,
                'model_id' => $modelId,
                'voice_settings' => $voiceSettings,
            ]
        );

        if (! $response->successful()) {
            logger()->warning('VoiceLab: ElevenLabs TTS failed', [
                'status' => $response->status(),
            ]);
            throw new RuntimeException('Voice generation failed.', $response->status());
        }

        return $response->body();
    }
}
