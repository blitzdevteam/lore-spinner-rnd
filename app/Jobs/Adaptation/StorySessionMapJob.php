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
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
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

            $events = $this->story->events()
                ->orderBy('events.position')
                ->get(['events.id', 'events.position', 'events.title', 'events.objectives', 'events.chapter_id'])
                ->map(fn ($ev) => [
                    'position' => $ev->position,
                    'title' => $ev->title,
                    'objectives' => $ev->objectives,
                    'chapter_position' => $ev->chapter->position,
                ])
                ->all();

            $response = (new StorySessionMapAgent)->prompt(
                view('ai.agents.adaptation.story-session-map.prompt', [
                    'ipAudit' => $ipAudit,
                    'formatDetection' => json_encode($formatDetection, JSON_PRETTY_PRINT),
                    'estimatedSessionCount' => $formatDetection['estimated_session_count'] ?? 1,
                    'chapters' => $chapters,
                    'events' => $events,
                ])->render()
            );

            $sessionMap = $response->toArray();

            DB::transaction(function () use ($sessionMap, $adaptation): void {
                $adaptation->update([
                    'story_session_map' => $sessionMap,
                    'adaptation_status' => AdaptationStatusEnum::ADAPTING_SESSIONS,
                ]);

                $allEvents = $this->story->events()
                    ->orderBy('events.position')
                    ->get(['events.id', 'events.position']);

                foreach ($sessionMap['session_allocation'] as $session) {
                    $range = $this->parseEventRange($session['event_range']);
                    $eventIds = $allEvents
                        ->filter(fn ($ev) => $ev->position >= $range[0] && $ev->position <= $range[1])
                        ->pluck('id');

                    if ($eventIds->isNotEmpty()) {
                        Event::whereIn('id', $eventIds)->update([
                            'session_number' => $session['session_number'],
                        ]);
                    }

                    $adaptation->sessionAdaptations()->create([
                        'session_number' => $session['session_number'],
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

            $batch = Bus::batch(
                collect($sessionChains)->map(fn ($chain) => Bus::chain($chain)->onQueue('adaptation'))
            )
                ->onQueue('adaptation')
                ->finally(function () {
                    AdaptationStatusReconciliationJob::dispatch($this->story)->onQueue('adaptation');
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
}
