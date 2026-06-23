<?php

declare(strict_types=1);

namespace App\Http\Controllers\ChaosMode;

use App\ChaosMode\ChaosStoryConfig;
use App\Http\Controllers\Controller;
use App\Models\ChaosSession;
use App\Models\Story;
use App\Services\SpeechifyTtsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ChaosTtsController extends Controller
{
    private const ELEVENLABS_URL = 'https://api.elevenlabs.io/v1/text-to-speech/%s/stream';

    public function __construct(private readonly SpeechifyTtsService $speechify) {}

    /**
     * Serve TTS audio for a narrator turn in a chaos session.
     * Provider is controlled by the TTS_PROVIDER env var (elevenlabs | speechify | deepgram).
     *
     * Cache hit  → instant BinaryFileResponse from disk (full byte-range support).
     * Cache miss → download from provider (blocking), save to disk, then serve.
     *
     * StreamedResponse was tried but browsers' <audio> elements always initiate
     * with a Range request (Range: bytes=0-). StreamedResponse has no Range
     * handling, causing nginx to return 416 Range Not Satisfiable. BinaryFileResponse
     * handles Accept-Ranges / Content-Range / 206 automatically and works on all
     * browsers including iOS Safari.
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

        $provider = (string) config('services.tts_provider', 'elevenlabs');
        $path     = "tts/chaos/{$provider}/{$chaosSession->id}/{$turnIndex}.mp3";

        if (Storage::disk('local')->exists($path) && ! $this->cachedAudioIsValid($path)) {
            Storage::disk('local')->delete($path);
        }

        if (! Storage::disk('local')->exists($path)) {
            $slug = Story::find($chaosSession->story_id)?->slug ?? '';

            match ($provider) {
                'speechify' => $this->generateSpeechify($text, $path, ChaosStoryConfig::speechifyVoiceId($slug)),
                default     => $this->generateElevenLabs($text, $path, ChaosStoryConfig::ttsVoiceId($slug)),
            };
        }

        return $this->serve($path);
    }

    private function generateElevenLabs(string $text, string $path, string $voiceId): void
    {
        $apiKey  = config('services.elevenlabs.api_key');
        $voiceId = $voiceId !== '' ? $voiceId : (string) config('services.elevenlabs.voice_id');
        $modelId = config('services.elevenlabs.model_id', 'eleven_flash_v2_5');

        abort_unless(filled($apiKey) && filled($voiceId), 503, 'Voice generation is not configured.');

        $url = sprintf(self::ELEVENLABS_URL, $voiceId) . '?output_format=mp3_44100_128';

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

    private function generateSpeechify(string $text, string $path, string $voiceId): void
    {
        try {
            Storage::disk('local')->put(
                $path,
                $this->speechify->synthesize($text, $voiceId),
            );
        } catch (\RuntimeException $e) {
            abort(503, $e->getMessage());
        }
    }

    private function cachedAudioIsValid(string $path): bool
    {
        $bytes = Storage::disk('local')->get($path);

        return is_string($bytes) && $this->speechify->cachedBytesLookLikeAudio($bytes);
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
