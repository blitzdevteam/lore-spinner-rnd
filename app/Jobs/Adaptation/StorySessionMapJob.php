<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\StorySessionMapAgent;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Event;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class StorySessionMapJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public int $backoff = 60;

    public function __construct(
        private Story $story,
    ) {
        $this->onQueue('adaptation');
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $adaptation = $this->story->adaptation;

        try {
            $adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::STORY_SESSION_MAP,
            ]);

            $formatDetection = $adaptation->format_detection;
            $ipAudit = $adaptation->ip_audit;

            $chapters = $this->story->chapters()
                ->orderBy('position')
                ->get(['id', 'position', 'title', 'teaser'])
                ->map(fn ($ch) => [
                    'position' => $ch->position,
                    'title' => $ch->title,
                ])
                ->all();

            $storyGlobalEvents = $this->loadEventsWithStoryPosition();
            $totalEvents = $storyGlobalEvents->count();

            $events = $storyGlobalEvents
                ->map(fn ($ev) => [
                    'story_position' => $ev->story_position,
                    'position' => $ev->position,
                    'title' => $ev->title,
                    'objectives' => $ev->objectives,
                    'chapter_position' => $ev->chapter_position,
                ])
                ->all();

            $response = (new StorySessionMapAgent)->prompt(
                view('ai.agents.adaptation.story-session-map.prompt', [
                    'ipAudit' => $ipAudit,
                    'formatDetection' => json_encode($formatDetection, JSON_PRETTY_PRINT),
                    'estimatedSessionCount' => $formatDetection['estimated_session_count'] ?? 1,
                    'chapters' => $chapters,
                    'events' => $events,
                    'totalEvents' => $totalEvents,
                ])->render()
            );

            $sessionMap = $response->toArray();

            DB::transaction(function () use ($sessionMap, $adaptation, $storyGlobalEvents): void {
                $adaptation->sessionAdaptations()->delete();
                $this->story->events()->update(['session_number' => null]);

                $adaptation->update([
                    'story_session_map' => $sessionMap,
                    'adaptation_status' => AdaptationStatusEnum::ADAPTING_SESSIONS,
                ]);

                $sessionNumbers = collect($sessionMap['session_allocation'])
                    ->pluck('session_number')
                    ->sort()
                    ->values();

                foreach ($sessionMap['session_allocation'] as $session) {
                    $range = $this->parseEventRange($session['event_range']);
                    $eventIds = $storyGlobalEvents
                        ->filter(fn ($ev) => $ev->story_position >= $range[0] && $ev->story_position <= $range[1])
                        ->pluck('id');

                    if ($eventIds->isEmpty()) {
                        throw new RuntimeException(sprintf(
                            'Session %d resolved to zero events for range "%s" (story has %d events, story-global positions 1..%d).',
                            $session['session_number'],
                            $session['event_range'],
                            $storyGlobalEvents->count(),
                            $storyGlobalEvents->count(),
                        ));
                    }

                    Event::whereIn('id', $eventIds)->update([
                        'session_number' => $session['session_number'],
                    ]);
                }

                foreach ($sessionNumbers as $num) {
                    $adaptation->sessionAdaptations()->create([
                        'session_number' => $num,
                        'session_status' => SessionAdaptationStatusEnum::PENDING,
                    ]);
                }
            });

            $sessionAdaptations = $adaptation->sessionAdaptations()->orderBy('session_number')->get();

            $sessionChains = [];
            foreach ($sessionAdaptations as $sa) {
                $sessionChains[] = [
                    new EntryPointDiagnosisJob($this->story, $sa->session_number),
                    new SessionArchitectureJob($this->story, $sa->session_number),
                    new ChoiceDesignJob($this->story, $sa->session_number),
                    new ConsequenceMappingJob($this->story, $sa->session_number),
                    new SessionCloseJob($this->story, $sa->session_number),
                    new EditorialVerificationJob($this->story, $sa->session_number),
                ];
            }

            $storyId = $this->story->id;

            $batch = Bus::batch($sessionChains)
                ->onQueue('adaptation')
                ->finally(function () use ($storyId) {
                    $story = Story::findOrFail($storyId);
                    AdaptationStatusReconciliationJob::dispatch($story)->onQueue('adaptation');
                })
                ->dispatch();
        } catch (Throwable $throwable) {
            $adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::FAILED,
            ]);

            throw $throwable;
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseEventRange(string $range): array
    {
        $parts = explode('-', $range);

        return [(int) trim($parts[0]), (int) trim($parts[1] ?? $parts[0])];
    }

    /**
     * Load every event in the story ordered by (chapter.position, event.position)
     * and stamp a 1-based story-global `story_position` onto each row. This index
     * is the contract used for both the agent prompt and session_allocation filtering.
     *
     * @return Collection<int, Event>
     */
    private function loadEventsWithStoryPosition(): Collection
    {
        return Event::query()
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
                'chapters.position as chapter_position',
            ])
            ->values()
            ->map(function (Event $ev, int $i): Event {
                $ev->setAttribute('story_position', $i + 1);

                return $ev;
            });
    }
}
