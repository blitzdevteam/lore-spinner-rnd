<?php

declare(strict_types=1);

namespace App\Http\Controllers\ChaosMode;

use App\Http\Controllers\Controller;
use App\Models\ChaosSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ChaosTtsController extends Controller
{
    private const CHUNK_BYTES = 8192;

    private const ELEVENLABS_STREAM_URL = 'https://api.elevenlabs.io/v1/text-to-speech/%s/stream';

    /**
     * Serve ElevenLabs TTS audio for a narrator turn in a chaos session.
     *
     * Cache hit  → BinaryFileResponse with Accept-Ranges (iOS-friendly, instant seek).
     * Cache miss → StreamedResponse piping ElevenLabs bytes to the browser in real-time
     *              while simultaneously writing to disk so subsequent plays are instant.
     *
     * X-Accel-Buffering: no disables nginx FastCGI buffering, which was the primary
     * cause of the 6-7 second delay seen in logs (nginx was waiting for the full
     * response before forwarding anything to the client).
     */
    public function __invoke(ChaosSession $chaosSession, int $turnIndex): Response
    {
        $history = $chaosSession->conversation_history ?? [];

        $narratorTurns = array_values(
            array_filter($history, fn (array $turn) => ($turn['role'] ?? '') === 'narrator')
        );

        abort_if(! isset($narratorTurns[$turnIndex]), 404, 'Turn not found.');

        $text = strip_tags((string) ($narratorTurns[$turnIndex]['text'] ?? ''));

        abort_if(blank($text), 404, 'Turn has no text content.');

        $path = "tts/chaos/{$chaosSession->id}/{$turnIndex}.mp3";

        if (Storage::disk('local')->exists($path)) {
            return $this->serveCached($path);
        }

        return $this->streamFromElevenLabs($text, $path);
    }

    /**
     * Serve a cached mp3 with full byte-range support.
     *
     * BinaryFileResponse handles Accept-Ranges / Content-Range / 206 Partial automatically,
     * which is required for iOS Safari to display duration, enable seeking, and play reliably.
     */
    private function serveCached(string $path): BinaryFileResponse
    {
        $response = new BinaryFileResponse(
            Storage::disk('local')->path($path),
            200,
            [
                'Content-Type'           => 'audio/mpeg',
                'Accept-Ranges'          => 'bytes',
                'Cache-Control'          => 'private, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
                'X-Accel-Buffering'      => 'no',
            ]
        );

        $response->setAutoLastModified();

        return $response;
    }

    /**
     * Open an ElevenLabs streaming connection and pipe bytes straight to the browser.
     *
     * - `stream: true` on the Guzzle client prevents curl_exec() from blocking until
     *   the full body is downloaded — that was the 6-7 second hang in PHP-FPM.
     * - optimize_streaming_latency=3 asks ElevenLabs for maximum latency reduction
     *   (some quality trade-off acceptable for narration).
     * - eleven_flash_v2_5 is ElevenLabs' lowest-latency model; falls back to whatever
     *   ELEVENLABS_MODEL_ID is set to in .env.
     * - ob_end_flush loop flushes any PHP output buffers so chunks aren't held in memory.
     * - X-Accel-Buffering: no tells nginx not to buffer this FastCGI response.
     */
    private function streamFromElevenLabs(string $text, string $path): StreamedResponse
    {
        $apiKey  = config('services.elevenlabs.api_key');
        $voiceId = config('services.elevenlabs.voice_id');
        $modelId = config('services.elevenlabs.model_id', 'eleven_flash_v2_5');

        abort_unless(filled($apiKey) && filled($voiceId), 503, 'Voice generation is not configured.');

        $url = sprintf(self::ELEVENLABS_STREAM_URL, $voiceId)
            . '?output_format=mp3_44100_128&optimize_streaming_latency=3';

        $payload = [
            'text'           => $text,
            'model_id'       => $modelId,
            'voice_settings' => [
                'stability'        => 0.50,
                'similarity_boost' => 0.75,
                'style'            => 0.0,
                'speed'            => 1.0,
            ],
        ];

        return new StreamedResponse(
            function () use ($url, $apiKey, $payload, $path): void {
                // Flush all PHP output-buffer layers so chunks reach nginx immediately
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }

                $response = Http::withHeaders(['xi-api-key' => $apiKey])
                    ->withOptions(['stream' => true])
                    ->timeout(30)
                    ->post($url, $payload);

                if (! $response->successful()) {
                    logger()->warning('ElevenLabs chaos TTS stream failed', [
                        'status' => $response->status(),
                        'path'   => $path,
                    ]);
                    // Headers already sent — output empty body; browser audio element
                    // will fire an error event which the frontend handles gracefully.
                    return;
                }

                $body   = $response->toPsrResponse()->getBody();
                $buffer = '';

                while (! $body->eof()) {
                    $chunk = $body->read(self::CHUNK_BYTES);
                    if ($chunk === '') {
                        continue;
                    }

                    echo $chunk;
                    flush();

                    $buffer .= $chunk;
                }

                if ($buffer !== '') {
                    Storage::disk('local')->put($path, $buffer);
                }
            },
            200,
            [
                'Content-Type'           => 'audio/mpeg',
                'Cache-Control'          => 'no-cache',
                'X-Accel-Buffering'      => 'no',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
