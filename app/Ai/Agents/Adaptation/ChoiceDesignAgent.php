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
#[Temperature(0.7)]
#[Timeout(240)]
class ChoiceDesignAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.choice-design.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $optionSchema = $schema->object([
            'text' => $schema->string()->required()->title('Option Text'),
            'downstream_effect' => $schema->string()->required()->title('Downstream Effect'),
        ])->required()->withoutAdditionalProperties();

        $branchingChoiceSchema = fn (string $title) => $schema->object([
            'choice_id' => $schema->string()->required()->title('Choice ID')->description('Format: S{session}_C{number}, e.g. S1_C1.'),
            'source_moment' => $schema->string()->required()->title('Source Moment'),
            'what_this_choice_tracks' => $schema->string()->required()->title('What This Choice Tracks')->description('Must reference a Phase 2 branch dimension or declare a new one.'),
            'narrative_setup' => $schema->string()->required()->title('Narrative Setup')->description('2-4 sentences of second-person prose leading to the choice.'),
            'choice_question' => $schema->string()->required()->title('Choice Question'),
            'option_a' => (clone $optionSchema)->title('Option A'),
            'option_b' => (clone $optionSchema)->title('Option B'),
            'option_c' => (clone $optionSchema)->title('Option C'),
            'all_paths_arrive_at' => $schema->string()->required()->title('All Paths Arrive At'),
        ])->required()->withoutAdditionalProperties()->title($title);

        $expressiveChoiceSchema = $schema->object([
            'beat' => $schema->string()->required()->title('Beat')->description('ESCALATION or BREATH.'),
            'source_moment' => $schema->string()->required()->title('Source Moment'),
            'narrative_lead_in' => $schema->string()->required()->title('Narrative Lead-In'),
            'choice_question' => $schema->string()->required()->title('Choice Question'),
            'option_a' => (clone $optionSchema)->title('Option A'),
            'option_b' => (clone $optionSchema)->title('Option B'),
            'option_c' => (clone $optionSchema)->title('Option C'),
            'all_paths_arrive_at' => $schema->string()->required()->title('All Paths Arrive At'),
        ])->required()->withoutAdditionalProperties();

        return [
            'branching_choice_1' => $branchingChoiceSchema('Branching Choice 1 — Identity'),
            'expressive_choices' => $schema
                ->array()
                ->required()
                ->title('Expressive Choices')
                ->items($expressiveChoiceSchema),
            'branching_choice_2' => $schema->object([
                'choice_id' => $schema->string()->required()->title('Choice ID'),
                'source_moment' => $schema->string()->required()->title('Source Moment'),
                'values_in_tension' => $schema->array()->required()->title('Values In Tension')->items($schema->string()->required()),
                'what_this_choice_tracks' => $schema->string()->required()->title('What This Choice Tracks'),
                'narrative_setup' => $schema->string()->required()->title('Narrative Setup'),
                'choice_question' => $schema->string()->required()->title('Choice Question'),
                'option_a' => (clone $optionSchema)->title('Option A'),
                'option_b' => (clone $optionSchema)->title('Option B'),
                'option_c' => (clone $optionSchema)->title('Option C'),
                'moral_weight_confirmation' => $schema->string()->required()->title('Moral Weight Confirmation'),
                'talkability_test' => $schema->string()->required()->title('Talkability Test'),
            ])->required()->withoutAdditionalProperties()->title('Branching Choice 2 — Moral Weight'),
            'branching_choice_3' => $schema->object([
                'choice_id' => $schema->string()->required()->title('Choice ID'),
                'source_moment' => $schema->string()->required()->title('Source Moment'),
                'what_this_choice_tracks' => $schema->string()->required()->title('What This Choice Tracks'),
                'narrative_setup' => $schema->string()->required()->title('Narrative Setup'),
                'choice_question' => $schema->string()->required()->title('Choice Question'),
                'option_a' => $schema->object([
                    'text' => $schema->string()->required()->title('Option Text'),
                    'next_session_opens' => $schema->string()->required()->title('Next Session Opens'),
                ])->required()->withoutAdditionalProperties()->title('Option A'),
                'option_b' => $schema->object([
                    'text' => $schema->string()->required()->title('Option Text'),
                    'next_session_opens' => $schema->string()->required()->title('Next Session Opens'),
                ])->required()->withoutAdditionalProperties()->title('Option B'),
                'option_c' => $schema->object([
                    'text' => $schema->string()->required()->title('Option Text'),
                    'next_session_opens' => $schema->string()->required()->title('Next Session Opens'),
                ])->required()->withoutAdditionalProperties()->title('Option C'),
            ])->required()->withoutAdditionalProperties()->title('Branching Choice 3 — Session-End Hook'),
        ];
    }
}
