<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * Pipeline Upgrade V2.2 — Voice Lock structured output schemas.
 *
 * Novelist (1A) and screenwriter (1B) profiles share a base shape with
 * format-specific extensions required by Deliverables 1A / 1B.
 */
final class VoiceLockSchema
{
    public static function isScreenwriter(?string $detectedFormat): bool
    {
        return strtoupper((string) $detectedFormat) === 'SCREENPLAY';
    }

    public static function mergeSchema(JsonSchema $schema, ?string $detectedFormat): array
    {
        return self::isScreenwriter($detectedFormat)
            ? self::screenwriterSchema($schema)
            : self::novelistSchema($schema);
    }

    public static function novelistSchema(JsonSchema $schema): array
    {
        return self::buildSchema($schema, 'NOVELIST', includeNovelistFields: true, includeScreenwriterFields: false);
    }

    public static function screenwriterSchema(JsonSchema $schema): array
    {
        return self::buildSchema($schema, 'SCREENWRITER', includeNovelistFields: false, includeScreenwriterFields: true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildSchema(
        JsonSchema $schema,
        string $profileType,
        bool $includeNovelistFields,
        bool $includeScreenwriterFields,
    ): array {
        $signatureTechniqueSchema = $schema->object([
            'name' => $schema->string()->required()->title('Name')->description('2-4 words.'),
            'quotes' => $schema->array()->required()->title('Quotes')->description('2-3 direct quotes from the source.')->items($schema->string()->required()),
            'why_this_author' => $schema->string()->required()->title('Why This Author')->description('One sentence: what makes this technique specific to THIS author.'),
            'frequency' => $schema->string()->required()->title('Frequency')->description('Approximate rate this technique appears in the source.'),
        ])->required()->withoutAdditionalProperties();

        $sentencePatternsSchema = $schema->object([
            'average_sentence_length' => $schema->string()->required()->title('Average Sentence Length'),
            'cadence_variation' => $schema->string()->required()->title('Cadence Variation Pattern'),
            'clause_structure_preference' => $schema->string()->required()->title('Clause Structure Preference'),
            'punctuation_habits' => $schema->string()->required()->title('Punctuation Habits'),
            'demonstrative_quotes' => $schema->array()->required()->title('Demonstrative Quotes')->items($schema->string()->required()),
        ])->required()->withoutAdditionalProperties();

        $dictionFingerprintSchema = $schema->object([
            'vocabulary_clusters' => $schema->array()->required()->title('Vocabulary Clusters')->items($schema->string()->required()),
            'register_and_formality' => $schema->string()->required()->title('Register And Formality'),
            'word_frequency_patterns' => $schema->string()->required()->title('Word Frequency Patterns'),
            'distinctive_diction_quotes' => $schema->array()->required()->title('Distinctive Diction Quotes')->items($schema->string()->required()),
        ])->required()->withoutAdditionalProperties();

        $characterDialogueSchema = $schema->object([
            'character' => $schema->string()->required()->title('Character'),
            'speech_rhythm' => $schema->string()->required()->title('Speech Rhythm'),
            'verbal_tics_or_recurring_phrases' => $schema->array()->required()->title('Verbal Tics Or Recurring Phrases')->items($schema->string()->required()),
            'words_they_would_never_say' => $schema->array()->required()->title('Words They Would Never Say')->items($schema->string()->required()),
            'emotional_range_in_dialogue' => $schema->string()->required()->title('Emotional Range In Dialogue'),
            'distinguishing_markers' => $schema->array()->required()->title('Distinguishing Markers')->description('At least 3 linguistic markers unique to this character.')->items($schema->string()->required()),
            'signature_line' => $schema->string()->required()->title('Signature Line'),
        ])->required()->withoutAdditionalProperties();

        $emotionalRegisterSchema = $schema->object([
            'register' => $schema->string()->required()->title('Register'),
            'quote' => $schema->string()->required()->title('Quote'),
            'technique' => $schema->string()->required()->title('Technique'),
            'rendering_method' => $schema->string()->required()->title('Rendering Method'),
        ])->required()->withoutAdditionalProperties();

        $collocationSchema = $schema->object([
            'pair' => $schema->string()->required()->title('Pair')->description('Author exact collocation, e.g. "clay pipe".'),
            'quotes' => $schema->array()->required()->title('Quotes')->items($schema->string()->required()),
            'frequency' => $schema->string()->required()->title('Frequency'),
            'ai_substitution' => $schema->string()->required()->title('AI Substitution')->description('What a generic AI would write instead.'),
            'category' => $schema->string()->required()->title('Category'),
        ])->required()->withoutAdditionalProperties();

        $negativeSpaceSchema = $schema->object([
            'technique' => $schema->string()->required()->title('Technique'),
            'absence_evidence' => $schema->string()->required()->title('Absence Evidence'),
            'why_ai_defaults' => $schema->string()->required()->title('Why AI Defaults'),
        ])->required()->withoutAdditionalProperties();

        $comparativeExclusionSchema = $schema->object([
            'neighbor_author' => $schema->string()->required()->title('Neighbor Author'),
            'overlapping_quality' => $schema->string()->required()->title('Overlapping Quality'),
            'differentiating_techniques' => $schema->array()->required()->title('Differentiating Techniques')->items($schema->string()->required()),
        ])->required()->withoutAdditionalProperties();

        $ipSpecificBanSchema = $schema->object([
            'ban' => $schema->string()->required()->title('Ban'),
            'evidence' => $schema->string()->required()->title('Evidence'),
            'positive_replacement' => $schema->string()->required()->title('Positive Replacement'),
        ])->required()->withoutAdditionalProperties();

        $auditPointSchema = $schema->object([
            'point_number' => $schema->number()->required()->title('Point Number'),
            'point_name' => $schema->string()->required()->title('Point Name'),
            'pass_fail_definition' => $schema->string()->required()->title('Pass Fail Definition'),
            'detection_method' => $schema->string()->required()->title('Detection Method'),
            'repair_instruction' => $schema->string()->required()->title('Repair Instruction'),
        ])->required()->withoutAdditionalProperties();

        $dnaProfileFields = [
            'signature_writing_techniques' => $schema->array()->required()->title('Signature Writing Techniques')->description('8-12 entries.')->items($signatureTechniqueSchema),
            'sentence_level_patterns' => $sentencePatternsSchema->title('Sentence-Level Patterns'),
            'diction_fingerprint' => $dictionFingerprintSchema->title('Diction Fingerprint'),
            'dialogue_fingerprint_per_character' => $schema->array()->required()->title('Dialogue Fingerprint Per Character')->items($characterDialogueSchema),
            'emotional_range_map' => $schema->object([
                'tension' => (clone $emotionalRegisterSchema)->title('Tension'),
                'humor' => (clone $emotionalRegisterSchema)->title('Humor'),
                'grief' => (clone $emotionalRegisterSchema)->title('Grief'),
                'wonder' => (clone $emotionalRegisterSchema)->title('Wonder'),
                'fear' => (clone $emotionalRegisterSchema)->title('Fear'),
                'violence' => (clone $emotionalRegisterSchema)->title('Violence'),
                'intimacy' => (clone $emotionalRegisterSchema)->title('Intimacy'),
            ])->required()->withoutAdditionalProperties()->title('Emotional Range Map'),
            'collocation_fingerprint' => $schema->array()->required()->title('Collocation Fingerprint')->description('15-20 characteristic word pairs.')->items($collocationSchema),
            'negative_space_map' => $schema->array()->required()->title('Negative Space Map')->description('Minimum 5 genre-default techniques this author never uses.')->items($negativeSpaceSchema),
            'show_explain_ratio' => $schema->object([
                'show_language_description' => $schema->string()->required()->title('Show Language Description'),
                'explain_language_description' => $schema->string()->required()->title('Explain Language Description'),
                'approximate_balance' => $schema->string()->required()->title('Approximate Balance'),
                'enforcement_note' => $schema->string()->required()->title('Enforcement Note'),
            ])->required()->withoutAdditionalProperties()->title('Show Explain Ratio'),
            'comparative_exclusion' => $schema->array()->required()->title('Comparative Exclusion')->description('2-3 stylistic neighbors.')->items($comparativeExclusionSchema),
        ];

        if ($includeNovelistFields) {
            $dnaProfileFields['narrator_perspective'] = $schema->object([
                'point_of_view' => $schema->string()->required()->title('Point Of View'),
                'reliability' => $schema->string()->required()->title('Reliability'),
                'distance' => $schema->string()->required()->title('Distance'),
                'commentary' => $schema->string()->required()->title('Commentary'),
                'tense' => $schema->string()->required()->title('Tense'),
                'interior_monologue' => $schema->string()->required()->title('Interior Monologue'),
                'representative_quotes' => $schema->array()->required()->title('Representative Quotes')->items($schema->string()->required()),
            ])->required()->withoutAdditionalProperties()->title('Narrator Perspective');

            $dnaProfileFields['paragraph_architecture'] = $schema->object([
                'pattern' => $schema->string()->required()->title('Pattern'),
                'transition_method' => $schema->string()->required()->title('Transition Method'),
                'chapter_opening_style' => $schema->string()->required()->title('Chapter Opening Style'),
                'chapter_closing_style' => $schema->string()->required()->title('Chapter Closing Style'),
                'demonstrative_quotes' => $schema->array()->required()->title('Demonstrative Quotes')->items($schema->string()->required()),
            ])->required()->withoutAdditionalProperties()->title('Paragraph Architecture');

            $dnaProfileFields['dialogue_tag_patterns'] = $schema->object([
                'said_percentage' => $schema->string()->required()->title('Said Percentage'),
                'other_tags' => $schema->array()->required()->title('Other Tags')->items($schema->string()->required()),
                'action_beats_frequency' => $schema->string()->required()->title('Action Beats Frequency'),
                'banned_tags' => $schema->array()->required()->title('Banned Tags')->items($schema->string()->required()),
            ])->required()->withoutAdditionalProperties()->title('Dialogue Tag Patterns');
        }

        if ($includeScreenwriterFields) {
            $dnaProfileFields['action_line_metrics'] = $schema->object([
                'average_words_per_line' => $schema->string()->required()->title('Average Words Per Line'),
                'fragment_percentage' => $schema->string()->required()->title('Fragment Percentage'),
                'verb_first_percentage' => $schema->string()->required()->title('Verb First Percentage'),
                'all_caps_density' => $schema->string()->required()->title('All Caps Density'),
                'paragraph_rhythm' => $schema->string()->required()->title('Paragraph Rhythm'),
                'demonstrative_quotes' => $schema->array()->required()->title('Demonstrative Quotes')->items($schema->string()->required()),
            ])->required()->withoutAdditionalProperties()->title('Action Line Metrics');

            $dnaProfileFields['screenplay_structure_metrics'] = $schema->object([
                'scene_density' => $schema->string()->required()->title('Scene Density'),
                'int_ext_ratio' => $schema->string()->required()->title('Int Ext Ratio'),
                'action_to_dialogue_ratio' => $schema->string()->required()->title('Action To Dialogue Ratio'),
                'transition_types' => $schema->string()->required()->title('Transition Types'),
                'parenthetical_vocabulary' => $schema->string()->required()->title('Parenthetical Vocabulary'),
                'character_introduction_patterns' => $schema->string()->required()->title('Character Introduction Patterns'),
            ])->required()->withoutAdditionalProperties()->title('Screenplay Structure Metrics');

            $dnaProfileFields['emotional_vocabulary_hierarchy'] = $schema->array()->required()->title('Emotional Vocabulary Hierarchy')->description('Ranked categories with representative quotes.')->items(
                $schema->object([
                    'category' => $schema->string()->required()->title('Category'),
                    'rank' => $schema->number()->required()->title('Rank'),
                    'representative_quotes' => $schema->array()->required()->title('Representative Quotes')->items($schema->string()->required()),
                ])->required()->withoutAdditionalProperties()
            );

            $dnaProfileFields['screenplay_to_prose_protocol'] = $schema->array()->required()->title('Screenplay To Prose Protocol')->items(
                $schema->object([
                    'screenplay_element' => $schema->string()->required()->title('Screenplay Element'),
                    'prose_translation_rule' => $schema->string()->required()->title('Prose Translation Rule'),
                ])->required()->withoutAdditionalProperties()
            );
        }

        return [
            'profile_type' => $schema->string()->required()->title('Profile Type')->description($profileType),

            'author_voice_dna_profile' => $schema->object($dnaProfileFields)
                ->required()
                ->withoutAdditionalProperties()
                ->title('Author Voice DNA Profile'),

            'master_rule_1_hard_bans' => $schema->object([
                'universal_bans_acknowledged' => $schema->boolean()->required()->title('Universal Bans Acknowledged'),
                'ip_specific_bans' => $schema->array()->required()->title('IP-Specific Bans')->description('Minimum 6 entries.')->items($ipSpecificBanSchema),
            ])->required()->withoutAdditionalProperties()->title('Master Rule 1 Hard Bans'),

            'fourteen_point_audit_protocol' => $schema->array()->required()->title('Fourteen Point Audit Protocol')->description('Exactly 14 entries.')->items($auditPointSchema),
        ];
    }
}
