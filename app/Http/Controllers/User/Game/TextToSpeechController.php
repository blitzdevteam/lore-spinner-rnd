<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Game;

use App\ChaosMode\ChaosStoryConfig;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Prompt;
use App\Services\SpeechifyTtsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class TextToSpeechController extends Controller
{
    public function __construct(private readonly SpeechifyTtsService $speechify) {}

    public function __invoke(Game $game, Prompt $prompt): BinaryFileResponse
    {
        abort_unless($prompt->game_id === $game->id, 404);
        abort_unless(filled($prompt->response), 404);

        $provider = (string) config('services.tts_provider', 'elevenlabs');
        $path = "tts/{$provider}/{$prompt->id}.mp3";

        if (Storage::disk('local')->exists($path) && ! $this->cachedAudioIsValid($path)) {
            Storage::disk('local')->delete($path);
        }

        if (! Storage::disk('local')->exists($path)) {
            $storySlug = $game->story?->slug ?? '';

            match ($provider) {
                'deepgram'  => $this->generateDeepgram($prompt, $path),
                'speechify' => $this->generateSpeechify($prompt, $path, ChaosStoryConfig::speechifyVoiceId($storySlug)),
                default     => $this->generateElevenLabs($prompt, $path, ChaosStoryConfig::ttsVoiceId($storySlug)),
            };
        }

        return new BinaryFileResponse(Storage::disk('local')->path($path), 200, [
            'Content-Type' => 'audio/mpeg',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function generateElevenLabs(Prompt $prompt, string $path, string $voiceId = ''): void
    {
        $text    = strip_tags($prompt->response);
        $voiceId = $voiceId !== '' ? $voiceId : (string) config('services.elevenlabs.voice_id');
        $apiKey  = config('services.elevenlabs.api_key');

        abort_unless(filled($apiKey), 503, 'Voice generation is not configured.');

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
        ])->timeout(120)->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}/stream?output_format=mp3_44100_128", [
            'text' => $text,
            'model_id' => config('services.elevenlabs.model_id', 'eleven_v3'),
            'voice_settings' => [
                'stability' => 0.50,
                'similarity_boost' => 0.75,
                'style' => 0.0,
                'speed' => 1.0,
            ],
        ]);

        if (! $response->successful()) {
            logger()->warning('ElevenLabs TTS failed', [
                'status' => $response->status(),
                'prompt_id' => $prompt->id,
            ]);

            abort($response->status() === 403 ? 502 : $response->status(), 'Voice generation unavailable.');
        }

        Storage::disk('local')->put($path, $response->body());
    }

    private function generateDeepgram(Prompt $prompt, string $path): void
    {
        $text = strip_tags($prompt->response);
        $apiKey = (string) config('services.deepgram.api_key');
        $voiceModel = (string) config('services.deepgram.voice_model', 'aura-2-thalia-en');

        abort_unless(filled($apiKey), 503, 'Deepgram voice generation is not configured.');

        // Deepgram TTS REST endpoint — returns audio/mpeg directly
        $response = Http::withHeaders([
            'Authorization' => "Token {$apiKey}",
            'Content-Type'  => 'application/json',
        ])->timeout(60)->post("https://api.deepgram.com/v1/speak?model={$voiceModel}&encoding=mp3", [
            'text' => $text,
        ]);

        if (! $response->successful()) {
            logger()->warning('Deepgram TTS failed', [
                'status' => $response->status(),
                'prompt_id' => $prompt->id,
            ]);

            abort($response->status() === 403 ? 502 : $response->status(), 'Voice generation unavailable.');
        }

        Storage::disk('local')->put($path, $response->body());
    }

    private function generateSpeechify(Prompt $prompt, string $path, string $voiceId = ''): void
    {
        $text = strip_tags($prompt->response);

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
}
