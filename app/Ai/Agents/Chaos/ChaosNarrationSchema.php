<?php

declare(strict_types=1);

namespace App\Ai\Agents\Chaos;

use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * JSON schema for the chaos narration turn response.
 *
 * Core principle: AI controls narration, pacing, and movement INSIDE the
 * active session. Runtime controls only which session is loaded and the
 * boundary between sessions. There is no advance_scene, no scene_note,
 * no suggested_playhead — the AI is given the full session script and
 * trusted to move through it naturally.
 *
 * This used to live on per-model agent classes that all wrapped the same
 * schema. The controller now calls Prism directly, so the schema lives in
 * one place and the agent shells are gone.
 */
final class ChaosNarrationSchema
{
    /**
     * Build the chaos narration JSON schema array.
     *
     * @return array<string, mixed>
     */
    public static function definition(JsonSchema $schema): array
    {
        return [
            'response' => $schema
                ->string()
                ->required()
                ->title('Response')
                ->description('Cinematic narration in Carroll\'s voice as an HTML string. Use <p> tags for paragraphs. Use <em> for italics (sparingly, Carroll-style). Use <strong> only for a single impactful moment. 2–4 paragraphs. Second-person ("you") throughout. Write AS Carroll, not as a narrator summarising Carroll.'),

            'choices' => $schema
                ->array()
                ->required()
                ->title('Choices')
                ->description('Exactly 3 suggested actions. Each begins with a strong verb. Surprising, Wonderland-specific, tempting. The player may type anything — these are suggestions, not constraints.')
                ->items(
                    $schema->string()->required()
                ),

            'session_complete' => $schema
                ->boolean()
                ->required()
                ->title('Session Complete')
                ->description('True ONLY when the current session has reached its natural narrative close — when its dramatic question has resolved and the seed for the next session has been planted in the narration. False on every other turn. The runtime, not the narrator, owns the technical transition to the next session.'),

            'state_delta' => $schema->object([
                'conditions' => $schema
                    ->array()
                    ->required()
                    ->title('Conditions')
                    ->description('Complete current list of Alice\'s active physical/mental conditions (e.g. "tiny (ten inches)", "enormous", "soaked", "indignant", "carrying the Rabbit\'s gloves"). Carry forward from previous turn, then add new and remove resolved. Empty array if none.')
                    ->items($schema->string()->required()),

                'items' => $schema
                    ->array()
                    ->required()
                    ->title('Items')
                    ->description('Complete current list of items Alice holds. Carry forward from previous state, plus any gained this turn, minus any lost. Empty array if she holds nothing.')
                    ->items($schema->string()->required()),

                'location' => $schema
                    ->string()
                    ->required()
                    ->title('Location')
                    ->description('Current sub-location, e.g. "riverbank", "falling", "hall of doors", "beside the glass table". Update when Alice moves. Empty string if unchanged.'),

                'relationships' => $schema
                    ->array()
                    ->required()
                    ->title('Relationships')
                    ->description('Notable shifts in how Wonderland characters regard Alice this turn (e.g. "White Rabbit: startled, fled", "Mouse: offended"). Empty array if no relationship changed.')
                    ->items($schema->string()->required()),

                'knowledge' => $schema
                    ->array()
                    ->required()
                    ->title('Knowledge')
                    ->description('Durable facts Alice has now learned about Wonderland from her own action (e.g. "Drinking labelled bottles changes her size", "The garden lies beyond a fifteen-inch door"). Empty array if nothing new was learned.')
                    ->items($schema->string()->required()),

                'notes' => $schema
                    ->array()
                    ->required()
                    ->title('Notes')
                    ->description('Player-created or emergent facts that are not knowledge or relationships but should survive turns (e.g. "Alice released a strange Nothing from a forbidden jar"). Empty array if none.')
                    ->items($schema->string()->required()),
            ])
                ->required()
                ->withoutAdditionalProperties()
                ->title('State Delta')
                ->description('The full persistent world state AFTER this turn. The runtime stores this and passes it back next turn. Every field is required; carry forward what has not changed.'),

            'session_memory_update' => $schema
                ->string()
                ->required()
                ->title('Session Memory Update')
                ->description('A single short natural-language sentence describing the most narratively important thing that happened this turn — what should be remembered if the session were resumed cold. Empty string if nothing notable happened. Example: "Alice opened the forbidden jar and released a strange Nothing that now owes her a favour."'),
        ];
    }
}
