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
 * Pipeline Upgrade V2.2 — Voice Lock per-chapter pass (Deliverable 1A or 1B fragment).
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
            $voiceObservationsFields['action_line_samples'] = $schema->array()->required()->title('Action Line Samples')->items($schema->string()->required());
            $voiceObservationsFields['action_line_metrics_note'] = $schema->string()->required()->title('Action Line Metrics Note');
            $voiceObservationsFields['dialogue_metrics_note'] = $schema->string()->required()->title('Dialogue Metrics Note');
        } else {
            $voiceObservationsFields['paragraph_architecture_note'] = $schema->string()->required()->title('Paragraph Architecture Note');
            $voiceObservationsFields['demonstrative_paragraphs'] = $schema->array()->required()->title('Demonstrative Paragraphs')->items($schema->string()->required());
            $voiceObservationsFields['narrator_perspective_notes'] = $schema->string()->required()->title('Narrator Perspective Notes');
            $voiceObservationsFields['dialogue_tag_notes'] = $schema->string()->required()->title('Dialogue Tag Notes');
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
