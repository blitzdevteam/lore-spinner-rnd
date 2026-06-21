<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

/**
 * Produces doc-aligned slices of `story_adaptations.voice_profile` for injection
 * into adaptation phase prompts.
 *
 * Slice contract (mirrors V2.3 Deliverable headings):
 *   dna                → profile_type + author_voice_dna_profile only (D3 / Phase 4)
 *   dnaAndBans         → dna + master_rule_1_hard_bans (D4 / Phases 6, 7)
 *   dnaBansAndAnchor   → dna + bans + full voice_anchor + anchor_card (Phase 3 / D10)
 *                        Throws RuntimeException if voice_anchor or anchor_card absent.
 *   dnaBansAndAnchorCard → dna + bans + anchor_card only (no exemplar prose) (Phase 5 / D4)
 *                          Throws RuntimeException if anchor_card absent.
 *   alignmentContext   → dna subset: diction, dialogue fingerprints, comparative
 *                        exclusion, negative space — no heavy quote arrays (Phase 2)
 *   full               → entire profile (Phase 8)
 *
 * All non-full slices strip quote arrays to keep pipeline prompt token budgets
 * manageable (mirrors RuntimeNarratorTemplateBuilder::dropVoiceQuotes()).
 *
 * V2.3 note: voice_anchor exemplar prose is NEVER stripped in dnaBansAndAnchor —
 * it is load-bearing and must arrive verbatim. dnaBansAndAnchorCard deliberately
 * omits the full exemplar prose to keep Phase 5 within token budget.
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
     * For SCREENWRITER profiles (1B v2), also includes:
     *   - author_voice_dna_profile.screenplay_to_prose_protocol (element_rules + quantitative_translation_mappings)
     *   - author_voice_dna_profile.numerical_enforcement_layer (enforcement fields preserved;
     *     only quote/evidence-heavy material stripped — same canonical field shape)
     *
     * voice_decay_prevention_protocol is NOT included (runtime narrator only).
     * beat_architecture_protocol and scene_transition_compression_protocol are NOT added here;
     * they flow to Phase 4 via dna() which already receives full stripped author_voice_dna_profile.
     *
     * @param  array<string, mixed>  $voiceProfile
     * @return array<string, mixed>
     */
    public static function dnaAndBans(array $voiceProfile): array
    {
        $profileType = $voiceProfile['profile_type'] ?? 'LEGACY';
        $dna         = self::stripQuotes((array) ($voiceProfile['author_voice_dna_profile'] ?? []));

        if ($profileType === 'SCREENWRITER') {
            // screenplay_to_prose_protocol: pass the full object (element_rules + quantitative_translation_mappings).
            // The field is already inside $dna from stripQuotes() — ensure both sub-keys are present.
            // No stripping needed: element_rules and quantitative_translation_mappings contain rules/tables,
            // not evidence quotes.

            // numerical_enforcement_layer: preserve all enforcement fields (target/floor/ceiling/
            // confidence/sample_size on every metric); strip only auxiliary evidence arrays if present.
            if (isset($dna['numerical_enforcement_layer'])) {
                $dna['numerical_enforcement_layer'] = self::stripNelAuxiliaryArrays(
                    (array) $dna['numerical_enforcement_layer']
                );
            }
        }

        return [
            'profile_type'             => $profileType,
            'author_voice_dna_profile' => $dna,
            'master_rule_1_hard_bans'  => $voiceProfile['master_rule_1_hard_bans'] ?? [],
        ];
    }

    /**
     * Sections 1+2+3 (Voice Anchor) — DNA + bans + full voice_anchor exemplars + anchor_card.
     * Used by EntryPointDiagnosisJob / Phase 3 (D10).
     *
     * voice_anchor exemplars are NEVER stripped — they are load-bearing prose the agent
     * uses to ground cold_open tone and first_choice_spec quality.
     * runtime_self_check is omitted (runtime-only; not needed at adaptation time).
     *
     * @param  array<string, mixed>  $voiceProfile
     * @return array<string, mixed>
     * @throws \RuntimeException if voice_anchor or anchor_card absent (V2.3 profile required)
     */
    public static function dnaBansAndAnchor(array $voiceProfile): array
    {
        if (empty($voiceProfile['voice_anchor'])) {
            throw new \RuntimeException(
                'dnaBansAndAnchor() requires a V2.3 voice_profile with voice_anchor populated. '
                . 'Re-run Voice Lock for this story before running Phase 3.'
            );
        }

        if (empty($voiceProfile['anchor_card'])) {
            throw new \RuntimeException(
                'dnaBansAndAnchor() requires a V2.3 voice_profile with anchor_card populated. '
                . 'Re-run Voice Lock for this story before running Phase 3.'
            );
        }

        $base = self::dnaAndBans($voiceProfile);

        // voice_anchor prose is load-bearing — include verbatim, never stripped.
        $base['voice_anchor'] = $voiceProfile['voice_anchor'];
        $base['anchor_card']  = $voiceProfile['anchor_card'];

        return $base;
    }

    /**
     * Sections 1+2+anchor_card only — no voice_anchor exemplar prose.
     * Used by ChoiceDesignAgent / Phase 5 (D4 patch).
     *
     * Deliberately omits full voice_anchor exemplar prose to keep Phase 5 within
     * token budget. The Anchor Card (binary/local rules) is sufficient for authored
     * choice prose; full exemplars are runtime-only via D8.
     *
     * @param  array<string, mixed>  $voiceProfile
     * @return array<string, mixed>
     * @throws \RuntimeException if anchor_card absent (V2.3 profile required)
     */
    public static function dnaBansAndAnchorCard(array $voiceProfile): array
    {
        if (empty($voiceProfile['anchor_card'])) {
            throw new \RuntimeException(
                'dnaBansAndAnchorCard() requires a V2.3 voice_profile with anchor_card populated. '
                . 'Re-run Voice Lock for this story before running Phase 5.'
            );
        }

        $base = self::dnaAndBans($voiceProfile);

        // anchor_card only — voice_anchor exemplar prose intentionally excluded.
        $base['anchor_card'] = $voiceProfile['anchor_card'];

        return $base;
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
     * Strip only auxiliary evidence arrays from the numerical_enforcement_layer
     * for token control. Enforcement fields (target/floor/ceiling/confidence/
     * sample_size on every metric, dialogue ceilings, opener distribution,
     * word-length buckets) are preserved intact — same canonical field shape.
     *
     * @param  array<string, mixed>  $nel
     * @return array<string, mixed>
     */
    private static function stripNelAuxiliaryArrays(array $nel): array
    {
        // All current NEL sub-objects are purely enforcement specs (no evidence quotes).
        // This method is the correct extension point if future NEL versions add
        // evidence-quote arrays. Passes through unchanged until such arrays exist.
        return $nel;
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
