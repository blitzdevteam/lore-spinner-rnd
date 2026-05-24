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

/**
 * Pipeline Upgrade V2 — Deliverable 7: IP Trimming Agent.
 *
 * Runs ONCE per IP, BEFORE Format Detection. Performs surgical reduction
 * of the source text while preserving every element the rest of the
 * adaptation pipeline needs (dialogue, character-revealing action, world
 * rules, emotional turning points, forward-referenced objects/NPCs/locations,
 * first appearances).
 *
 * Output feeds:
 *   - Format Detection / Phase 1 IP Audit / Phase 2+ — use the trimmed source.
 *   - Voice Lock — uses the FULL ORIGINAL source (NOT the trimmed version).
 *   - Phase 2 StoryGuard Canon Extraction — uses World Rules from Task 2.
 *
 * Temperature is low (0.3) because triage decisions should be conservative.
 * Timeout extended (600s) because long novels need real reading time.
 */
#[Model('gpt-5.2')]
#[Temperature(0.3)]
#[Timeout(600)]
class IpTrimmingAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.ip-trimming.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $turningPointSchema = $schema->object([
            'reference' => $schema->string()->required()->title('Reference')->description('Page or chapter reference, e.g. "Ch.3" or "p.47".'),
            'description' => $schema->string()->required()->title('Description')->description('One sentence: what happens and why it matters.'),
        ])->required()->withoutAdditionalProperties();

        $worldRuleSchema = $schema->object([
            'rule' => $schema->string()->required()->title('Rule'),
            'evidence' => $schema->string()->required()->title('Evidence')->description('Page or chapter reference where the rule is established.'),
        ])->required()->withoutAdditionalProperties();

        $triageEntrySchema = $schema->object([
            'reference' => $schema->string()->required()->title('Reference')->description('Chapter / scene / page identifier.'),
            'classification' => $schema->string()->required()->title('Classification')->description('PRESERVE or TRIM.'),
            'note' => $schema->string()->required()->title('Note')->description('One sentence: what it contains and why preserved or trimmed.'),
        ])->required()->withoutAdditionalProperties();

        $conversionNoteSchema = $schema->object([
            'reference' => $schema->string()->required()->title('Reference'),
            'original_content_summary' => $schema->string()->required()->title('Original Content Summary'),
            'conversion_type' => $schema->string()->required()->title('Conversion Type')->description('EXPLORABLE ENVIRONMENT / NPC DIALOGUE / DISCOVERABLE LORE / WORLD-EXPLORATION REWARD / EMOTIONAL DISCOVERY.'),
            'conversion_instruction' => $schema->string()->required()->title('Conversion Instruction'),
            'information_to_preserve' => $schema->string()->required()->title('Information To Preserve'),
        ])->required()->withoutAdditionalProperties();

        return [
            'story_spine' => $schema->object([
                'protagonist' => $schema->string()->required()->title('Protagonist'),
                'dramatic_question' => $schema->string()->required()->title('Dramatic Question'),
                'world' => $schema->string()->required()->title('World')->description('One sentence: where and when, plus the single most important world rule.'),
                'major_turning_points' => $schema->array()->required()->title('Major Turning Points')->items($turningPointSchema),
                'climax' => $turningPointSchema->title('Climax'),
                'resolution' => $turningPointSchema->title('Resolution'),
                'irreversible_events' => $schema->array()->required()->title('Irreversible Events')->items(
                    $schema->object([
                        'event' => $schema->string()->required()->title('Event'),
                        'why_fixed' => $schema->string()->required()->title('Why Fixed'),
                    ])->required()->withoutAdditionalProperties()
                ),
            ])->required()->withoutAdditionalProperties()->title('Story Spine'),

            'world_rules' => $schema->object([
                'physics_technology' => $schema->array()->required()->title('Physics / Technology')->items($worldRuleSchema),
                'creatures_entities' => $schema->array()->required()->title('Creatures / Entities')->items($worldRuleSchema),
                'geography_locations' => $schema->array()->required()->title('Geography / Locations')->items($worldRuleSchema),
                'social_systems' => $schema->array()->required()->title('Social Systems')->items($worldRuleSchema),
                'what_cannot_exist' => $schema->array()->required()->title('What Cannot Exist')->items(
                    $schema->object([
                        'thing' => $schema->string()->required()->title('Thing'),
                        'why' => $schema->string()->required()->title('Why')->description('Based on world rules — explain why this would break the world.'),
                    ])->required()->withoutAdditionalProperties()
                ),
            ])->required()->withoutAdditionalProperties()->title('World Rules'),

            'content_triage_log' => $schema->array()->required()->title('Content Triage Log')->items($triageEntrySchema),

            'interactive_conversion_notes' => $schema->array()->required()->title('Interactive Conversion Notes')->items($conversionNoteSchema),

            'trimmed_source_text' => $schema->object([
                'original_length_estimate' => $schema->string()->required()->title('Original Length Estimate')->description('Word count or page count.'),
                'trimmed_length_estimate' => $schema->string()->required()->title('Trimmed Length Estimate'),
                'reduction_percentage' => $schema->string()->required()->title('Reduction Percentage')->description('Target 25-45%.'),
                'text' => $schema->string()->required()->title('Trimmed Source Text')->description('Complete trimmed source with TRIM MARKER lines inserted at every cut point. Preserves all original formatting and exact preserved prose.'),
            ])->required()->withoutAdditionalProperties()->title('Trimmed Source Text'),
        ];
    }
}
