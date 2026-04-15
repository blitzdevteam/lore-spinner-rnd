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
#[Temperature(0.5)]
#[Timeout(240)]
class StorySessionMapAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.story-session-map.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'confirmed_session_count' => $schema
                ->number()
                ->required()
                ->title('Confirmed Session Count'),
            'session_allocation' => $schema
                ->array()
                ->required()
                ->title('Session Allocation')
                ->items(
                    $schema->object([
                        'session_number' => $schema->number()->required()->title('Session Number'),
                        'chapters_covered' => $schema->string()->required()->title('Chapters Covered'),
                        'event_range' => $schema->string()->required()->title('Event Range')->description('Event position range, e.g. "1-8".'),
                        'primary_dramatic_question' => $schema->string()->required()->title('Primary Dramatic Question'),
                        'emotional_register' => $schema->string()->required()->title('Emotional Register'),
                    ])->required()->withoutAdditionalProperties()
                ),
            'arc_progression' => $schema
                ->array()
                ->required()
                ->title('Arc Progression')
                ->items(
                    $schema->object([
                        'session_number' => $schema->number()->required()->title('Session Number'),
                        'opens_with' => $schema->string()->required()->title('Opens With')->description('Seed from previous session close. N/A for session 1.'),
                        'primary_dramatic_question' => $schema->string()->required()->title('Primary Dramatic Question'),
                        'emotional_register_shift' => $schema->string()->required()->title('Emotional Register Shift'),
                    ])->required()->withoutAdditionalProperties()
                ),
            'branch_opportunities' => $schema
                ->array()
                ->required()
                ->title('Branch Opportunities')
                ->description('2-3 strongest branching choice opportunities per session.')
                ->items(
                    $schema->object([
                        'session_number' => $schema->number()->required()->title('Session Number'),
                        'event_position' => $schema->number()->required()->title('Event Position'),
                        'event_title' => $schema->string()->required()->title('Event Title'),
                        'choice_dimension' => $schema->string()->required()->title('Choice Dimension'),
                        'downstream_payoff_session' => $schema->number()->required()->title('Downstream Payoff Session'),
                        'payoff_event' => $schema->string()->required()->title('Payoff Event'),
                    ])->required()->withoutAdditionalProperties()
                ),
            'cross_session_payoff_plan' => $schema
                ->array()
                ->required()
                ->title('Cross-Session Payoff Plan')
                ->items(
                    $schema->object([
                        'choice_reference' => $schema->string()->required()->title('Choice Reference')->description('Session + event reference.'),
                        'what_it_tracks' => $schema->string()->required()->title('What It Tracks'),
                        'echo_session' => $schema->number()->required()->title('Echo Session'),
                        'payoff_session' => $schema->number()->required()->title('Payoff Session'),
                        'payoff_description' => $schema->string()->required()->title('Payoff Description'),
                    ])->required()->withoutAdditionalProperties()
                ),
            'branch_dimensions' => $schema
                ->array()
                ->required()
                ->title('Branch Dimensions')
                ->description('3-6 canonical narrative axes for the story.')
                ->items(
                    $schema->object([
                        'dimension_name' => $schema->string()->required()->title('Dimension Name')->description('Snake_case tension axis, e.g. trust_vs_caution.'),
                        'description' => $schema->string()->required()->title('Description')->description('One-sentence human-readable definition.'),
                    ])->required()->withoutAdditionalProperties()
                ),
        ];
    }
}
