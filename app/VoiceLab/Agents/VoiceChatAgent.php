<?php

declare(strict_types=1);

namespace App\VoiceLab\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.85)]
#[Timeout(60)]
class VoiceChatAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private string $customInstructions,
    ) {}

    /**
     * Runtime-resolved model. Defaults to the same model Voice Lab shipped
     * with originally (gpt-5.2). Set VOICELAB_LLM_MODEL to a mini variant
     * once you confirm the exact slug registered in your Laravel\Ai
     * provider — dropping to mini saves ~1–2s per turn.
     */
    public function model(): string
    {
        return (string) env('VOICELAB_LLM_MODEL', 'gpt-5.2');
    }

    public function instructions(): Stringable|string
    {
        return $this->customInstructions;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'response' => $schema
                ->string()
                ->required()
                ->title('Response')
                ->description('Short, cinematic, conversational narration written for voice playback. Use <p> tags. 2-4 sentences. End by naturally weaving 2-3 choices into the prose.'),
            'choices' => $schema
                ->array()
                ->required()
                ->title('Choices')
                ->description('Exactly 3 short actionable choices. Each starts with a strong verb. These mirror the options verbally woven into the response.')
                ->items(
                    $schema
                        ->string()
                        ->required()
                        ->title('Choice')
                        ->description('A single concrete, actionable choice starting with a strong verb.')
                ),
        ];
    }
}
