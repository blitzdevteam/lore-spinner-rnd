<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

/**
 * Produces doc-aligned slices of `story_adaptations.voice_profile` for injection
 * into adaptation phase prompts.
 *
 * Slice contract (mirrors V2.2 Deliverable headings):
 *   dna             → profile_type + author_voice_dna_profile only (D3 / Phase 4)
 *   dnaAndBans      → dna + master_rule_1_hard_bans (D4 / Phases 3, 5, 6, 7)
 *   alignmentContext → dna subset: diction, dialogue fingerprints, comparative
 *                      exclusion, negative space — no heavy quote arrays (Phase 2)
 *   full            → entire profile including fourteen_point_audit_protocol (Phase 8)
 *
 * All non-full slices strip quote arrays to keep pipeline prompt token budgets
 * manageable (mirrors RuntimeNarratorTemplateBuilder::dropVoiceQuotes()).
 */
final class VoiceProfilePromptSlice
{
    /**
     * Section 1 only — Voice DNA Profile (no bans, no audit).
     * Used by Phase 4 (Session Architecture) per D3 spec.
     *
     * @param  array<string, mixed>  $voiceProfile
     * @return array<string, mixed>
     */
    public static function dna(array $voiceProfile): array
    {
        return [
            'profile_type'             => $voiceProfile['profile_type'] ?? 'LEGACY',
            'author_voice_dna_profile' => self::stripQuotes(
                (array) ($voiceProfile['author_voice_dna_profile'] ?? [])
            ),
        ];
    }

    /**
     * Sections 1+2 — Voice DNA + Master Rule 1 hard bans.
     * Used by Phases 3, 5, 6, 7 per D4 spec.
     *
     * @param  array<string, mixed>  $voiceProfile
     * @return array<string, mixed>
     */
    public static function dnaAndBans(array $voiceProfile): array
    {
        return [
            'profile_type'             => $voiceProfile['profile_type'] ?? 'LEGACY',
            'author_voice_dna_profile' => self::stripQuotes(
                (array) ($voiceProfile['author_voice_dna_profile'] ?? [])
            ),
            'master_rule_1_hard_bans'  => $voiceProfile['master_rule_1_hard_bans'] ?? [],
        ];
    }

    /**
     * Alignment-context subset — diction, dialogue fingerprints, comparative
     * exclusion, negative space. No quote arrays. No full ban list.
     * Used by Phase 2 (StorySessionMap) to ground alignment voice signatures
     * and NPC tone against real author data.
     *
     * @param  array<string, mixed>  $voiceProfile
     * @return array<string, mixed>
     */
    public static function alignmentContext(array $voiceProfile): array
    {
        $dna = self::stripQuotes((array) ($voiceProfile['author_voice_dna_profile'] ?? []));

        return [
            'profile_type' => $voiceProfile['profile_type'] ?? 'LEGACY',
            'author_voice_dna_profile' => array_filter([
                'diction_fingerprint'             => $dna['diction_fingerprint'] ?? null,
                'dialogue_fingerprint_per_character' => $dna['dialogue_fingerprint_per_character'] ?? null,
                'comparative_exclusion'           => $dna['comparative_exclusion'] ?? null,
                'negative_space_map'              => $dna['negative_space_map'] ?? null,
                'collocation_fingerprint'         => $dna['collocation_fingerprint'] ?? null,
                'sentence_level_patterns'         => $dna['sentence_level_patterns'] ?? null,
            ], static fn ($v) => $v !== null),
        ];
    }

    /**
     * Full profile — all three sections including fourteen_point_audit_protocol.
     * Used by Phase 8 (Editorial Verification) per D6 spec.
     *
     * @param  array<string, mixed>  $voiceProfile
     * @return array<string, mixed>
     */
    public static function full(array $voiceProfile): array
    {
        return $voiceProfile;
    }

    /**
     * Remove heavy source-quote arrays from a voice DNA array so pipeline
     * prompts stay within token budgets. This mirrors the compression logic
     * in RuntimeNarratorTemplateBuilder::dropVoiceQuotes().
     *
     * @param  array<string, mixed>  $dna
     * @return array<string, mixed>
     */
    private static function stripQuotes(array $dna): array
    {
        foreach ((array) ($dna['signature_writing_techniques'] ?? []) as $i => $t) {
            $dna['signature_writing_techniques'][$i]['quotes'] = [];
        }

        if (isset($dna['sentence_level_patterns']['demonstrative_quotes'])) {
            $dna['sentence_level_patterns']['demonstrative_quotes'] = [];
        }

        if (isset($dna['diction_fingerprint']['distinctive_diction_quotes'])) {
            $dna['diction_fingerprint']['distinctive_diction_quotes'] = [];
        }

        if (isset($dna['paragraph_architecture']['demonstrative_quotes'])) {
            $dna['paragraph_architecture']['demonstrative_quotes'] = [];
        }

        foreach ((array) ($dna['emotional_range_map'] ?? []) as $key => $entry) {
            $dna['emotional_range_map'][$key]['quote'] = '';
        }

        foreach ((array) ($dna['collocation_fingerprint'] ?? []) as $i => $col) {
            $dna['collocation_fingerprint'][$i]['quotes'] = [];
        }

        foreach ((array) ($dna['comparative_exclusion'] ?? []) as $i => $ex) {
            $dna['comparative_exclusion'][$i]['differentiating_techniques'] = array_map(
                static fn ($t) => is_string($t) ? (strtok($t, '—') ?: $t) : $t,
                (array) ($ex['differentiating_techniques'] ?? [])
            );
        }

        return $dna;
    }
}
