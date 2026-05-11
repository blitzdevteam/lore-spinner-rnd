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
 * Given an edited event's new content and the existing session_choice_design,
 * suggests updated choice question + A/B/C options that align with the rewrite.
 *
 * This agent is intentionally narrow — it only re-aligns one choice slot at a time
 * and outputs structured diffs so the writer can Accept or Dismiss.
 */
#[Model('gpt-5.2')]
#[Temperature(0.45)]
#[Timeout(60)]
class ChoiceAlignmentAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return view('ai.agents.writer-lab.choice-alignment.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'choice_slot' => $schema
                ->string()
                ->required()
                ->title('Choice Slot')
                ->description('Which branching choice slot this suggestion applies to: branching_choice_1, branching_choice_2, or branching_choice_3.'),

            'suggested_question' => $schema
                ->string()
                ->required()
                ->title('Suggested Question')
                ->description('The revised player-facing choice question, rewritten to feel earned by the edited event content.'),

            'suggested_option_a' => $schema
                ->string()
                ->required()
                ->title('Suggested Option A')
                ->description('Revised text for Option A. Preserve the original choice dimension — only update the surface language to match the new content.'),

            'suggested_option_b' => $schema
                ->string()
                ->required()
                ->title('Suggested Option B')
                ->description('Revised text for Option B.'),

            'suggested_option_c' => $schema
                ->string()
                ->required()
                ->title('Suggested Option C')
                ->description('Revised text for Option C.'),

            'tracked_dimension' => $schema
                ->string()
                ->required()
                ->title('Tracked Dimension')
                ->description('The what_this_choice_tracks dimension. Usually unchanged — only update if the edit fundamentally shifts the emotional axis of the choice.'),

            'rationale' => $schema
                ->string()
                ->required()
                ->title('Rationale')
                ->description('One sentence explaining why the original choice question/options needed updating and what the edit changed.'),

            'changes_significant' => $schema
                ->boolean()
                ->required()
                ->title('Changes Significant')
                ->description('True if the suggested changes are meaningfully different from the original. False if the original choice design already fits the edited content well and changes are cosmetic only.'),
        ];
    }
}
