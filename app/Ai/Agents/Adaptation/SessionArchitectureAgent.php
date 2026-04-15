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
class SessionArchitectureAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.session-architecture.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $beatSchema = fn (string $name) => $schema->object([
            'source_moment' => $schema->string()->required()->title('Source Moment'),
            'why_it_qualifies' => $schema->string()->required()->title('Why It Qualifies'),
            'editorial_intervention' => $schema->string()->required()->title('Editorial Intervention')->description('Minimal / Moderate / Heavy / INVENTION REQUIRED'),
        ])->required()->withoutAdditionalProperties()->title($name);

        return [
            'beat_identification' => $schema
                ->object([
                    'setup' => $beatSchema('Setup Beat'),
                    'escalation' => $beatSchema('Escalation Beat'),
                    'breath' => $beatSchema('Breath Beat'),
                    'twist' => $beatSchema('Twist Beat'),
                    'resolution' => $beatSchema('Resolution Beat'),
                ])->required()->withoutAdditionalProperties()->title('Beat Identification'),
            'beat_map' => $schema
                ->array()
                ->required()
                ->title('Beat Map')
                ->description('Complete session beat map with time slots.')
                ->items(
                    $schema->object([
                        'time_range' => $schema->string()->required()->title('Time Range'),
                        'moment' => $schema->string()->required()->title('Moment'),
                        'beat_type' => $schema->string()->required()->title('Beat Type'),
                        'choice_type' => $schema->string()->required()->title('Choice Type')->description('BRANCHING, EXPRESSIVE, or none.'),
                        'choice_arrives' => $schema->string()->required()->title('Choice Arrives'),
                    ])->required()->withoutAdditionalProperties()
                ),
            'next_session_awareness' => $schema
                ->object([
                    'seed_for_next_session' => $schema->string()->required()->title('Seed For Next Session'),
                    'connects_to_next_dramatic_question' => $schema->string()->required()->title('Connects To Next Dramatic Question')->description('YES or NEEDS EDITORIAL BRIDGE.'),
                ])->required()->withoutAdditionalProperties()->title('Next Session Awareness'),
        ];
    }
}
