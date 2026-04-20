<?php

declare(strict_types=1);

namespace App\VoiceLab\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\VoiceLab\Actions\ProcessVoiceTurnAction;
use App\VoiceLab\Actions\ResolveVoiceLabSessionAction;
use App\VoiceLab\Http\Requests\StoreVoiceLabMessageRequest;
use App\VoiceLab\Services\ElevenLabsVoiceService;
use Illuminate\Container\Attributes\CurrentUser;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RespondController extends Controller
{
    public function __invoke(
        #[CurrentUser] User $user,
        StoreVoiceLabMessageRequest $request,
        ResolveVoiceLabSessionAction $resolveSession,
        ProcessVoiceTurnAction $processTurn,
        ElevenLabsVoiceService $tts,
    ): StreamedResponse {
        $session = $resolveSession->handle($user);

        if (config('voice-lab.greeting_enabled', true) && $session->prompts()->doesntExist()) {
            $processTurn->generateOpening($session);
        }

        $result = $processTurn->handle($session, $request->string('message')->toString());
        $spokenText = strip_tags($result['response']);

        return new StreamedResponse(function () use ($tts, $spokenText): void {
            @ini_set('output_buffering', '0');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');

            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            try {
                foreach ($tts->stream($spokenText) as $chunk) {
                    echo $chunk;
                    @ob_flush();
                    @flush();
                }
            } catch (RuntimeException $e) {
                logger()->warning('VoiceLab: streaming TTS aborted mid-response', [
                    'message' => $e->getMessage(),
                    'status' => $e->getCode(),
                ]);
            }
        }, 200, [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            // Disable nginx/reverse-proxy buffering so chunks flush to the client
            // as they arrive from ElevenLabs.
            'X-Accel-Buffering' => 'no',
            'X-VoiceLab-Choices' => (string) json_encode($result['choices']),
            'Access-Control-Expose-Headers' => 'X-VoiceLab-Choices',
        ]);
    }
}
