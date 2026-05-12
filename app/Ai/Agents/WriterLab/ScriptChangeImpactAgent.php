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
 * Comprehensive adaptation impact analysis for a single event script edit.
 *
 * Takes old content, new content, the event's current extracted metadata, and
 * the session's full adaptation layer (beat_map, session_choice_design,
 * choice_consequence_map, next_session_awareness) then returns structured
 * change suggestions for every stale layer — writer can accept or dismiss each.
 */
#[Model('gpt-5.2')]
#[Temperature(0.35)]
#[Timeout(120)]
class ScriptChangeImpactAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return view('ai.agents.writer-lab.script-change-impact.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return [

            // ── Overall verdict ────────────────────────────────────────────
            'severity' => $schema
                ->string()
                ->required()
                ->title('Severity')
                ->description('"clean" = nothing meaningful needs updating. "minor" = surface language touch-ups only. "moderate" = 2-3 layers need revision. "significant" = the edit shifts a dramatic axis and multiple layers need substantive rewrite.'),

            'summary' => $schema
                ->string()
                ->required()
                ->title('Summary')
                ->description('1-2 sentences explaining what the edit changed in terms of character intention, tone, or dramatic content — and why that cascades into the adaptation layers.'),

            // ── Event metadata layer ───────────────────────────────────────
            'objectives_needs_update' => $schema
                ->boolean()
                ->required()
                ->title('Objectives Needs Update')
                ->description('True if the event objectives (the past-tense factual summary of what happened) no longer accurately describe the edited content.'),

            'objectives_revised' => $schema
                ->string()
                ->required()
                ->title('Objectives Revised')
                ->description('The revised objectives sentence for this event. If objectives_needs_update is false, return the original unchanged.'),

            'attributes_needs_update' => $schema
                ->boolean()
                ->required()
                ->title('Attributes Needs Update')
                ->description('True if the event attributes (canonical objects, characters, locations referenced) changed — new items added or old ones removed.'),

            'attributes_revised' => $schema
                ->array()
                ->required()
                ->title('Attributes Revised')
                ->description('The revised list of canonical attributes for this event. If attributes_needs_update is false, return the original unchanged.')
                ->items($schema->string()->required()),

            // ── Beat map layer ─────────────────────────────────────────────
            'beat_map_needs_update' => $schema
                ->boolean()
                ->required()
                ->title('Beat Map Needs Update')
                ->description('True if the beat_map entry for this event (moment description, beat_type, or choice_arrives text) is no longer accurate.'),

            'beat_moment_revised' => $schema
                ->string()
                ->required()
                ->title('Beat Moment Revised')
                ->description('The revised moment description (one sentence) for the beat_map entry. If beat_map_needs_update is false, return the original unchanged.'),

            'beat_type_revised' => $schema
                ->string()
                ->required()
                ->title('Beat Type Revised')
                ->description('The revised beat type for this event: SETUP, ESCALATION, BREATH, TWIST, or RESOLUTION. If beat_map_needs_update is false, return the original unchanged.'),

            // ── Choice design layer ────────────────────────────────────────
            'choice_design_needs_update' => $schema
                ->boolean()
                ->required()
                ->title('Choice Design Needs Update')
                ->description('True if a branching choice slot in this session has a source_moment that references this event, and the edit changes how that choice feels earned.'),

            'choice_slot_affected' => $schema
                ->string()
                ->required()
                ->title('Choice Slot Affected')
                ->description('Which branching choice slot is most affected: branching_choice_1, branching_choice_2, branching_choice_3, or "none".'),

            'choice_question_revised' => $schema
                ->string()
                ->required()
                ->title('Choice Question Revised')
                ->description('Revised player-facing choice question. Return empty string if choice_design_needs_update is false.'),

            'choice_option_a_revised' => $schema
                ->string()
                ->required()
                ->title('Choice Option A Revised')
                ->description('Revised Option A text. Return empty string if choice_design_needs_update is false.'),

            'choice_option_b_revised' => $schema
                ->string()
                ->required()
                ->title('Choice Option B Revised')
                ->description('Revised Option B text. Return empty string if choice_design_needs_update is false.'),

            'choice_option_c_revised' => $schema
                ->string()
                ->required()
                ->title('Choice Option C Revised')
                ->description('Revised Option C text. Return empty string if choice_design_needs_update is false.'),

            'choice_tracked_dimension' => $schema
                ->string()
                ->required()
                ->title('Choice Tracked Dimension')
                ->description('The what_this_choice_tracks dimension. Usually unchanged. Return empty string if choice_design_needs_update is false.'),

            // ── Consequence map layer ──────────────────────────────────────
            'consequence_map_needs_review' => $schema
                ->boolean()
                ->required()
                ->title('Consequence Map Needs Review')
                ->description('True if the edit shifts the emotional or behavioral axis enough that the consequence map entries (what A/B/C do to world state) may no longer calibrate correctly. This is a flag, not a full rewrite.'),

            'consequence_map_note' => $schema
                ->string()
                ->required()
                ->title('Consequence Map Note')
                ->description('One sentence describing which consequence entries may need re-calibrating and why. Empty string if consequence_map_needs_review is false.'),

            'consequence_option_a_revised' => $schema
                ->string()
                ->required()
                ->title('Consequence Option A Revised')
                ->description('Revised one-sentence consequence the world should now register if the player picks the choice_slot_affected\'s Option A, given the edited script. Empty string if consequence_map_needs_review is false OR choice_slot_affected is "none".'),

            'consequence_option_b_revised' => $schema
                ->string()
                ->required()
                ->title('Consequence Option B Revised')
                ->description('Revised one-sentence consequence for Option B. Empty string if not applicable.'),

            'consequence_option_c_revised' => $schema
                ->string()
                ->required()
                ->title('Consequence Option C Revised')
                ->description('Revised one-sentence consequence for Option C. Empty string if not applicable.'),

            // ── Cross-session layer ────────────────────────────────────────
            'cross_session_concern' => $schema
                ->boolean()
                ->required()
                ->title('Cross Session Concern')
                ->description('True if this event\'s edit removes or changes a canonical anchor (character, object, planted seed) that a downstream session references in its cold open, session_choice_design, or next_session_awareness.'),

            'cross_session_note' => $schema
                ->string()
                ->required()
                ->title('Cross Session Note')
                ->description('Description of which downstream session is at risk and what specifically was planted that may now be disconnected. Empty string if cross_session_concern is false.'),

            'cross_session_seed_revised' => $schema
                ->string()
                ->required()
                ->title('Cross Session Seed Revised')
                ->description('Revised wording of the specific planted seed / anchor that downstream sessions reference, so that downstream awareness stays aligned with the edit. One short paragraph. Empty string if cross_session_concern is false.'),

            'cross_session_target_session' => $schema
                ->integer()
                ->required()
                ->title('Cross Session Target Session')
                ->description('The session_number of the downstream session that is affected. 0 if cross_session_concern is false or no specific downstream session is at risk.'),

        ];
    }
}
