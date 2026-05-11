<?php

declare(strict_types=1);

namespace App\Ai\Agents\WriterLab;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Editorial compression agent for Writer Lab.
 *
 * Distinct from the NarrationAgent (performer). This agent is an editor —
 * its job is to merge multiple source events into a single compact prose block
 * while preserving canonical anchors and staying consistent with the established
 * adaptation layer (cold open tone, beat structure, authored choice placement).
 *
 * Temperature is intentionally lower than the narrator — editorial work requires
 * precision, not expressive range.
 */
#[Model('gpt-5.2')]
#[Temperature(0.55)]
#[Timeout(90)]
class EventCombinerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return view('ai.agents.writer-lab.event-combiner.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'rewritten_content' => $schema
                ->string()
                ->required()
                ->title('Rewritten Content')
                ->description('The compact prose rewrite of all source events combined. Plain prose — no screenplay formatting, no second-person narration, no player-facing text. Third-person body text that the narrator will later render interactively. 1–3 paragraphs maximum.'),

            'derived_objectives' => $schema
                ->string()
                ->required()
                ->title('Derived Objectives')
                ->description('A single past-tense factual sentence describing what canonically happened in this combined block. Mirrors the format of event.objectives. E.g. "Alice followed the White Rabbit through the hall and discovered a small door hidden behind a curtain."'),

            'derived_attributes' => $schema
                ->array()
                ->required()
                ->title('Derived Attributes')
                ->description('Canonical objects, characters, and named locations that appear in the combined block. One item per string. This becomes the new event.attributes for runtime state tracking.')
                ->items($schema->string()->required()),

            'beat_type' => $schema
                ->string()
                ->required()
                ->title('Beat Type')
                ->description('The editorial beat classification for this combined block. One of: setup, escalation, breath, twist, resolution. If source events span multiple beats, use the dominant beat type.'),

            'requires_choice' => $schema
                ->boolean()
                ->required()
                ->title('Requires Choice')
                ->description('True if any source event was a branching or expressive choice beat per the session_choice_design. False only if all source events were purely cinematic flow moments with no authored choice slot. When in doubt, use true.'),

            'canonical_anchors' => $schema
                ->array()
                ->required()
                ->title('Canonical Anchors')
                ->description('The facts from the source events that MUST survive in rewritten_content. Every item here must be verifiable in the output. Used as a safety checklist by the writer UI.')
                ->items($schema->string()->required()),
        ];
    }
}
