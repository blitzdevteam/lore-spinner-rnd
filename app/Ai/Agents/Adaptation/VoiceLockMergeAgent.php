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
 * Pipeline Upgrade V2.2 — Voice Lock merge synthesis (Deliverable 1A or 1B).
 */
#[Model('gpt-5.4')]
#[Temperature(0.2)]
#[Timeout(420)]
class VoiceLockMergeAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private ?string $detectedFormat = null,
        private array $formatDetection = [],
        private array $ipAudit = [],
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
            'formatDetection' => $this->formatDetection,
            'formatDetectionOutput' => json_encode($this->formatDetection, JSON_PRETTY_PRINT),
            'ipAudit' => $this->ipAudit,
            'currentPhase' => 'Voice Lock Phase — Merge Synthesis (V2.2)',
        ])->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return VoiceLockSchema::mergeSchema($schema, $this->detectedFormat);
    }
}
