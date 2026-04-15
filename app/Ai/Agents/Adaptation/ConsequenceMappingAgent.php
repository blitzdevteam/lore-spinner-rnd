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

#[Model('gpt-5.2')]
#[Temperature(0.6)]
#[Timeout(180)]
class ConsequenceMappingAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.consequence-mapping.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $pathSchema = fn () => $schema->object([
            'immediate_effect' => $schema->string()->required()->title('Immediate Effect'),
            'current_session_echo' => $schema->string()->required()->title('Current Session Echo'),
            'next_session_payoff' => $schema->string()->required()->title('Next Session Payoff'),
            'later_session_legacy' => $schema->string()->required()->title('Later Session Legacy')->description('Specific or N/A.'),
        ])->required()->withoutAdditionalProperties();

        $consequenceMapSchema = fn (string $title) => $schema->object([
            'tracked_dimension' => $schema->string()->required()->title('Tracked Dimension')->description('Must match the Phase 5 branching choice dimension.'),
            'path_a' => $pathSchema()->title('Path A'),
            'path_b' => $pathSchema()->title('Path B'),
            'path_c' => $pathSchema()->title('Path C'),
        ])->required()->withoutAdditionalProperties()->title($title);

        return [
            'consequence_map_choice_1' => $consequenceMapSchema('Consequence Map — Branching Choice 1'),
            'consequence_map_choice_2' => $consequenceMapSchema('Consequence Map — Branching Choice 2'),
            'consequence_map_choice_3' => $schema->object([
                'tracked_dimension' => $schema->string()->required()->title('Tracked Dimension'),
                'path_a' => $schema->object([
                    'immediate_effect' => $schema->string()->required()->title('Immediate Effect')->description('N/A — session ends on this choice.'),
                    'next_session_opening' => $schema->string()->required()->title('Next Session Opening'),
                    'next_session_payoff' => $schema->string()->required()->title('Next Session Payoff'),
                    'later_session_legacy' => $schema->string()->required()->title('Later Session Legacy'),
                ])->required()->withoutAdditionalProperties()->title('Path A'),
                'path_b' => $schema->object([
                    'immediate_effect' => $schema->string()->required()->title('Immediate Effect'),
                    'next_session_opening' => $schema->string()->required()->title('Next Session Opening'),
                    'next_session_payoff' => $schema->string()->required()->title('Next Session Payoff'),
                    'later_session_legacy' => $schema->string()->required()->title('Later Session Legacy'),
                ])->required()->withoutAdditionalProperties()->title('Path B'),
                'path_c' => $schema->object([
                    'immediate_effect' => $schema->string()->required()->title('Immediate Effect'),
                    'next_session_opening' => $schema->string()->required()->title('Next Session Opening'),
                    'next_session_payoff' => $schema->string()->required()->title('Next Session Payoff'),
                    'later_session_legacy' => $schema->string()->required()->title('Later Session Legacy'),
                ])->required()->withoutAdditionalProperties()->title('Path C'),
            ])->required()->withoutAdditionalProperties()->title('Consequence Map — Branching Choice 3'),
            'validation_results' => $schema->object([
                'specificity' => $schema->string()->required()->title('Specificity')->description('PASS or list cells to revise.'),
                'asymmetry' => $schema->string()->required()->title('Asymmetry')->description('PASS or list choices to revise.'),
                'payability' => $schema->string()->required()->title('Payability')->description('PASS or list flags.'),
            ])->required()->withoutAdditionalProperties()->title('Validation Results'),
        ];
    }
}
