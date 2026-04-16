<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\VoiceLab;

use App\Actions\Game\CreateGameAction;
use App\Actions\Game\ProcessGameTurnAction;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Story;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RespondController extends Controller
{
    private const string STORY_SLUG = 'alices-adventures-in-wonderland';

    private const string VOICE_ADDENDUM = <<<'ADDENDUM'


=== VOICE OUTPUT ADDENDUM ===
Your response will be converted to speech and played aloud through an orb interface.

SPOKEN PROSE:
- Keep the HTML in the "response" field for structure, but write prose that sounds natural spoken aloud.
- Favor short, punchy sentences. Avoid overly long paragraphs.
- No visual-only formatting (no lists, no markdown, no headers inside the response).

CHOICE PRESENTATION (CRITICAL):
- At the END of your narration, you MUST naturally weave the choices into your spoken prose.
- Present them as organic options the character might consider, not a numbered list.
- Example: "You could follow the White Rabbit down the hole... or perhaps turn back toward the garden path. Then again, you might try calling out to it."
- The choices in the "choices" array must still be short action strings (for UI display), but your narration must verbally present them so the listener knows their options.
- NEVER say "Option 1", "Choice A", or use any meta-game language. Keep it diegetic.
ADDENDUM;

    public function __invoke(
        #[CurrentUser] User $user,
        Request $request,
        CreateGameAction $createGameAction,
        ProcessGameTurnAction $processTurn,
    ): StreamedResponse {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $elevenLabsKey = config('services.elevenlabs.api_key');
        $voiceId = config('services.elevenlabs.voice_id');
        $modelId = config('services.elevenlabs.model_id', 'eleven_v3');

        abort_unless(filled($elevenLabsKey), 503, 'Voice generation is not configured.');

        $story = Story::where('slug', self::STORY_SLUG)->firstOrFail();
        $game = $this->resolveGame($user, $story, $createGameAction, $processTurn);

        $aiResult = $processTurn->handle($game, $request->string('message')->toString(), self::VOICE_ADDENDUM);

        $spokenText = strip_tags($aiResult['response']);

        $ttsResponse = Http::withHeaders([
            'xi-api-key' => $elevenLabsKey,
        ])->timeout(120)->post(
            "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}/stream?output_format=mp3_44100_128",
            [
                'text' => $spokenText,
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
            'X-VoiceLab-Choices' => json_encode($aiResult['choices']),
            'Access-Control-Expose-Headers' => 'X-VoiceLab-Choices',
        ]);
    }

    private function resolveGame(
        User $user,
        Story $story,
        CreateGameAction $createGameAction,
        ProcessGameTurnAction $processTurn,
    ): Game {
        $game = $user->games()->where('story_id', $story->id)->first();

        if ($game) {
            return $game;
        }

        $game = $createGameAction->handle($user, $story);

        $firstNarration = $processTurn->generateFirstNarration($game, self::VOICE_ADDENDUM);

        $game->prompts()->create([
            'event_id' => $game->current_event_id,
            'response' => $firstNarration['response'],
            'choices' => $firstNarration['choices'],
        ]);

        return $game;
    }
}
