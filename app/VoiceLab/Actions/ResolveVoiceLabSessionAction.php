<?php

declare(strict_types=1);

namespace App\VoiceLab\Actions;

use App\Models\Story;
use App\Models\User;
use App\VoiceLab\Models\VoiceLabSession;

final readonly class ResolveVoiceLabSessionAction
{
    public function handle(User $user): VoiceLabSession
    {
        $existing = VoiceLabSession::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        $storyId = null;
        $storySlug = config('voice-lab.story_slug');

        if (is_string($storySlug) && $storySlug !== '') {
            $storyId = Story::query()->where('slug', $storySlug)->value('id');
        }

        return VoiceLabSession::query()->create([
            'user_id' => $user->id,
            'story_id' => $storyId,
        ]);
    }
}
