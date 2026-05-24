<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Agents\Adaptation\VoiceLockAgent;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Pipeline Upgrade V2 — Deliverable 1.
 *
 * Runs once per IP between Phase 1 (IP Audit) and Phase 2 (Story Session Map).
 *
 * IMPORTANT — uses `Story::getScriptContent()` directly (the FULL ORIGINAL
 * source), NOT the trimmed text on `story_adaptations.ip_trimming`. Voice
 * extraction needs the complete range of the author's writing.
 */
final class VoiceLockJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1200;

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
                'adaptation_status' => AdaptationStatusEnum::VOICE_LOCK,
            ]);

            $fullOriginalSource = $this->story->getScriptContent();

            $response = (new VoiceLockAgent)->prompt(
                view('ai.agents.adaptation.voice-lock.prompt', [
                    'title' => $this->story->title,
                    'author' => $this->story->creator?->name ?? 'Unknown Author',
                    'year' => optional($this->story->published_at)->year ?? 'Unknown Year',
                    'format' => $adaptation->format_detection['detected_format'] ?? 'UNKNOWN',
                    'formatDetection' => $adaptation->format_detection,
                    'ipAudit' => $adaptation->ip_audit,
                    'sourceText' => $fullOriginalSource,
                ])->render()
            );

            $adaptation->update([
                'voice_profile' => $response->toArray(),
            ]);
        } catch (Throwable $throwable) {
            $adaptation->update([
                'adaptation_status' => AdaptationStatusEnum::FAILED,
            ]);

            throw $throwable;
        }
    }
}
