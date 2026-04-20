<?php

declare(strict_types=1);

namespace App\VoiceLab\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Response;

final class IndexController extends Controller
{
    public function __invoke(): Response
    {
        $intro = config('voice-lab.intro', []);

        return inertia('VoiceLab/Index', [
            'intro' => [
                'enabled' => (bool) ($intro['enabled'] ?? false),
                'audioUrl' => (string) ($intro['audio_url'] ?? ''),
                'choices' => array_values((array) ($intro['choices'] ?? [])),
            ],
        ]);
    }
}
