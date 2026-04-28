<?php

declare(strict_types=1);

namespace App\Actions\Game;

use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Event;
use App\Models\Game;
use App\Models\Story;
use App\Models\User;

final readonly class CreateGameAction
{
    public function handle(User $user, Story $story): Game
    {
        $firstChapter = $story->chapters()->orderBy('position')->first();

        $firstEvent = $firstChapter->events()
            ->orderBy('position')
            ->first();

        $startEvent = $this->resolveStartEvent($story, $firstEvent);

        return $user->games()->create([
            'story_id' => $story->id,
            'current_event_id' => $startEvent->id,
            'current_session_number' => $startEvent->session_number,
        ]);
    }

    public function resolveStartEvent(Story $story, Event $firstEvent): Event
    {
        $adaptation = $story->adaptation;

        if ($adaptation?->adaptation_status !== AdaptationStatusEnum::COMPLETED) {
            return $firstEvent;
        }

        $session1 = $adaptation->sessionAdaptations()
            ->where('session_number', 1)
            ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
            ->first();

        $startEventId = $session1?->entry_point_diagnosis['start_event_id'] ?? null;

        if ($startEventId === null) {
            return $firstEvent;
        }

        $resolved = Event::find($startEventId);

        if ($resolved
            && $resolved->chapter->story_id === $story->id
            && $resolved->session_number === 1) {
            return $resolved;
        }

        return $firstEvent;
    }
}
