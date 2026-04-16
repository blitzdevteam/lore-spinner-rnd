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

            $scriptContent = $this->story->getScriptContent();
            $excerpt = mb_substr($scriptContent, 0, 8000);

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
