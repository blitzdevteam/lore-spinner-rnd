<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Model('gpt-5.2')]
#[Temperature(0.85)]
#[Timeout(60)]
class NarrationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Create a new narration agent instance.
     *
     * @param  string  $customInstructions  Runtime-rendered system prompt with story data + event context.
     */
    public function __construct(
        private string $customInstructions,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return $this->customInstructions;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'response' => $schema
                ->string()
                ->required()
                ->title('Response')
                ->description('Cinematic narrative as HTML. Use <p> tags for paragraphs, <em> for emphasis, <strong> for impactful moments. Immersive, atmospheric, second-person. 2-4 paragraphs.'),
            'choices' => $schema
                ->array()
                ->required()
                ->title('Choices')
                ->description('Exactly 3 short actionable choices. Each starts with a strong verb. Ordered by forward momentum: most forward, moderate, least forward (but still changes state).')
                ->items(
                    $schema
                        ->string()
                        ->required()
                        ->title('Choice')
                        ->description('A single concrete, actionable choice starting with a strong verb.')
                ),
            'advance_event' => $schema
                ->boolean()
                ->required()
                ->title('Advance Event')
                ->description('True when the current event\'s core dramatic beats have been sufficiently explored and the player\'s action naturally exits or completes the scene. False when the player is still engaging within the current event.'),

            'input_classification' => $schema
                ->string()
                ->required()
                ->title('Input Classification')
                ->description("How you classified the player's most recent action. Exactly one of: 'expressive' (changes tone or delivery only), 'branch_aligned' (matches an authored branching choice slot), 'emergent' (meaningful continuity shift outside the authored branch), 'unsupported' (folded into nearest safe outcome), 'opening' (use only on turn 1 of a session)."),

            'mapped_choice_id' => $schema
                ->string()
                ->required()
                ->title('Mapped Choice ID')
                ->description("When input_classification === 'branch_aligned' AND a pre-authored branching choice slot was active, the choice_id (e.g. 'S1_C1'). Empty string otherwise."),

            'mapped_option' => $schema
                ->string()
                ->required()
                ->title('Mapped Option')
                ->description("When input_classification === 'branch_aligned' AND a pre-authored branching choice slot was active, exactly one of 'A', 'B', or 'C'. Empty string otherwise."),

            'state_delta' => $schema->object([
                'objects_acquired' => $schema
                    ->array()
                    ->required()
                    ->title('Objects Acquired')
                    ->description('Items the player picked up, was given, or otherwise gained possession of this turn. Empty array if none.')
                    ->items(
                        $schema->object([
                            'name' => $schema
                                ->string()
                                ->required()
                                ->title('Name')
                                ->description('Canonical short noun phrase, e.g. "small bottle", "golden key", "backpack".'),
                            'qualifier' => $schema
                                ->string()
                                ->required()
                                ->title('Qualifier')
                                ->description('Short descriptor or state, e.g. "labelled DRINK ME", "rusted". Empty string if none.'),
                            'contains' => $schema
                                ->array()
                                ->required()
                                ->title('Contains')
                                ->description('Names of nested objects (e.g. backpack contains ["phone", "notebook"]). Empty array if none.')
                                ->items($schema->string()->required()),
                        ])->required()->withoutAdditionalProperties()
                    ),
                'objects_lost' => $schema
                    ->array()
                    ->required()
                    ->title('Objects Lost')
                    ->description('Names matching prior acquired objects no longer in the player\'s possession. Empty array if none.')
                    ->items($schema->string()->required()),
                'objects_transformed' => $schema
                    ->array()
                    ->required()
                    ->title('Objects Transformed')
                    ->description('Existing objects whose qualifier changed (e.g. bottle becomes "empty" after drinking). Empty array if none.')
                    ->items(
                        $schema->object([
                            'name' => $schema->string()->required()->title('Name'),
                            'new_qualifier' => $schema->string()->required()->title('New Qualifier'),
                        ])->required()->withoutAdditionalProperties()
                    ),
                'conditions_added' => $schema
                    ->array()
                    ->required()
                    ->title('Conditions Added')
                    ->description('New conditions affecting the player (e.g. shrunk, wet, exhausted). Empty array if none.')
                    ->items(
                        $schema->object([
                            'name' => $schema->string()->required()->title('Name'),
                            'note' => $schema
                                ->string()
                                ->required()
                                ->title('Note')
                                ->description('Short context, e.g. "10 inches tall after drinking". Empty string if none.'),
                        ])->required()->withoutAdditionalProperties()
                    ),
                'conditions_removed' => $schema
                    ->array()
                    ->required()
                    ->title('Conditions Removed')
                    ->description('Names of conditions no longer active. Empty array if none.')
                    ->items($schema->string()->required()),
                'location_changed' => $schema
                    ->string()
                    ->required()
                    ->title('Location Changed')
                    ->description('New sub-location within the current event (e.g. "doorway", "rooftop"). Empty string if no movement.'),
                'knowledge_gained' => $schema
                    ->array()
                    ->required()
                    ->title('Knowledge Gained')
                    ->description('Discrete facts the player now knows. Each fact is a short declarative sentence. Empty array if none.')
                    ->items($schema->string()->required()),
                'relationship_changes' => $schema
                    ->array()
                    ->required()
                    ->title('Relationship Changes')
                    ->description('Disposition shifts of named characters toward the player. Empty array if none.')
                    ->items(
                        $schema->object([
                            'character' => $schema->string()->required()->title('Character'),
                            'shift' => $schema
                                ->string()
                                ->required()
                                ->title('Shift')
                                ->description('Short phrase, e.g. "more agitated", "warmed slightly", "openly hostile".'),
                        ])->required()->withoutAdditionalProperties()
                    ),
                'tracked_path_update' => $schema
                    ->array()
                    ->required()
                    ->title('Tracked Path Update')
                    ->description('Tracked dimension shifts this turn. Empty array if no shift.')
                    ->items(
                        $schema->object([
                            'dimension' => $schema
                                ->string()
                                ->required()
                                ->title('Dimension')
                                ->description('Name of the tracked dimension, matching the design layer (e.g. "curiosity_vs_caution").'),
                            'path' => $schema
                                ->string()
                                ->required()
                                ->title('Path')
                                ->description('Exactly one of "A", "B", or "C".'),
                        ])->required()->withoutAdditionalProperties()
                    ),
                'flags_set' => $schema
                    ->array()
                    ->required()
                    ->title('Flags Set')
                    ->description('Write-once flags raised by this turn (e.g. "tried_drink_me_bottle"). Empty array if none.')
                    ->items($schema->string()->required()),
            ])
                ->required()
                ->withoutAdditionalProperties()
                ->title('State Delta')
                ->description('Structured world-state changes from this turn. Every top-level key is required; use empty arrays / empty strings for "no change in this category". The runtime applies these cumulatively to the persisted world_state column.'),
        ];
    }
}
