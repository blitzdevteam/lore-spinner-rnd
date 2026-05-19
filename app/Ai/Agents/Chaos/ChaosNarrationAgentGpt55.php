<?php

declare(strict_types=1);

namespace App\Ai\Agents\Chaos;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

// Default temperature lowered to 0.9 — GPT-5.5 strict structured output is more reliable
// below 1.0, especially as conversation context grows across turns.
#[Model('gpt-5.5')]
#[Temperature(0.9)]
#[Timeout(90)]
class ChaosNarrationAgentGpt55 implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private string $customInstructions,
        private float $runtimeTemperature = 0.9,
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->customInstructions;
    }

    public function temperature(): float
    {
        return $this->runtimeTemperature;
    }

    public function schema(JsonSchema $schema): array
    {
        return ChaosNarrationAgent::chaosSchema($schema);
    }
}
