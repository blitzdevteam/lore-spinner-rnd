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
 * Pipeline Upgrade V2 — Deliverable 1: Voice Lock Phase.
 *
 * Runs ONCE per IP, between Phase 1 (IP Audit) and Phase 2 (Story Session Map).
 * Consumes the FULL ORIGINAL source text. Output becomes constitutional law:
 *   - the runtime narrator template's Voice Profile Block (Section 6) and
 *     Hard Bans Block (Section 7) are filled from this output.
 *   - Phase 8 Editorial Verification runs the 14-Point Audit Protocol with
 *     the per-IP bans + universal bans + signature checks loaded from here.
 *
 * Schema mirrors Deliverable 1's three tasks exactly:
 *   1. author_voice_dna_profile     (sections A-F)
 *   2. master_rule_1_hard_bans      (universal_bans + ip_specific_bans)
 *   3. fourteen_point_audit_protocol
 *
 * Low temperature (0.2) because we want deterministic forensic output, not
 * creative interpretation. Long timeout (900s) — full-novel reads.
 */
#[Model('gpt-5.4')]
#[Temperature(0.2)]
#[Timeout(900)]
class VoiceLockAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.voice-lock.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        $signatureTechniqueSchema = $schema->object([
            'name' => $schema->string()->required()->title('Name')->description('2-4 words.'),
            'quotes' => $schema->array()->required()->title('Quotes')->description('2-3 direct quotes from the source.')->items($schema->string()->required()),
            'why_this_author' => $schema->string()->required()->title('Why This Author')->description('One sentence: what makes this technique specific to THIS author, not just good writing.'),
        ])->required()->withoutAdditionalProperties();

        $sentencePatternsSchema = $schema->object([
            'average_sentence_length' => $schema->string()->required()->title('Average Sentence Length')->description('Words per sentence.'),
            'cadence_variation' => $schema->string()->required()->title('Cadence Variation Pattern'),
            'clause_structure_preference' => $schema->string()->required()->title('Clause Structure Preference'),
            'demonstrative_quotes' => $schema->array()->required()->title('Demonstrative Quotes')->description('3-4 consecutive sentences from the source that show natural rhythm at its most distinctive.')->items($schema->string()->required()),
        ])->required()->withoutAdditionalProperties();

        $dictionFingerprintSchema = $schema->object([
            'vocabulary_clusters' => $schema->array()->required()->title('Vocabulary Clusters')->items($schema->string()->required()),
            'register_and_formality' => $schema->string()->required()->title('Register And Formality'),
            'word_frequency_patterns' => $schema->string()->required()->title('Word Frequency Patterns')->description('What this author uses MORE; what they AVOID.'),
            'distinctive_diction_quotes' => $schema->array()->required()->title('Distinctive Diction Quotes')->description('5-6 lines that demonstrate diction choices no other writer would make the same way.')->items($schema->string()->required()),
        ])->required()->withoutAdditionalProperties();

        $characterDialogueSchema = $schema->object([
            'character' => $schema->string()->required()->title('Character'),
            'speech_rhythm' => $schema->string()->required()->title('Speech Rhythm'),
            'verbal_tics_or_recurring_phrases' => $schema->array()->required()->title('Verbal Tics Or Recurring Phrases')->items($schema->string()->required()),
            'words_they_would_never_say' => $schema->array()->required()->title('Words They Would Never Say')->items($schema->string()->required()),
            'emotional_range_in_dialogue' => $schema->string()->required()->title('Emotional Range In Dialogue')->description('How they sound angry, afraid, tender, lying.'),
            'signature_line' => $schema->string()->required()->title('Signature Line')->description('The single most characteristic line of dialogue.'),
        ])->required()->withoutAdditionalProperties();

        $emotionalRegisterSchema = $schema->object([
            'register' => $schema->string()->required()->title('Register'),
            'quote' => $schema->string()->required()->title('Quote')->description('One source passage that demonstrates the register, or "ABSENT" if the emotion is not in the source.'),
            'technique' => $schema->string()->required()->title('Technique')->description('One sentence on how the author achieves it. "ABSENT" if not present.'),
        ])->required()->withoutAdditionalProperties();

        $ipSpecificBanSchema = $schema->object([
            'ban' => $schema->string()->required()->title('Ban'),
            'evidence' => $schema->string()->required()->title('Evidence')->description('Citation to Task 1 DNA finding.'),
            'positive_replacement' => $schema->string()->required()->title('Positive Replacement')->description('What the AI should do instead.'),
        ])->required()->withoutAdditionalProperties();

        $auditPointSchema = $schema->object([
            'point_number' => $schema->number()->required()->title('Point Number'),
            'point_name' => $schema->string()->required()->title('Point Name'),
            'pass_fail_definition' => $schema->string()->required()->title('Pass Fail Definition')->description('Specific to this IP.'),
            'detection_method' => $schema->string()->required()->title('Detection Method'),
            'repair_instruction' => $schema->string()->required()->title('Repair Instruction'),
        ])->required()->withoutAdditionalProperties();

        return [
            'author_voice_dna_profile' => $schema->object([
                'signature_writing_techniques' => $schema->array()->required()->title('Signature Writing Techniques')->description('8-12 entries.')->items($signatureTechniqueSchema),
                'sentence_level_patterns' => $sentencePatternsSchema->title('Sentence-Level Patterns'),
                'diction_fingerprint' => $dictionFingerprintSchema->title('Diction Fingerprint'),
                'dialogue_fingerprint_per_character' => $schema->array()->required()->title('Dialogue Fingerprint Per Character')->description('One entry per character with more than 5 lines of dialogue.')->items($characterDialogueSchema),
                'emotional_range_map' => $schema->object([
                    'tension' => (clone $emotionalRegisterSchema)->title('Tension'),
                    'humor' => (clone $emotionalRegisterSchema)->title('Humor'),
                    'grief' => (clone $emotionalRegisterSchema)->title('Grief'),
                    'wonder' => (clone $emotionalRegisterSchema)->title('Wonder'),
                    'fear' => (clone $emotionalRegisterSchema)->title('Fear'),
                    'violence' => (clone $emotionalRegisterSchema)->title('Violence'),
                    'intimacy' => (clone $emotionalRegisterSchema)->title('Intimacy'),
                ])->required()->withoutAdditionalProperties()->title('Emotional Range Map'),
                'paragraph_architecture' => $schema->object([
                    'pattern' => $schema->string()->required()->title('Pattern')->description('Short punches / long flowing blocks / mixed.'),
                    'transition_method' => $schema->string()->required()->title('Transition Method'),
                    'demonstrative_quotes' => $schema->array()->required()->title('Demonstrative Quotes')->description('Two consecutive paragraphs from the source.')->items($schema->string()->required()),
                ])->required()->withoutAdditionalProperties()->title('Paragraph Architecture'),
            ])->required()->withoutAdditionalProperties()->title('Author Voice DNA Profile'),

            'master_rule_1_hard_bans' => $schema->object([
                'universal_bans_acknowledged' => $schema->boolean()->required()->title('Universal Bans Acknowledged')->description('True if the agent has loaded the universal-ban block from the system prompt. Used as an integrity check during Phase 8.'),
                'ip_specific_bans' => $schema->array()->required()->title('IP-Specific Bans')->description('Minimum 6 entries.')->items($ipSpecificBanSchema),
            ])->required()->withoutAdditionalProperties()->title('Master Rule 1 Hard Bans'),

            'fourteen_point_audit_protocol' => $schema->array()->required()->title('Fourteen Point Audit Protocol')->description('Exactly 14 entries.')->items($auditPointSchema),
        ];
    }
}
