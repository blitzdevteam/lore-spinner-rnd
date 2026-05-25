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
 * Pipeline Upgrade V2 — Voice Lock (per-chapter pass).
 *
 * Reads ONE CHAPTER of the FULL ORIGINAL source text and extracts a compact
 * voice observation fragment: technique samples, sentence pattern notes,
 * diction samples, character dialogue fingerprints, and IP-specific ban
 * candidates visible in this chapter.
 *
 * All fragments are collected by VoiceLockMergeJob which makes a single
 * full synthesis API call (VoiceLockMergeAgent) to produce the complete
 * author Voice DNA Profile matching the Deliverable 1 schema.
 *
 * IMPORTANT: VoiceLock uses the FULL ORIGINAL chapter content, NOT the
 * trimmed version produced by IpTrimmingChapterAgent. Voice extraction
 * requires the complete range of the author's writing.
 *
 * gpt-5.4-mini: observation task, bounded to one chapter.
 */
#[Model('gpt-5.4')]
#[Temperature(0.2)]
#[Timeout(300)]
class VoiceLockChapterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.voice-lock.chapter-system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $emotionalMomentSchema = $schema->object([
            'register' => $schema->string()->required()->title('Register')->description('TENSION, HUMOR, GRIEF, WONDER, FEAR, VIOLENCE, or INTIMACY.'),
            'quote' => $schema->string()->required()->title('Quote')->description('Direct source quote. "ABSENT" if this register does not appear in this chapter.'),
            'technique' => $schema->string()->required()->title('Technique')->description('One sentence on how the author achieves it. "ABSENT" if not present.'),
        ])->required()->withoutAdditionalProperties();

        return [
            'chapter_id' => $schema->number()->required()->title('Chapter ID'),

            'chapter_position' => $schema->number()->required()->title('Chapter Position'),

            'voice_observations' => $schema->object([
                'signature_techniques_observed' => $schema->array()->required()->title('Signature Techniques Observed')->description('Up to 5 distinctive techniques visible in this chapter. Each must be specific to this author.')->items(
                    $schema->object([
                        'name' => $schema->string()->required()->title('Name')->description('2-4 words.'),
                        'quote' => $schema->string()->required()->title('Quote')->description('One direct source quote demonstrating this technique.'),
                        'technique_note' => $schema->string()->required()->title('Technique Note')->description('One sentence: what makes this specific to THIS author.'),
                    ])->required()->withoutAdditionalProperties()
                ),

                'sentence_pattern_notes' => $schema->string()->required()->title('Sentence Pattern Notes')->description('Brief observation on sentence length, cadence, and clause structure in this chapter.'),

                'demonstrative_quotes' => $schema->array()->required()->title('Demonstrative Quotes')->description('3-4 consecutive sentences from this chapter that show the author\'s natural rhythm at its most distinctive.')->items($schema->string()->required()),

                'diction_samples' => $schema->array()->required()->title('Diction Samples')->description('5-6 lines from this chapter that demonstrate diction choices no other writer would make the same way.')->items($schema->string()->required()),

                'register_note' => $schema->string()->required()->title('Register Note')->description('Formality level and vocabulary register in this chapter.'),

                'word_frequency_note' => $schema->string()->required()->title('Word Frequency Note')->description('What this author uses more of / avoids in this chapter.'),

                'paragraph_architecture_note' => $schema->string()->required()->title('Paragraph Architecture Note')->description('Short punches / long flowing / mixed, and how the author transitions between paragraphs.'),

                'demonstrative_paragraphs' => $schema->array()->required()->title('Demonstrative Paragraphs')->description('2 consecutive paragraphs from this chapter that demonstrate paragraph architecture at its most characteristic.')->items($schema->string()->required()),

                'emotional_moments_observed' => $schema->array()->required()->title('Emotional Moments Observed')->description('All emotional registers present in this chapter.')->items($emotionalMomentSchema),
            ])->required()->withoutAdditionalProperties()->title('Voice Observations'),

            'character_dialogue_observations' => $schema->array()->required()->title('Character Dialogue Observations')->description('One entry per character who has more than 5 lines of dialogue in this chapter.')->items(
                $schema->object([
                    'character' => $schema->string()->required()->title('Character'),
                    'speech_rhythm' => $schema->string()->required()->title('Speech Rhythm'),
                    'verbal_tics_or_recurring_phrases' => $schema->array()->required()->title('Verbal Tics')->items($schema->string()->required()),
                    'words_they_would_never_say' => $schema->array()->required()->title('Words They Would Never Say')->items($schema->string()->required()),
                    'emotional_range_in_dialogue' => $schema->string()->required()->title('Emotional Range In Dialogue'),
                    'signature_line' => $schema->string()->required()->title('Signature Line')->description('The single most characteristic line of dialogue for this character in this chapter.'),
                ])->required()->withoutAdditionalProperties()
            ),

            'ip_specific_ban_candidates' => $schema->array()->required()->title('IP-Specific Ban Candidates')->description('Minimum 2 patterns this author conspicuously avoids that AI would default to.')->items(
                $schema->object([
                    'ban' => $schema->string()->required()->title('Ban'),
                    'evidence' => $schema->string()->required()->title('Evidence')->description('What the source does instead.'),
                    'positive_replacement' => $schema->string()->required()->title('Positive Replacement'),
                ])->required()->withoutAdditionalProperties()
            ),
        ];
    }
}
