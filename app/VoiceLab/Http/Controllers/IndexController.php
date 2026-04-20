<?php

declare(strict_types=1);

namespace App\VoiceLab\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;
use Throwable;

final class IndexController extends Controller
{
    public function __invoke(): Response
    {
        $intro = config('voice-lab.intro', []);
        $disk = Storage::disk('public');
        $path = (string) ($intro['audio_path'] ?? '');
        $enabled = (bool) ($intro['enabled'] ?? false);

        // Prefer the disk's own URL (respects local vs S3 vs any driver), so
        // environments with symlink-less public storage still get the right
        // URL. Fall back to the configured path/url, then disable the intro
        // entirely if the baked file simply isn't there.
        $audioUrl = '';
        if ($path !== '') {
            try {
                $audioUrl = $disk->exists($path)
                    ? $disk->url($path)
                    : (string) ($intro['audio_url'] ?? '');
            } catch (Throwable $e) {
                $audioUrl = (string) ($intro['audio_url'] ?? '');
            }
        }

        // If the file definitely isn't on disk, don't lie to the frontend —
        // it'll just record on first tap instead of throwing a 404 toast.
        if ($enabled && $path !== '' && ! $disk->exists($path)) {
            $enabled = false;
        }

        return inertia('VoiceLab/Index', [
            'intro' => [
                'enabled' => $enabled,
                'audioUrl' => $audioUrl,
                'choices' => array_values((array) ($intro['choices'] ?? [])),
            ],
        ]);
    }
}
