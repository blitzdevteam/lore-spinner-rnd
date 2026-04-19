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

        try {
            $audio = $tts->speak($spokenText);
        } catch (RuntimeException $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502;

            abort($status, $e->getMessage());
        }

        return new StreamedResponse(function () use ($audio): void {
            echo $audio;
        }, 200, [
            'Content-Type' => 'audio/mpeg',
            'Content-Length' => strlen($audio),
            'Cache-Control' => 'no-cache, no-store',
            'X-VoiceLab-Choices' => json_encode($result['choices']),
            'Access-Control-Expose-Headers' => 'X-VoiceLab-Choices',
        ]);
    }
}
