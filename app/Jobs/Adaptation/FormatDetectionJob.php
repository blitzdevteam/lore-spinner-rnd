<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\FormatDetectionAgent;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class FormatDetectionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

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
                'adaptation_status' => AdaptationStatusEnum::FORMAT_DETECTION,
            ]);

            // Prefer the ip_trimming trimmed source when available — it is smaller
            // and already has redundant description stripped, making format detection
            // more signal-dense for the same token budget.
            $ipTrimming = $adaptation->ip_trimming ?? [];
            $source = ! empty($ipTrimming['trimmed_source_text']['text'])
                ? $ipTrimming['trimmed_source_text']['text']
                : $this->story->getScriptContent();
            $excerpt = mb_substr($source, 0, 8000);

            $response = (new FormatDetectionAgent)->prompt(
                view('ai.agents.adaptation.format-detection.prompt', [
                    'scriptExcerpt' => $excerpt,
                ])->render()
            );

            $adaptation->update([
                'format_detection' => $response->toArray(),
            ]);
        } catch (Throwable $throwable) {
            $adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::FAILED,
            ]);

            throw $throwable;
        }
    }
}
