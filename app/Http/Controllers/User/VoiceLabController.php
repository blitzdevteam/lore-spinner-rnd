<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Story;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

final class VoiceLabController extends Controller
{
    private const string STORY_SLUG = 'alices-adventures-in-wonderland';

    public function index(): Response
    {
        return inertia('User/VoiceLab/Index');
    }

    public function clearHistory(
        #[CurrentUser] User $user,
        Request $request,
    ): JsonResponse {
        $story = Story::where('slug', self::STORY_SLUG)->first();

        if ($story) {
            $game = $user->games()->where('story_id', $story->id)->first();

            if ($game) {
                $game->prompts()->delete();
                $firstEvent = $story->events()->orderBy('position')->first();

                if ($firstEvent) {
                    $game->update([
                        'current_event_id' => $firstEvent->id,
                        'current_session_number' => $firstEvent->session_number,
                    ]);
                }
            }
        }

        $request->session()->forget('voice_lab_history');

        return response()->json(['cleared' => true]);
    }
}
