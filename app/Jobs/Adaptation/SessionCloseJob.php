<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\SessionCloseAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Event;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

final class SessionCloseJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;
    public int $timeout = 420;
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
            $session->update(['session_status' => SessionAdaptationStatusEnum::SESSION_CLOSE]);

            $choiceDesign = $session->session_choice_design;
            $consequenceMap = $session->choice_consequence_map;
            $scriptContent = $this->story->getScriptContent();

            $sessionAllocation = collect($adaptation->story_session_map['session_allocation'] ?? [])
                ->firstWhere('session_number', $this->sessionNumber);

            $sessionEvents = $this->loadSessionEventsWithStoryPosition();

            if ($sessionEvents->isEmpty()) {
                throw new RuntimeException(
                    "No events found for story {$this->story->id} session {$this->sessionNumber}"
                );
            }

            $response = (new SessionCloseAgent)->prompt(
                view('ai.agents.adaptation.session-close.prompt', [
                    'branchingChoice3Design' => $choiceDesign['branching_choice_3'] ?? null,
                    'choice3ConsequenceMap' => $consequenceMap['consequence_map_choice_3'] ?? null,
                    'sessionPrimaryGoal' => $sessionAllocation['primary_dramatic_question'] ?? '',
                    'sessionNumber' => $this->sessionNumber,
                    'sessionEvents' => $sessionEvents->map(fn (Event $ev) => [
                        'story_position' => $ev->story_position,
                        'title' => $ev->title,
                        'objectives' => $ev->objectives,
                    ])->all(),
                    'resolutionSourcePages' => mb_substr($scriptContent, 0, 16000),
                ])->render()
            );

            $result = $response->toArray();

            // Resolve the LLM-selected story_position back to a concrete event_id, the same
            // way EntryPointDiagnosisJob resolves start_event_position -> start_event_id.
            // This is the exit-point counterpart to entry-point resolution: pipeline authors
            // an explicit integer, runtime reads it. No heuristics, no drift.
            $triggerPos = $result['session_close_trigger_event_position'] ?? null;
            $triggerEvent = $triggerPos !== null
                ? $sessionEvents->firstWhere('story_position', (int) $triggerPos)
                : null;

            if ($triggerEvent === null) {
                $triggerEvent = $sessionEvents->last();
            }

            $result['session_close_trigger_event_id'] = $triggerEvent->id;
            $result['session_close_trigger_event_position'] = $triggerEvent->story_position;

            $session->update(['session_close_design' => $result]);
        } catch (Throwable $throwable) {
            $session->update(['session_status' => SessionAdaptationStatusEnum::FAILED]);
            throw $throwable;
        }
    }

    /**
     * Load the events in this session ordered by (chapter.position, event.position)
     * and stamp each row with a 1-based story-global `story_position`. Mirrors the
     * loader in EntryPointDiagnosisJob so session_close_trigger_event_position uses
     * the same ordinal space as start_event_position.
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
