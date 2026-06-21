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
 * Pipeline Upgrade V2.3 — Voice Lock per-chapter pass (Deliverable 1A v2 or 1B v3 fragment).
 *
 * V2.3 additions (both branches):
 *   voice_anchor_candidates[]  — candidate exemplar passages (merge → top-level voice_anchor)
 *   anchor_card_candidates[]   — ABSOLUTE/HIGH binary/local rule candidates
 *   self_check_candidates[]    — discrete/local check step candidates
 * Novelist branch also adds:
 *   documented_narrator_techniques[] — carve-outs from bans (cognitive verbs, FID, prolepsis…)
 */
#[Model('gpt-5.4')]
#[Temperature(0.2)]
#[Timeout(300)]
class VoiceLockChapterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private ?string $detectedFormat = null,
    ) {}

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        $view = VoiceLockSchema::isScreenwriter($this->detectedFormat)
            ? 'ai.agents.adaptation.voice-lock.chapter-system-prompt-screenwriter'
            : 'ai.agents.adaptation.voice-lock.chapter-system-prompt-novelist';

        return view($view)->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $emotionalMomentSchema = $schema->object([
            'register' => $schema->string()->required()->title('Register'),
            'quote' => $schema->string()->required()->title('Quote'),
            'technique' => $schema->string()->required()->title('Technique'),
            'rendering_method' => $schema->string()->required()->title('Rendering Method'),
        ])->required()->withoutAdditionalProperties();

        $collocationCandidateSchema = $schema->object([
            'pair' => $schema->string()->required()->title('Pair'),
            'quote' => $schema->string()->required()->title('Quote'),
            'ai_substitution' => $schema->string()->required()->title('AI Substitution'),
        ])->required()->withoutAdditionalProperties();

        $voiceObservationsFields = [
            'signature_techniques_observed' => $schema->array()->required()->title('Signature Techniques Observed')->items(
                $schema->object([
                    'name' => $schema->string()->required()->title('Name'),
                    'quote' => $schema->string()->required()->title('Quote'),
                    'technique_note' => $schema->string()->required()->title('Technique Note'),
                    'frequency_note' => $schema->string()->required()->title('Frequency Note'),
                ])->required()->withoutAdditionalProperties()
            ),
            'sentence_pattern_notes' => $schema->string()->required()->title('Sentence Pattern Notes'),
            'demonstrative_quotes' => $schema->array()->required()->title('Demonstrative Quotes')->items($schema->string()->required()),
            'diction_samples' => $schema->array()->required()->title('Diction Samples')->items($schema->string()->required()),
            'register_note' => $schema->string()->required()->title('Register Note'),
            'word_frequency_note' => $schema->string()->required()->title('Word Frequency Note'),
            'emotional_moments_observed' => $schema->array()->required()->title('Emotional Moments Observed')->items($emotionalMomentSchema),
            'collocation_candidates' => $schema->array()->required()->title('Collocation Candidates')->items($collocationCandidateSchema),
            'negative_space_candidates' => $schema->array()->required()->title('Negative Space Candidates')->items(
                $schema->object([
                    'technique' => $schema->string()->required()->title('Technique'),
                    'absence_evidence' => $schema->string()->required()->title('Absence Evidence'),
                ])->required()->withoutAdditionalProperties()
            ),
        ];

        if (VoiceLockSchema::isScreenwriter($this->detectedFormat)) {
            // Qualitative context fields (retained from 1B FINAL)
            $voiceObservationsFields['action_line_samples'] = $schema->array()->required()->title('Action Line Samples')->items($schema->string()->required());
            $voiceObservationsFields['action_line_metrics_note'] = $schema->string()->required()->title('Action Line Metrics Note');
            $voiceObservationsFields['dialogue_metrics_note'] = $schema->string()->required()->title('Dialogue Metrics Note');

            // --- 1B v2 / 1C: producer-neutral raw-count fields ---
            // Merge sums these across all chapter fragments BEFORE deriving percentages.
            // A future deterministic extractor can populate the same fields without
            // changing merge schema, voice_profile schema, or runtime rendering.
            $voiceObservationsFields['metric_counts'] = $schema->object([
                // Action-line denominators
                'action_line_count'       => $schema->number()->required()->title('Action Line Count'),
                'action_line_word_count'  => $schema->number()->required()->title('Action Line Word Count'),
                // Dialogue denominators
                'dialogue_speech_count'   => $schema->number()->required()->title('Dialogue Speech Count'),
                'dialogue_word_count'     => $schema->number()->required()->title('Dialogue Word Count'),
                // Punctuation (split action vs dialogue where measurable; estimate otherwise)
                'punctuation_counts' => $schema->object([
                    'period'      => $schema->number()->required()->title('Periods'),
                    'comma'       => $schema->number()->required()->title('Commas'),
                    'semicolon'   => $schema->number()->required()->title('Semicolons'),
                    'exclamation' => $schema->number()->required()->title('Exclamation Marks'),
                    'em_dash'     => $schema->number()->required()->title('Em Dashes'),
                    'question'    => $schema->number()->required()->title('Question Marks'),
                    'ellipsis'    => $schema->number()->required()->title('Ellipses'),
                ])->required()->withoutAdditionalProperties()->title('Punctuation Counts'),
                // Rhythm counts
                'fragment_line_count'     => $schema->number()->required()->title('Fragment Line Count — action lines ≤5 words'),
                'verb_first_line_count'   => $schema->number()->required()->title('Verb-First Line Count'),
                'ing_opening_line_count'  => $schema->number()->required()->title('-ing Opening Line Count'),
                // Line-length buckets (deliverable keys: 1_3w … 26_plus_w)
                'line_length_bucket_counts' => $schema->object([
                    '1_3w'       => $schema->number()->required()->title('1-3 Words'),
                    '4_5w'       => $schema->number()->required()->title('4-5 Words'),
                    '6_8w'       => $schema->number()->required()->title('6-8 Words'),
                    '9_12w'      => $schema->number()->required()->title('9-12 Words'),
                    '13_18w'     => $schema->number()->required()->title('13-18 Words'),
                    '19_25w'     => $schema->number()->required()->title('19-25 Words'),
                    '26_plus_w'  => $schema->number()->required()->title('26+ Words'),
                ])->required()->withoutAdditionalProperties()->title('Line Length Bucket Counts'),
                // Opener type counts
                'opener_type_counts' => $schema->object([
                    'article'        => $schema->number()->required()->title('Article Openers'),
                    'pronoun'        => $schema->number()->required()->title('Pronoun Openers'),
                    'character_name' => $schema->number()->required()->title('Character Name Openers'),
                    'verb'           => $schema->number()->required()->title('Verb Openers'),
                    'negation'       => $schema->number()->required()->title('Negation Openers'),
                    'preposition'    => $schema->number()->required()->title('Preposition Openers'),
                    'ing'            => $schema->number()->required()->title('-ing Openers'),
                    'all_caps'       => $schema->number()->required()->title('ALL CAPS Openers'),
                ])->required()->withoutAdditionalProperties()->title('Opener Type Counts'),
                // Word-length distribution
                'word_length_bucket_counts' => $schema->object([
                    'chars_1_3'    => $schema->number()->required()->title('1-3 Char Words'),
                    'chars_4_5'    => $schema->number()->required()->title('4-5 Char Words'),
                    'chars_6_8'    => $schema->number()->required()->title('6-8 Char Words'),
                    'chars_9_plus' => $schema->number()->required()->title('9+ Char Words'),
                ])->required()->withoutAdditionalProperties()->title('Word Length Bucket Counts'),
                // Beat counts
                'beat_count'              => $schema->number()->required()->title('Beat Count — 1-2 word standalone action-line beats'),
                // Scene closing
                'scene_closing_line_count' => $schema->number()->required()->title('Scene Closing Line Count'),
                'scene_closing_word_count' => $schema->number()->required()->title('Scene Closing Word Count'),
                'scene_closing_type_counts' => $schema->object([
                    'image'             => $schema->number()->required()->title('Image Closes'),
                    'action'            => $schema->number()->required()->title('Action Closes'),
                    'status'            => $schema->number()->required()->title('Status Closes'),
                    'dialogue_adjacent' => $schema->number()->required()->title('Dialogue-Adjacent Closes'),
                    'beat'              => $schema->number()->required()->title('Beat Closes'),
                ])->required()->withoutAdditionalProperties()->title('Scene Closing Type Counts'),
                // Rhythm transition matrix (ultra_short/short/medium/long buckets)
                // After each category: how many times each category followed
                'rhythm_transition_matrix_counts' => $schema->object([
                    'ultra_short' => $schema->object([
                        'ultra_short' => $schema->number()->required()->title('→ Ultra-Short'),
                        'short'       => $schema->number()->required()->title('→ Short'),
                        'medium'      => $schema->number()->required()->title('→ Medium'),
                        'long'        => $schema->number()->required()->title('→ Long'),
                    ])->required()->withoutAdditionalProperties()->title('After Ultra-Short'),
                    'short' => $schema->object([
                        'ultra_short' => $schema->number()->required()->title('→ Ultra-Short'),
                        'short'       => $schema->number()->required()->title('→ Short'),
                        'medium'      => $schema->number()->required()->title('→ Medium'),
                        'long'        => $schema->number()->required()->title('→ Long'),
                    ])->required()->withoutAdditionalProperties()->title('After Short'),
                    'medium' => $schema->object([
                        'ultra_short' => $schema->number()->required()->title('→ Ultra-Short'),
                        'short'       => $schema->number()->required()->title('→ Short'),
                        'medium'      => $schema->number()->required()->title('→ Medium'),
                        'long'        => $schema->number()->required()->title('→ Long'),
                    ])->required()->withoutAdditionalProperties()->title('After Medium'),
                    'long' => $schema->object([
                        'ultra_short' => $schema->number()->required()->title('→ Ultra-Short'),
                        'short'       => $schema->number()->required()->title('→ Short'),
                        'medium'      => $schema->number()->required()->title('→ Medium'),
                        'long'        => $schema->number()->required()->title('→ Long'),
                    ])->required()->withoutAdditionalProperties()->title('After Long'),
                ])->required()->withoutAdditionalProperties()->title('Rhythm Transition Matrix Counts'),
                // Boundary bucket values for inter-chapter transition stitching
                'first_action_line_bucket' => $schema->string()->required()->title('First Action Line Bucket')
                    ->description('ultra_short | short | medium | long'),
                'last_action_line_bucket'  => $schema->string()->required()->title('Last Action Line Bucket')
                    ->description('ultra_short | short | medium | long'),
                // Dialogue: raw speech lengths per character (not histogram buckets)
                // Merge concatenates speech_lengths_w across chunks per character,
                // then derives AVG, P90, P95, MAX.
                'dialogue_speech_lengths_by_character' => $schema->array()->required()
                    ->title('Dialogue Speech Lengths By Character')
                    ->items(
                        $schema->object([
                            'character'         => $schema->string()->required()->title('Character'),
                            'speech_lengths_w'  => $schema->array()->required()->title('Speech Lengths (words each)')
                                ->items($schema->number()->required()),
                            'speech_count'      => $schema->number()->required()->title('Speech Count'),
                            'max_speech_length_w' => $schema->number()->required()->title('Max Speech Length (words)'),
                        ])->required()->withoutAdditionalProperties()
                    ),
            ])->required()->withoutAdditionalProperties()->title('Metric Counts — Producer-Neutral Raw Counts');

            // Qualitative evidence fields (supplement counts; do not replace them)
            $voiceObservationsFields['beat_candidates'] = $schema->array()->required()->title('Beat Candidates')->items(
                $schema->object([
                    'beat_text'         => $schema->string()->required()->title('Beat Text'),
                    'placement_context' => $schema->string()->required()->title('Placement Context'),
                    'function'          => $schema->string()->required()->title('Function'),
                ])->required()->withoutAdditionalProperties()
            );
            $voiceObservationsFields['scene_closing_samples'] = $schema->array()->required()->title('Scene Closing Samples')->items(
                $schema->object([
                    'closing_lines' => $schema->array()->required()->title('Closing Lines')->items($schema->string()->required()),
                    'scene_context' => $schema->string()->required()->title('Scene Context'),
                ])->required()->withoutAdditionalProperties()
            );
            $voiceObservationsFields['confidence_sample_size_notes'] = $schema->string()->required()
                ->title('Confidence Sample Size Notes')
                ->description('Confidence tier + sample size notes for sparse metrics in this chapter.');

            // --- 1B v3 / V2.3: anchor candidate fields ---
            // Merge synthesises these into the top-level voice_anchor, anchor_card,
            // and runtime_self_check. Arrays may be empty; never omit the keys.
            $voiceObservationsFields['voice_anchor_candidates'] = $schema->array()->required()
                ->title('Voice Anchor Candidates')
                ->description('0–3 candidate exemplar passages from this chapter (Task 3). Empty array valid if no standout moments.')
                ->items(
                    $schema->object([
                        'mode'       => $schema->string()->required()->title('Mode')
                            ->description('cold_tension | physical_action | quiet_aftermath | environmental | dialogue_bearing | emotional_weight'),
                        'source'     => $schema->string()->required()->title('Source Moment')
                            ->description('The screenplay moment translated from.'),
                        'techniques' => $schema->string()->required()->title('Techniques Demonstrated')
                            ->description('2–3 signature techniques this exemplar demonstrates.'),
                        'prose'      => $schema->string()->required()->title('Prose')
                            ->description('90–150 words, second-person present-tense, passes all bans.'),
                    ])->required()->withoutAdditionalProperties()
                );

            $voiceObservationsFields['anchor_card_candidates'] = $schema->array()->required()
                ->title('Anchor Card Candidates')
                ->description('ABSOLUTE/HIGH-confidence binary/local rules from this chapter (Task 4). Phrased as direct actions. Empty array valid.')
                ->items($schema->string()->required());

            $voiceObservationsFields['self_check_candidates'] = $schema->array()->required()
                ->title('Self-Check Candidates')
                ->description('Discrete/local check steps observable in this chapter (Task 5). No rate computations. Empty array valid.')
                ->items($schema->string()->required());
        } else {
            $voiceObservationsFields['paragraph_architecture_note'] = $schema->string()->required()->title('Paragraph Architecture Note');
            $voiceObservationsFields['demonstrative_paragraphs'] = $schema->array()->required()->title('Demonstrative Paragraphs')->items($schema->string()->required());
            $voiceObservationsFields['narrator_perspective_notes'] = $schema->string()->required()->title('Narrator Perspective Notes');
            $voiceObservationsFields['dialogue_tag_notes'] = $schema->string()->required()->title('Dialogue Tag Notes');

            // --- 1A v2 / V2.3: novelist anchor candidate fields ---
            // Merge synthesises these into the top-level voice_anchor, anchor_card,
            // and runtime_self_check. Arrays may be empty; never omit the keys.

            // documented_narrator_techniques: novelist-specific carve-outs from bans.
            // Tracks techniques the author uses deliberately (cognitive verbs, free indirect
            // discourse, prolepsis, editorial commentary) so merge can carve them out of
            // the ban list and Anchor Card rather than banning them incorrectly.
            $voiceObservationsFields['documented_narrator_techniques'] = $schema->array()->required()
                ->title('Documented Narrator Techniques')
                ->description('1A v2: techniques observable in this chapter that AI is normally banned from but this author uses deliberately (cognitive verbs, free indirect discourse, prolepsis, direct address, philosophical commentary). Becomes merge carve-outs. Empty array valid.')
                ->items(
                    $schema->object([
                        'technique'   => $schema->string()->required()->title('Technique')
                            ->description('Name of the normally-banned technique this author uses.'),
                        'evidence'    => $schema->string()->required()->title('Evidence')
                            ->description('Direct quote from source demonstrating deliberate use.'),
                        'carve_out'   => $schema->string()->required()->title('Carve-Out Rule')
                            ->description('How the ban should be modified to permit this technique for this IP.'),
                    ])->required()->withoutAdditionalProperties()
                );

            $voiceObservationsFields['voice_anchor_candidates'] = $schema->array()->required()
                ->title('Voice Anchor Candidates')
                ->description('1A v2: 0–3 candidate passages from this chapter, with POV+tense conversion to second-person present only (no paraphrase). Mode + source + techniques header required. Empty array valid.')
                ->items(
                    $schema->object([
                        'mode'       => $schema->string()->required()->title('Mode')
                            ->description('atmosphere | rising_tension | quiet_beat | dialogue_bearing | action | emotional_register'),
                        'source'     => $schema->string()->required()->title('Source Passage')
                            ->description('The original source passage this was converted from.'),
                        'techniques' => $schema->string()->required()->title('Techniques Demonstrated')
                            ->description('2–3 signature techniques this exemplar demonstrates.'),
                        'prose'      => $schema->string()->required()->title('Prose')
                            ->description('90–150 words, second-person present, near-verbatim author voice. POV+tense conversion only.'),
                    ])->required()->withoutAdditionalProperties()
                );

            $voiceObservationsFields['anchor_card_candidates'] = $schema->array()->required()
                ->title('Anchor Card Candidates')
                ->description('1A v2: ABSOLUTE/HIGH-confidence binary/local rules from this chapter (Task 5). Carve-outs for documented narrator techniques respected. Empty array valid.')
                ->items($schema->string()->required());

            $voiceObservationsFields['self_check_candidates'] = $schema->array()->required()
                ->title('Self-Check Candidates')
                ->description('1A v2: discrete/local check steps from this chapter (Task 6). No rate computations. Empty array valid.')
                ->items($schema->string()->required());
        }

        return [
            'chapter_id' => $schema->number()->required()->title('Chapter ID'),
            'chapter_position' => $schema->number()->required()->title('Chapter Position'),
            'voice_observations' => $schema->object($voiceObservationsFields)->required()->withoutAdditionalProperties()->title('Voice Observations'),
            'character_dialogue_observations' => $schema->array()->required()->title('Character Dialogue Observations')->items(
                $schema->object([
                    'character' => $schema->string()->required()->title('Character'),
                    'speech_rhythm' => $schema->string()->required()->title('Speech Rhythm'),
                    'verbal_tics_or_recurring_phrases' => $schema->array()->required()->title('Verbal Tics')->items($schema->string()->required()),
                    'words_they_would_never_say' => $schema->array()->required()->title('Words They Would Never Say')->items($schema->string()->required()),
                    'emotional_range_in_dialogue' => $schema->string()->required()->title('Emotional Range In Dialogue'),
                    'distinguishing_markers' => $schema->array()->required()->title('Distinguishing Markers')->items($schema->string()->required()),
                    'signature_line' => $schema->string()->required()->title('Signature Line'),
                ])->required()->withoutAdditionalProperties()
            ),
            'ip_specific_ban_candidates' => $schema->array()->required()->title('IP-Specific Ban Candidates')->items(
                $schema->object([
                    'ban' => $schema->string()->required()->title('Ban'),
                    'evidence' => $schema->string()->required()->title('Evidence'),
                    'positive_replacement' => $schema->string()->required()->title('Positive Replacement'),
                ])->required()->withoutAdditionalProperties()
            ),
        ];
    }
}
