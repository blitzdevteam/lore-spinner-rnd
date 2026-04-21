<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\EntryPointDiagnosisAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

final class EntryPointDiagnosisJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;
    public int $timeout = 300;
    public int $backoff = 60;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
    ) {
        $this->onQueue('adaptation');
    }

    /** @throws Throwable */
    public function handle(): void
    {
        $adaptation = $this->story->adaptation;
        $session = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        try {
            $session->update(['session_status' => SessionAdaptationStatusEnum::ENTRY_POINT_DIAGNOSIS]);

            $sessionEvents = $this->loadSessionEventsWithStoryPosition();

            if ($sessionEvents->isEmpty()) {
                throw new RuntimeException(
                    "No events found for story {$this->story->id} session {$this->sessionNumber}"
                );
            }

            $scriptContent = $this->story->getScriptContent();
            $sessionSourcePages = mb_substr($scriptContent, 0, 16000);

            $response = (new EntryPointDiagnosisAgent)->prompt(
                view('ai.agents.adaptation.entry-point-diagnosis.prompt', [
                    'storySessionMap' => $adaptation->story_session_map,
                    'ipAudit' => $adaptation->ip_audit,
                    'sessionNumber' => $this->sessionNumber,
                    'sessionSourcePages' => $sessionSourcePages,
                    'sessionEvents' => $sessionEvents->map(fn (Event $ev) => [
                        'story_position' => $ev->story_position,
                        'position' => $ev->position,
                        'chapter_position' => $ev->chapter_position,
                        'title' => $ev->title,
                        'objectives' => $ev->objectives,
                    ])->all(),
                ])->render()
            );

            $result = $response->toArray();

            $startPos = $result['start_event_position'] ?? null;
            $startEvent = $sessionEvents->firstWhere('story_position', $startPos)
                ?? $sessionEvents->first();

            $result['start_event_id'] = $startEvent->id;
            $result['start_event_position'] = $startEvent->story_position;
            $result['start_event_chapter_position'] = $startEvent->chapter_position;
            $result['start_event_local_position'] = $startEvent->position;

            $session->update(['entry_point_diagnosis' => $result]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }

    /**
     * Load the events in this session ordered by (chapter.position, event.position)
     * and stamp each row with a 1-based story-global `story_position`. The ordinal
     * is computed across ALL events in the story (not just this session) so it
     * matches what StorySessionMapJob shows the agent.
     *
     * @return Collection<int, Event>
     */
    private function loadSessionEventsWithStoryPosition(): Collection
    {
        $allEvents = Event::query()
            ->join('chapters', 'chapters.id', '=', 'events.chapter_id')
            ->where('chapters.story_id', $this->story->id)
            ->orderBy('chapters.position')
            ->orderBy('events.position')
            ->get([
                'events.id',
                'events.position',
                'events.title',
                'events.objectives',
                'events.chapter_id',
                'events.session_number',
                'chapters.position as chapter_position',
            ])
            ->values()
            ->map(function (Event $ev, int $i): Event {
                $ev->setAttribute('story_position', $i + 1);

                return $ev;
            });

        return $allEvents->filter(
            fn (Event $ev) => (int) $ev->session_number === $this->sessionNumber
        )->values();
    }
}
