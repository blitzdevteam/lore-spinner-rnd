<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;
use Throwable;

/**
 * Pipeline Upgrade V2 — Voice Lock (synthesis / merge pass).
 *
 * Receives all per-chapter voice observation fragments and synthesizes the
 * complete Author Voice DNA Profile matching the full Deliverable 1 schema.
 *
 * This agent produces the same structured output as the original VoiceLockAgent
 * but operates over compact per-chapter fragments rather than the full source
 * text, avoiding the 600s timeout that killed the monolithic approach.
 *
 * gpt-5.4 (full model): synthesis of voice DNA requires high judgment quality
 * — this output is constitutional law for all narrator prose.
 */
#[Model('gpt-5.4')]
#[Temperature(0.2)]
#[Timeout(300)]
class VoiceLockMergeAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.voice-lock.system-prompt', [
            'formatDetection' => [],
            'formatDetectionOutput' => '',
            'currentPhase' => 'Voice Lock Phase — Merge Synthesis',
        ])->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return (new VoiceLockAgent)->schema($schema);
    }
}
