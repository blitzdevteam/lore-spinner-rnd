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
 *   - Builder raises if compression cascade fails (prompt exceeds its configured
 *     cap even after dropping voice quotes + collapsing source to titles). Logged
 *     so the editor can mark the session for a manual split.
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
        $session    = $adaptation->sessionAdaptations()->where('session_number', $this->sessionNumber)->firstOrFail();

        try {
            $prompt = $builder->build($this->story, $session);

            // D8 v2 post-render guards — explicit RuntimeException (no assert()).
            // The builder already checks these inside build(), but we enforce
            // here as a secondary gate before persisting to the database.

            if (preg_match('/\{\{[^}]+\}\}/', $prompt)) {
                throw new RuntimeException(sprintf(
                    'Assembled runtime narrator prompt contains unmapped {{…}} tokens (story %d, session %d). '
                    . 'Template slot wiring is incomplete — do not persist.',
                    $this->story->id,
                    $this->sessionNumber,
                ));
            }

            $charCount = mb_strlen($prompt);
            if ($charCount > RuntimeNarratorTemplateBuilder::MAX_PROMPT_CHARS) {
                throw new RuntimeException(sprintf(
                    'Assembled runtime narrator prompt exceeds %d character cap: %d chars (story %d, session %d). '
                    . 'Editorial split required.',
                    RuntimeNarratorTemplateBuilder::MAX_PROMPT_CHARS,
                    $charCount,
                    $this->story->id,
                    $this->sessionNumber,
                ));
            }

            $session->update([
                'runtime_narrator_prompt'        => $prompt,
                'runtime_narrator_assembled_at'  => Carbon::now(),
            ]);
        } catch (RuntimeException $assemblyFailure) {
            // Persist nothing. No legacy partial fallback. The session stays
            // un-runnable in Chaos Mode until the pipeline is re-run.
            \Log::channel('narration')->error('runtime_narrator_assembly.failed', [
                'story_id'       => $this->story->id,
                'session_number' => $this->sessionNumber,
                'message'        => $assemblyFailure->getMessage(),
            ]);

            throw $assemblyFailure;
        }
    }
}
