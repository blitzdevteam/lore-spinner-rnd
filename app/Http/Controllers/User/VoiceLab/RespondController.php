<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\VoiceLab;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RespondController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $openaiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        $elevenLabsKey = config('services.elevenlabs.api_key');
        $voiceId = config('services.elevenlabs.voice_id');
        $modelId = config('services.elevenlabs.model_id', 'eleven_v3');

        abort_unless(filled($openaiKey), 503, 'AI is not configured.');
        abort_unless(filled($elevenLabsKey), 503, 'Voice generation is not configured.');

        $history = $request->session()->get('voice_lab_history', []);

        $history[] = ['role' => 'user', 'content' => $request->string('message')->toString()];

        $aiResponse = Http::withToken($openaiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a friendly, creative storytelling companion. '
                            . 'Keep responses concise (2-4 sentences) since they will be spoken aloud. '
                            . 'Be expressive and engaging. Never use markdown, lists, or special formatting.',
                    ],
                    ...array_slice($history, -20),
                ],
                'max_tokens' => 200,
                'temperature' => 0.8,
            ]);

        if (! $aiResponse->successful()) {
            logger()->warning('VoiceLab: OpenAI request failed', [
                'status' => $aiResponse->status(),
            ]);
            abort(502, 'AI response failed.');
        }

        $reply = $aiResponse->json('choices.0.message.content', '');

        $history[] = ['role' => 'assistant', 'content' => $reply];
        $request->session()->put('voice_lab_history', array_slice($history, -20));

        $ttsResponse = Http::withHeaders([
            'xi-api-key' => $elevenLabsKey,
        ])->timeout(120)->post(
            "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}/stream?output_format=mp3_44100_128",
            [
                'text' => $reply,
                'model_id' => $modelId,
                'voice_settings' => [
                    'stability' => 0.50,
                    'similarity_boost' => 0.75,
                    'style' => 0.0,
                    'speed' => 1.0,
                ],
            ]
        );

        if (! $ttsResponse->successful()) {
            logger()->warning('VoiceLab: ElevenLabs TTS failed', [
                'status' => $ttsResponse->status(),
            ]);
            abort(502, 'Voice generation failed.');
        }

        $audioBody = $ttsResponse->body();

        return new StreamedResponse(function () use ($audioBody): void {
            echo $audioBody;
        }, 200, [
            'Content-Type' => 'audio/mpeg',
            'Content-Length' => strlen($audioBody),
            'Cache-Control' => 'no-cache, no-store',
        ]);
    }
}
