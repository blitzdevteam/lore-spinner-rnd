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
 * Pipeline Upgrade V2.3 — Entry Point Diagnosis (Deliverable 10).
 *
 * Produces six structured outputs:
 *   Task 1: entry_point (chosen source moment, rubric scores, adjustment, cut point)
 *   Task 2: protagonist_introduction (earned identity approach)
 *   Task 3: situation_stakes_world (economy — established + deferred)
 *   Task 4: first_choice_spec ★ consumed by ChoiceDesignJob / D4 Task 1
 *   Task 5: cold_open prose (→ D8 Section 13 via ChaosEngineService)
 *   Task 6: emotional_promise (→ ChoiceDesignJob / D4)
 *
 * Backward-compatible runtime keys preserved: editorial_diagnosis,
 * format_specific_cut, cold_open, emotional_promise, start_event_position.
 */
#[Model('gpt-5.4')]
#[Temperature(0.6)]
#[Timeout(180)]
class EntryPointDiagnosisAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.entry-point-diagnosis.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            // --- Task 1: Entry Point (D10) ---
            'entry_point' => $schema->object([
                'chosen_moment'          => $schema->string()->required()->title('Chosen Moment')
                    ->description('The source moment selected as the entry point.'),
                'rubric_scores'          => $schema->string()->required()->title('Rubric Scores')
                    ->description('Body/tension/threshold/exposition-debt/core-stakes-proximity — one line each.'),
                'entry_point_adjustment' => $schema->string()->required()->title('Entry Point Adjustment')
                    ->description('"literal opening" or "moved forward to: ___ — and why."'),
                'cut_point'              => $schema->string()->required()->title('Cut Point')
                    ->description('Where the cold open ends and hands off to Phase 4 SETUP beat.'),
            ])->required()->withoutAdditionalProperties()->title('Entry Point — Task 1'),

            // --- Task 2: Protagonist Introduction (D10) ---
            'protagonist_introduction' => $schema->object([
                'identity_reveal_approach' => $schema->string()->required()->title('Identity Reveal Approach')
                    ->description('Body-first beat, weight-moment for the name, reflex/instinct that conveys role, one loaded detail.'),
                'reveal_lines'             => $schema->string()->required()->title('Reveal Lines')
                    ->description('The actual in-voice line(s) that name the protagonist at a moment of weight.'),
                'resume_dump_check'        => $schema->string()->required()->title('Resume Dump Check')
                    ->description('Confirmation that no stacked-label exposition is used.'),
            ])->required()->withoutAdditionalProperties()->title('Protagonist Introduction — Task 2'),

            // --- Task 3: Situation, Stakes, World (D10) ---
            'situation_stakes_world' => $schema->object([
                'established' => $schema->string()->required()->title('Established')
                    ->description('Where/when, the pressing now, 1-2 active world rules, the stake — as present-tense pressure.'),
                'deferred'    => $schema->string()->required()->title('Deferred')
                    ->description('What is intentionally withheld for later beats.'),
            ])->required()->withoutAdditionalProperties()->title('Situation Stakes World — Task 3'),

            // --- Task 4: First-Choice Spec ★ (D10 → consumed by D4 Task 1) ---
            'first_choice_spec' => $schema->object([
                'setup_prose'      => $schema->string()->required()->title('Setup Prose')
                    ->description('2-3 sentences of cold-open prose immediately before the question — in the author\'s voice.'),
                'threshold_stake'  => $schema->string()->required()->title('Threshold/Stake')
                    ->description('The core stake or threshold this choice turns on — tied to protagonist\'s central want/threat.'),
                'question'         => $schema->string()->required()->title('Question')
                    ->description('The choice question in second person.'),
                'option_1'         => $schema->string()->required()->title('Option 1')
                    ->description('One sentence — a genuine human value.'),
                'option_1_alignment' => $schema->string()->required()->title('Option 1 Alignment')
                    ->description('chaotic | lawful | neutral'),
                'option_1_tracks'  => $schema->string()->required()->title('Option 1 Tracks')
                    ->description('Branch dimension from Phase 2 this option tracks.'),
                'option_1_value'   => $schema->string()->required()->title('Option 1 Value')
                    ->description('The human value this option represents.'),
                'option_2'         => $schema->string()->required()->title('Option 2')
                    ->description('One sentence — a genuine human value.'),
                'option_2_alignment' => $schema->string()->required()->title('Option 2 Alignment')
                    ->description('chaotic | lawful | neutral'),
                'option_2_tracks'  => $schema->string()->required()->title('Option 2 Tracks')
                    ->description('Branch dimension from Phase 2 this option tracks.'),
                'option_2_value'   => $schema->string()->required()->title('Option 2 Value')
                    ->description('The human value this option represents.'),
                'option_3_unexpected' => $schema->string()->required()->title('Option 3 (Unexpected)')
                    ->description('The third path nobody expects — one sentence.'),
                'option_3_alignment'  => $schema->string()->required()->title('Option 3 Alignment')
                    ->description('chaotic | lawful | neutral'),
                'option_3_tracks'     => $schema->string()->required()->title('Option 3 Tracks')
                    ->description('Branch dimension from Phase 2 this option tracks.'),
                'option_3_value'      => $schema->string()->required()->title('Option 3 Value')
                    ->description('The human value this option represents.'),
                'not_a_tutorial'      => $schema->string()->required()->title('Not a Tutorial')
                    ->description('One line: how this choice engages core stakes and defines identity — not a side encounter.'),
            ])->required()->withoutAdditionalProperties()->title('First-Choice Spec — Task 4 ★ consumed by D4 Task 1'),

            // --- Task 5: Cold Open prose (Task 5 / D8 Section 13) ---
            'cold_open' => $schema
                ->string()
                ->required()
                ->title('Cold Open')
                ->description('Second-person present tense cold open prose, 120-180 words. Written in the author\'s voice (Voice Anchor). Ends at the first choice question. Sensory grounding within first 50 words.'),

            // --- Task 6: Emotional Promise (→ D4) ---
            'emotional_promise' => $schema
                ->string()
                ->required()
                ->title('Emotional Promise')
                ->description('One sentence: "The emotional promise of this cold open is: [NOUN]. A user arrives feeling [ADJECTIVE] and wanting to [VERB]."'),

            // --- Freeform input hint (→ chat-bar placeholder, session 1 first move only) ---
            'freeform_input_hint' => $schema
                ->string()
                ->required()
                ->title('Freeform Input Hint')
                ->description('Max 80 chars. A story-native, present-tense line that appears as the chat-bar placeholder when the player first faces the three choices. Invites a custom move beyond the buttons. Captures the spirit of the unexpected third option without quoting it verbatim. No UI language ("Type here", "Enter text"). No game-mechanical words. Must tempt, not instruct. Written in the world\'s tone.'),

            // --- Preserved runtime keys (backward compat) ---
            'editorial_diagnosis' => $schema
                ->string()
                ->required()
                ->title('Editorial Diagnosis')
                ->description('Full editorial diagnosis explaining what was cut and why (from entry point selection).'),

            'format_specific_cut' => $schema
                ->object([
                    'cut_point'          => $schema->string()->required()->title('Cut Point'),
                    'original_before_cut' => $schema->string()->required()->title('Original Before Cut'),
                    'cut_eliminates'     => $schema->string()->required()->title('Cut Eliminates'),
                    'must_reintroduce'   => $schema->string()->required()->title('Must Reintroduce'),
                ])->required()->withoutAdditionalProperties()->title('Format-Specific Cut'),

            'start_event_position' => $schema
                ->integer()
                ->required()
                ->title('Start Event Position')
                ->description('The integer event position number where this session should begin. Must be one of the story-global Event numbers in the events list.'),
        ];
    }
}
