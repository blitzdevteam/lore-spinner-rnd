<?php

declare(strict_types=1);

namespace App\Http\Controllers\ChaosMode;

use App\ChaosMode\ChaosStoryConfig;
use App\Http\Controllers\Controller;
use App\Models\ChaosSession;
use App\Models\Story;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ChaosTtsController extends Controller
{
    private const ELEVENLABS_URL = 'https://api.elevenlabs.io/v1/text-to-speech/%s/stream';

    /**
     * Serve ElevenLabs TTS audio for a narrator turn in a chaos session.
     *
     * Cache hit  → instant BinaryFileResponse from disk (full byte-range support).
     * Cache miss → download from ElevenLabs (blocking), save to disk, then serve.
     *
     * StreamedResponse was tried but browsers' <audio> elements always initiate
     * with a Range request (Range: bytes=0-). StreamedResponse has no Range
     * handling, causing nginx to return 416 Range Not Satisfiable. BinaryFileResponse
     * handles Accept-Ranges / Content-Range / 206 automatically and works on all
     * browsers including iOS Safari.
     *
     * Latency is kept low via eleven_flash_v2_5 (ElevenLabs' fastest model) and
     * optimize_streaming_latency=3 — roughly 50-60% faster than the original
     * eleven_v3 baseline, reducing generation from ~7s to ~2-3s.
     */
    public function __invoke(ChaosSession $chaosSession, int $turnIndex): BinaryFileResponse
    {
        $history = $chaosSession->conversation_history ?? [];

        $narratorTurns = array_values(
            array_filter($history, fn (array $turn) => ($turn['role'] ?? '') === 'narrator')
        );

        abort_if(! isset($narratorTurns[$turnIndex]), 404, 'Turn not found.');

        $text = strip_tags((string) ($narratorTurns[$turnIndex]['text'] ?? ''));

        abort_if(blank($text), 404, 'Turn has no text content.');

        $path = "tts/chaos/{$chaosSession->id}/{$turnIndex}.mp3";

        if (! Storage::disk('local')->exists($path)) {
            $slug    = Story::find($chaosSession->story_id)?->slug ?? '';
            $voiceId = ChaosStoryConfig::ttsVoiceId($slug);
            $this->generate($text, $path, $voiceId);
        }

        return $this->serve($path);
    }

    private function generate(string $text, string $path, string $voiceId): void
    {
        $apiKey  = config('services.elevenlabs.api_key');
        $voiceId = $voiceId !== '' ? $voiceId : (string) config('services.elevenlabs.voice_id');
        $modelId = config('services.elevenlabs.model_id', 'eleven_flash_v2_5');

        abort_unless(filled($apiKey) && filled($voiceId), 503, 'Voice generation is not configured.');

        $url = sprintf(self::ELEVENLABS_URL, $voiceId)
            . '?output_format=mp3_44100_128&optimize_streaming_latency=3';

        $response = Http::withHeaders(['xi-api-key' => $apiKey])
            ->timeout(90)
            ->post($url, [
                'text'           => $text,
                'model_id'       => $modelId,
                'voice_settings' => [
                    'stability'        => 0.50,
                    'similarity_boost' => 0.75,
                    'style'            => 0.0,
                    'speed'            => 1.0,
                ],
            ]);

        if (! $response->successful()) {
            logger()->warning('ElevenLabs chaos TTS failed', [
                'status' => $response->status(),
                'path'   => $path,
            ]);

            abort($response->status() === 403 ? 502 : $response->status(), 'Voice generation unavailable.');
        }

        Storage::disk('local')->put($path, $response->body());
    }

    /**
     * Serve a cached mp3 with full byte-range support.
     *
     * BinaryFileResponse handles Range / 206 / Content-Range automatically,
     * which is required for iOS Safari duration display and mid-track seeking.
     */
    private function serve(string $path): BinaryFileResponse
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
}
