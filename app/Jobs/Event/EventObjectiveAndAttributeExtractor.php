<?php

namespace App\Jobs\Event;

use App\Ai\Agents\EventObjectiveAndAttributesExtractor;
use App\Models\Chapter;
use App\Models\Event;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

class EventObjectiveAndAttributeExtractor implements ShouldQueue
{
    use Batchable;
    use Queueable;

    private Event $event;

    private Chapter $chapter;

    /**
     * Create a new job instance.
     */
    public function __construct(int $id)
    {
        $this->event = Event::query()
            ->with('chapter')
            ->findOrFail($id);

        $this->chapter = $this->event->chapter;
    }

    /**
     * Execute the job.
     * @throws Throwable
     */
    public function handle(): void
    {
        if ($this->batch()?->canceled()) {
            return;
        }

        /** @var StructuredAgentResponse $response */
        $response = EventObjectiveAndAttributesExtractor::make()
            ->prompt(
                view('ai.agents.event-objective-and-attribute-extractor.prompt', [
                    'targetEvent' => $this->event,
                    'nextEvents' => $this->nextEvents()
                ])->render()
            );

        $this->event->update([
            'attributes' => $response['attributes'],
            'objectives' => $response['objective'],
        ]);
    }

    /**
     * @return Collection<int, Event>
     */
    private function nextEvents(int $take = 3): Collection
    {
        return $this->chapter->events()
            ->orderBy('position')
            ->where('position', '>', $this->event->position)
            ->take($take)
            ->get();
    }
}
