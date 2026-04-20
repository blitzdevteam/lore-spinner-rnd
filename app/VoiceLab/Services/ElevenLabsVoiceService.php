<?php

declare(strict_types=1);

namespace App\VoiceLab\Services;

use Generator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ElevenLabsVoiceService
{
    /**
     * Yield MP3 chunks from ElevenLabs as they arrive, so the HTTP response
     * can flush them straight to the browser for progressive playback.
     *
     * @return Generator<int, string, null, void>
     */
    public function stream(string $text, int $chunkBytes = 8192): Generator
    {
        $apiKey = config('voice-lab.api_key');
        $voiceId = config('voice-lab.voice_id');
        $modelId = config('voice-lab.model_id', 'eleven_turbo_v2_5');
        $outputFormat = config('voice-lab.output_format', 'mp3_44100_128');
        $voiceSettings = config('voice-lab.voice_settings', []);

        if (! is_string($apiKey) || $apiKey === '' || ! is_string($voiceId) || $voiceId === '') {
            throw new RuntimeException('Voice Lab TTS is not configured.');
        }

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
        ])
            ->withOptions(['stream' => true])
            ->timeout(120)
            ->post(
                "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}/stream?output_format={$outputFormat}",
                [
                    'text' => $text,
                    'model_id' => $modelId,
                    'voice_settings' => $voiceSettings,
                ]
            );

        if (! $response->successful()) {
            $status = $response->status();
            $snippet = (string) $response->body();
            logger()->warning('VoiceLab: ElevenLabs TTS failed', [
                'status' => $status,
                'body' => mb_substr($snippet, 0, 500),
            ]);

            throw new RuntimeException('Voice generation failed.', $status);
        }

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $chunk = $body->read($chunkBytes);
            if ($chunk === '') {
                continue;
            }
            yield $chunk;
        }
    }

    /**
     * Buffered compatibility wrapper — collects all streamed chunks into a
     * single binary string. Used anywhere a complete MP3 is needed (e.g.
     * caching the full clip to disk).
     */
    public function speak(string $text): string
    {
        $buffer = '';
        foreach ($this->stream($text) as $chunk) {
            $buffer .= $chunk;
        }

        return $buffer;
    }
}
