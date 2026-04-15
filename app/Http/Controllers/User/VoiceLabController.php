<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

final class VoiceLabController extends Controller
{
    public function index(): Response
    {
        return inertia('User/VoiceLab/Index');
    }

    public function clearHistory(Request $request): JsonResponse
    {
        $request->session()->forget('voice_lab_history');

        return response()->json(['cleared' => true]);
    }
}
