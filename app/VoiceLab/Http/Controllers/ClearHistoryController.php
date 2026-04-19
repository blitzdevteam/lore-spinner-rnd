<?php

declare(strict_types=1);

namespace App\VoiceLab\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\VoiceLab\Models\VoiceLabSession;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ClearHistoryController extends Controller
{
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        VoiceLabSession::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        return response()->json(['cleared' => true]);
    }
}
