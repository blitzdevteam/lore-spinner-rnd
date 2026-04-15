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
#[Temperature(0.3)]
#[Timeout(120)]
class EditorialVerificationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.editorial-verification.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $questionResultSchema = $schema->object([
            'verdict' => $schema->string()->required()->title('Verdict')->description('PASS or REVISE.'),
            'detail' => $schema->string()->required()->title('Detail')->description('Supporting detail, evidence, or revision instruction.'),
        ])->required()->withoutAdditionalProperties();

        return [
            'question_results' => $schema->object([
                'entry_point' => (clone $questionResultSchema)->title('Q1 — Entry Point'),
                'emotional_promise' => (clone $questionResultSchema)->title('Q2 — Emotional Promise'),
                'first_choice_timing' => (clone $questionResultSchema)->title('Q3 — First Choice Timing'),
                'consequential_choices' => (clone $questionResultSchema)->title('Q4 — Consequential Choices'),
                'stakes_within_60_seconds' => (clone $questionResultSchema)->title('Q5 — Stakes Within 60 Seconds'),
                'early_decision_visible_later' => (clone $questionResultSchema)->title('Q6 — Early Decision Visible Later'),
                'breath_beat' => (clone $questionResultSchema)->title('Q7 — Breath Beat'),
                'moral_gray_area' => (clone $questionResultSchema)->title('Q8 — Moral Gray Area'),
                'exit_emotional_state' => (clone $questionResultSchema)->title('Q9 — Exit Emotional State'),
                'talkability' => (clone $questionResultSchema)->title('Q10 — Talkability'),
            ])->required()->withoutAdditionalProperties()->title('Question Results'),
            'total_passed' => $schema
                ->number()
                ->required()
                ->title('Total Passed')
                ->description('Count of PASS verdicts out of 10.'),
            'production_status' => $schema
                ->string()
                ->required()
                ->title('Production Status')
                ->description('GREEN (10/10), AMBER (8-9/10), or RED (7 or below).'),
            'revision_instructions' => $schema
                ->array()
                ->required()
                ->title('Revision Instructions')
                ->description('One entry per REVISE verdict. Empty array if all PASS.')
                ->items(
                    $schema->object([
                        'question' => $schema->string()->required()->title('Question'),
                        'phase' => $schema->string()->required()->title('Phase'),
                        'instruction' => $schema->string()->required()->title('Instruction'),
                    ])->required()->withoutAdditionalProperties()
                ),
        ];
    }
}
