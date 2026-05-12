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
 * Distinct from the NarrationAgent (performer). This agent is an editor whose
 * ONLY job is to glue multiple source events into a single tighter prose block
 * that reads as if the original author wrote it that way — same vocabulary,
 * same rhythm, same dialogue verbatim, just less connective tissue.
 *
 * Field derivation that downstream systems rely on (objectives, attributes,
 * beat_type, requires_choice) is NOT this agent's job — those are produced by
 * the same pipeline agents that populated the originals so the schema stays
 * aligned. This agent returns only the rewrite + the canonical anchor checklist
 * the writer UI uses for verification.
 *
 * Temperature is intentionally lower than the narrator — editorial work requires
 * precision, not expressive range.
 */
#[Model('gpt-5.2')]
#[Temperature(0.45)]
#[Timeout(120)]
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
                ->description('The compact prose rewrite of all source events combined. Plain prose — no screenplay formatting, no second-person narration, no player-facing text. Third-person body text that the narrator will later render interactively. 1–3 paragraphs maximum. Author voice must be preserved — sentence rhythm, vocabulary, and dialogue all match the source. No invented phrasing, no AI clichés.'),

            'canonical_anchors' => $schema
                ->array()
                ->required()
                ->title('Canonical Anchors')
                ->description('The facts from the source events that MUST survive in rewritten_content. Every item here must be verifiable in the output. Used as a safety checklist by the writer UI.')
                ->items($schema->string()->required()),
        ];
    }
}
