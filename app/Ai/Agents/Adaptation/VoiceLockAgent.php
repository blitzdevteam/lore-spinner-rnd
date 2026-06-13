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
 * Pipeline Upgrade V2.2 — Deliverable 1: Voice Lock schema holder.
 */
#[Model('gpt-5.4')]
#[Temperature(0.2)]
#[Timeout(900)]
class VoiceLockAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private ?string $detectedFormat = null,
    ) {}

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        $view = VoiceLockSchema::isScreenwriter($this->detectedFormat)
            ? 'ai.agents.adaptation.voice-lock.system-prompt-screenwriter'
            : 'ai.agents.adaptation.voice-lock.system-prompt-novelist';

        return view($view, [
            'formatDetection' => [],
            'formatDetectionOutput' => '',
            'ipAudit' => [],
            'currentPhase' => 'Voice Lock Phase (between Phase 1 and Phase 2)',
        ])->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return VoiceLockSchema::mergeSchema($schema, $this->detectedFormat);
    }
}
