<?php

declare(strict_types=1);

namespace App\Jobs\Adaptation;

use App\Ai\Adaptation\RuntimeNarratorTemplateBuilder;
use App\Models\Story;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Pipeline Upgrade V2 — Deliverable 8 assembly job.
 *
 * Runs as the LAST step in each per-session chain (after EditorialVerificationJob).
 * Assembles the 17-section runtime narrator prompt from every prior phase output
 * and persists it on `session_adaptations.runtime_narrator_prompt`. Chaos Mode
 * then reads this column at session start; runtime only injects tiered state /
 * symbolic memory / opening scene on top.
 *
 * Failure modes:
 *   - Builder raises if compression cascade fails (prompt exceeds 65k chars even
 *     after dropping voice quotes + collapsing source to titles). Logged so the
 *     editor can mark the session for a manual split.
 */
final class RuntimeNarratorAssemblyJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $backoff = 30;

    public function __construct(
        private Story $story,
        private int $sessionNumber,
    ) {
        $this->onQueue('adaptation');
    }

    /**
     * @throws Throwable
     */
    public function handle(RuntimeNarratorTemplateBuilder $builder): void
    {
        $adaptation = $this->story->adaptation;
        $session = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        try {
            $prompt = $builder->build($this->story, $session);

            $session->update([
                'runtime_narrator_prompt' => $prompt,
                'runtime_narrator_assembled_at' => Carbon::now(),
            ]);
        } catch (RuntimeException $compressionFailure) {
            // Persist nothing. Daniel's in-place rule: there is no legacy partial
            // fallback. The session will stay un-runnable in Chaos Mode until the
            // pipeline is re-run for this story (typically via
            // `php artisan stories:run-adaptation <story> --force`).
            \Log::channel('narration')->error('runtime_narrator_assembly.compression_failed', [
                'story_id' => $this->story->id,
                'session_number' => $this->sessionNumber,
                'message' => $compressionFailure->getMessage(),
            ]);

            throw $compressionFailure;
        }
    }
}
