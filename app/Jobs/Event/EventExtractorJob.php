<?php

declare(strict_types=1);

namespace App\Jobs\Event;

use App\Ai\Agents\EventExtractorAgent;
use App\Enums\Chapter\ChapterStatusEnum;
use App\Helpers\LineNumberFormatterHelper;
use App\Helpers\TextRangeExtractorHelper;
use App\Models\Chapter;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

final class EventExtractorJob implements ShouldQueue
{
    use Queueable;

    /**
     * Max characters of chapter content sent to the event extractor.
     * Prevents token-limit errors on very long chapters (e.g. feature-length screenplays).
     * ~60k chars ≈ 15k tokens of screenplay text — well within gpt-5.2's window.
     */
    private const int MAX_CONTENT_CHARS = 60_000;

    public int $tries = 3;

    public int $timeout = 660;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Chapter $chapter,
    ) {
        $this->onQueue('event-extraction');
    }

    /**
     * @throws Throwable
     */
    public function handle(): void {
        try {
            // Mark chapter as extracting events
            $this->chapter->update([
                'status' => ChapterStatusEnum::EXTRACTING_EVENTS,
            ]);

            // Truncate oversized chapters before sending to the agent to avoid token-limit errors.
            $content = $this->chapter->content;
            if (mb_strlen($content) > self::MAX_CONTENT_CHARS) {
                Log::warning("EventExtractorJob: Chapter [{$this->chapter->id}] \"{$this->chapter->title}\" truncated from " . mb_strlen($content) . " to " . self::MAX_CONTENT_CHARS . " chars for event extraction.");
                $content = mb_substr($content, 0, self::MAX_CONTENT_CHARS);
            }

            // Extract events using AI agent with line-numbered content
            $linedContent = LineNumberFormatterHelper::handle($content);

            /** @var StructuredAgentResponse $response */
            $response = EventExtractorAgent::make()
                ->prompt(
                    view('ai.agents.event-extractor.prompt', [
                        'length' => $linedContent['length'],
                        'content' => $linedContent['content'],
                    ])->render()
                );

            // Create events sorted by position
            $events = collect($response['events'])
                ->sortBy('position')
                ->map(fn (array $event): array => [
                    'position' => $event['position'],
                    'title' => $event['title'],
                    'content' => TextRangeExtractorHelper::handle($content, $event['start'], $event['end']),
                ]);

            // Queue jobs to extract objectives and attributes for each event
            $jobs = $this->chapter->events()
                ->createMany($events->all())
                ->map(fn ($event): \App\Jobs\Event\EventObjectiveAndAttributeExtractor => new EventObjectiveAndAttributeExtractor($event->id));

            // Update chapter status to waiting for event preparation
            $this->chapter->update([
                'status' => ChapterStatusEnum::WAITING_FOR_EVENT_PREPARATION,
            ]);

            // Store chapter ID to avoid serialization issues with closures
            // Process batch and update chapter status accordingly
            $chapterId = $this->chapter->id;
            Bus::batch($jobs)
                // After all events have been processed, mark chapter as ready to play
                ->then(function (Batch $batch) use ($chapterId): void {
                    Chapter::find($chapterId)?->update([
                        'status' => ChapterStatusEnum::READY_TO_PLAY,
                    ]);
                })
                ->onQueue('event-objective-and-attribute-extraction')
                ->dispatch();
        } catch (Throwable $throwable) {
            // Reset chapter status on failure
            $this->chapter->update([
                'status' => ChapterStatusEnum::AWAITING_EXTRACTING_EVENTS_REQUEST,
            ]);

            // Remove any partially created events
            $this->chapter->events()->delete();

            throw $throwable;
        }
    }
}
