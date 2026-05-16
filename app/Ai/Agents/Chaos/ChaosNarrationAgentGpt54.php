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

#[Model('gpt-5.4')]
#[Temperature(1.0)]
#[Timeout(90)]
class ChaosNarrationAgentGpt54 implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private string $customInstructions,
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->customInstructions;
    }

    public function schema(JsonSchema $schema): array
    {
        return ChaosNarrationAgent::chaosSchema($schema);
    }
}
