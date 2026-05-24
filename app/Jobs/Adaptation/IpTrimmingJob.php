<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\IpTrimmingAgent;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Models\Story;
use App\Models\StoryAdaptation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Pipeline Upgrade V2 — Deliverable 7.
 *
 * Runs first in the adaptation chain. Reduces the source text so every
 * downstream phase that consumes "source pages" pays fewer tokens.
 *
 * Behaviour notes:
 *   - The trimmed source is stored on `story_adaptations.ip_trimming.trimmed_source_text.text`.
 *   - Voice Lock receives the FULL ORIGINAL source via `Story::getScriptContent()`,
 *     bypassing this trimmed copy. That bypass is intentional per Deliverable 7
 *     ("voice extraction requires the complete range of the author's writing").
 *   - If the story has no adaptation row yet (first run via the pipeline trigger),
 *     it is created in PENDING state.
 */
final class IpTrimmingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

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
        $adaptation = $this->story->adaptation ?? StoryAdaptation::create([
            'story_id' => $this->story->id,
            'adaptation_status' => AdaptationStatusEnum::PENDING,
        ]);

        try {
            $adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::IP_TRIMMING,
            ]);

            $sourceText = $this->story->getScriptContent();

            $approxWordCount = str_word_count($sourceText);

            $response = (new IpTrimmingAgent)->prompt(
                view('ai.agents.adaptation.ip-trimming.prompt', [
                    'title' => $this->story->title,
                    'author' => $this->story->creator?->name ?? 'Unknown Author',
                    'year' => optional($this->story->published_at)->year ?? 'Unknown Year',
                    'format' => $adaptation->format_detection['detected_format'] ?? 'UNKNOWN',
                    'pageCount' => max(1, (int) round($approxWordCount / 250)) . ' pages (estimated @ 250 wpp)',
                    'sourceText' => $sourceText,
                ])->render()
            );

            $adaptation->update([
                'ip_trimming' => $response->toArray(),
            ]);
        } catch (Throwable $throwable) {
            $adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::FAILED,
            ]);

            throw $throwable;
        }
    }
}
