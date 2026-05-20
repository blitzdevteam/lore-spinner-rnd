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
     * Max characters per chunk sent to the event extractor.
     * After LineNumberFormatterHelper adds #N# prefixes + the length index block,
     * raw chars inflate ~25–30%. 25k raw chars ≈ safe budget for any chapter size.
     * Chapters larger than this are split into multiple chunks at line boundaries
     * and merged — no events are silently dropped.
     */
    private const int CHUNK_SIZE_CHARS = 25_000;

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

            $content = $this->chapter->content;
            $chunks = $this->splitIntoChunks($content);

            if (count($chunks) > 1) {
                Log::info("EventExtractorJob: Chapter [{$this->chapter->id}] \"{$this->chapter->title}\" split into " . count($chunks) . " chunks (" . mb_strlen($content) . " chars total).");
            }

            $allEvents = collect();
            $positionOffset = 0;

            foreach ($chunks as $chunk) {
                $linedContent = LineNumberFormatterHelper::handle($chunk);

                /** @var StructuredAgentResponse $response */
                $response = EventExtractorAgent::make()
                    ->prompt(
                        view('ai.agents.event-extractor.prompt', [
                            'length' => $linedContent['length'],
                            'content' => $linedContent['content'],
                        ])->render()
                    );

                $chunkEvents = collect($response['events'])
                    ->sortBy('position')
                    ->map(fn (array $event): array => [
                        'position' => $event['position'] + $positionOffset,
                        'title' => $event['title'],
                        'content' => TextRangeExtractorHelper::handle($chunk, $event['start'], $event['end']),
                    ]);

                $positionOffset += $chunkEvents->count();
                $allEvents = $allEvents->concat($chunkEvents);
            }

            $events = $allEvents;

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

    /**
     * Split content into chunks at line boundaries, each under CHUNK_SIZE_CHARS.
     * Returns a single-element array when the content fits in one chunk.
     *
     * @return list<string>
     */
    private function splitIntoChunks(string $content): array
    {
        if (mb_strlen($content) <= self::CHUNK_SIZE_CHARS) {
            return [$content];
        }

        $lines = explode("\n", str_replace("\r\n", "\n", $content));
        $chunks = [];
        $current = '';

        foreach ($lines as $line) {
            $lineWithNewline = $line . "\n";

            if ($current !== '' && mb_strlen($current) + mb_strlen($lineWithNewline) > self::CHUNK_SIZE_CHARS) {
                $chunks[] = rtrim($current, "\n");
                $current = '';
            }

            $current .= $lineWithNewline;
        }

        if ($current !== '') {
            $chunks[] = rtrim($current, "\n");
        }

        return $chunks;
    }
}
