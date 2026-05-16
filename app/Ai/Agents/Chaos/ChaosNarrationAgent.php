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

#[Model('gpt-5.2')]
#[Temperature(1.0)]
#[Timeout(90)]
class ChaosNarrationAgent implements Agent, HasStructuredOutput
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
        return self::chaosSchema($schema);
    }

    /**
     * Shared schema definition for all chaos narration agent variants.
     */
    public static function chaosSchema(JsonSchema $schema): array
    {
        return [
            'response' => $schema
                ->string()
                ->required()
                ->title('Response')
                ->description('Cinematic narration in Carroll\'s voice as an HTML string. Use <p> tags for paragraphs. Use <em> for italics in Carroll\'s style (sparingly). Use <strong> only for single impactful moments. 2–4 paragraphs. Second-person ("you") throughout. Write as Carroll, not as a narrator summarising Carroll.'),

            'choices' => $schema
                ->array()
                ->required()
                ->title('Choices')
                ->description('Exactly 3 suggested actions. Each begins with a strong verb. Make them surprising, Wonderland-specific, and tempting. They are suggestions — the player may type anything.')
                ->items(
                    $schema->string()->required()
                ),

            'advance_scene' => $schema
                ->boolean()
                ->required()
                ->title('Advance Scene')
                ->description('True only when the scene has naturally moved into clearly new territory — a new room, a new chapter\'s content, a new major character arrival. False when still in the same scene space.'),

            'scene_note' => $schema
                ->string()
                ->required()
                ->title('Scene Note')
                ->description('Short string naming where we are now, e.g. "Chapter I — The Hall of Doors" or "Chapter V — The Mad Tea-Party". Update when advance_scene is true, carry forward unchanged when false.'),

            'world_update' => $schema->object([
                'size_condition' => $schema
                    ->string()
                    ->required()
                    ->title('Size Condition')
                    ->description('Alice\'s current size. One of: "normal", "tiny (ten inches)", "enormous (filling the room)", "growing", "shrinking", or empty string if unchanged from last turn.'),

                'items' => $schema
                    ->array()
                    ->required()
                    ->title('Items')
                    ->description('Complete list of all items Alice currently holds. Carry forward from previous state plus any gained this turn minus any lost this turn.')
                    ->items($schema->string()->required()),

                'location' => $schema
                    ->string()
                    ->required()
                    ->title('Location')
                    ->description('Current sub-location within the scene, e.g. "hall of doors", "beside the mushroom", "at the tea-table". Update when Alice moves.'),

                'notes' => $schema
                    ->array()
                    ->required()
                    ->title('Notes')
                    ->description('New facts discovered this turn through Alice\'s own action — things she tested, creature behaviours she triggered, hidden details she found. Empty array if nothing new was discovered.')
                    ->items($schema->string()->required()),
            ])
                ->required()
                ->withoutAdditionalProperties()
                ->title('World Update')
                ->description('Current world state after this turn. Every field is required.'),
        ];
    }
}
