<?php

declare(strict_types=1);

namespace App\Http\Controllers\ChaosMode;

use App\Http\Controllers\Controller;
use App\Models\ChaosSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ChaosTtsController extends Controller
{
    /**
     * Serve ElevenLabs TTS audio for a narrator turn in a chaos session.
     *
     * @param  ChaosSession  $chaosSession
     * @param  int           $turnIndex  0-based index into the narrator turns only
     */
    public function __invoke(ChaosSession $chaosSession, int $turnIndex): BinaryFileResponse
    {
        $history = $chaosSession->conversation_history ?? [];

        // Filter to narrator turns only
        $narratorTurns = array_values(
            array_filter($history, fn (array $turn) => ($turn['role'] ?? '') === 'narrator')
        );

        abort_if(! isset($narratorTurns[$turnIndex]), 404, 'Turn not found.');

        $text = strip_tags((string) ($narratorTurns[$turnIndex]['text'] ?? ''));

        abort_if(blank($text), 404, 'Turn has no text content.');

        $path = "tts/chaos/{$chaosSession->id}/{$turnIndex}.mp3";

        if (! Storage::disk('local')->exists($path)) {
            $this->generateElevenLabs($text, $path);
        }

        return new BinaryFileResponse(Storage::disk('local')->path($path), 200, [
            'Content-Type'           => 'audio/mpeg',
            'Accept-Ranges'          => 'bytes',
            'Cache-Control'          => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function generateElevenLabs(string $text, string $path): void
    {
        $voiceId = config('services.elevenlabs.voice_id');
        $apiKey  = config('services.elevenlabs.api_key');

        abort_unless(filled($apiKey), 503, 'Voice generation is not configured.');

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
        ])->timeout(120)->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}/stream?output_format=mp3_44100_128", [
            'text'     => $text,
            'model_id' => config('services.elevenlabs.model_id', 'eleven_v3'),
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
}
