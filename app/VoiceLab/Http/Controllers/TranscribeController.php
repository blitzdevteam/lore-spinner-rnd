<?php

declare(strict_types=1);

namespace App\VoiceLab\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

final class TranscribeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:10240'],
        ]);

        $apiKey = config('voice-lab.openai_api_key', env('OPENAI_API_KEY'));

        abort_unless(filled($apiKey), 503, 'Voice Lab transcription is not configured.');

        $file = $request->file('audio');

        $response = Http::withToken($apiKey)
            ->asMultipart()
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                [
                    'name' => 'file',
                    'contents' => fopen($file->getRealPath(), 'r'),
                    'filename' => $file->getClientOriginalName(),
                ],
                [
                    'name' => 'model',
                    'contents' => 'whisper-1',
                ],
                [
                    'name' => 'response_format',
                    'contents' => 'json',
                ],
            ]);

        if (! $response->successful()) {
            logger()->warning('VoiceLab: Whisper transcription failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json(['text' => ''], 502);
        }

        return response()->json([
            'text' => $response->json('text', ''),
        ]);
    }
}
