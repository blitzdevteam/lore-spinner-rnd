# LoreSpinner V2.2 — Voice Lock Injection Fix: Implementation Process Log

**Date:** 2026-06-14  
**Plan reference:** `.cursor/plans/voice_lock_injection_fix_80c6b838.plan.md`  
**Builds on:** `v2-2-process-log.md` (pipeline reorder + schema expansion), `v2-process-log.md` (original V2 batch)  
**Deployment:** Laravel Cloud — push triggers automatic redeploy. Re-adaptation of QA stories runs on Cloud post-deploy.

---

## Problem this fixes

V2.2 Voice Lock extraction was complete and the `voice_profile` JSON was being stored correctly in `story_adaptations.voice_profile`. The V2.2 schema expansion had been validated (Oz NOVELIST profile: 10 techniques, 18 collocations, 6 negative space, 3 comparative exclusion, 9 IP bans, 14 audit points, 8 character dialogue fingerprints).

However, the profile was only being **read** in two places:

| Consumer | What it does |
|----------|-------------|
| `EditorialVerificationJob` | Audit — compares finished prose against profile post-hoc |
| `RuntimeNarratorTemplateBuilder` | Loads profile into runtime Section 6 for live narration |

Every phase that **generates authored prose or voice-dependent design** was running without the profile. This produced Oz editorial RED flags on Phase 5 diction ("moral complexity", "reclaimed agency", "ownerless") — the exact pattern where Voice Lock extraction works but consumption does not.

### Why runtime Section 6 does not fix it

Phase 5 choice outcomes (115–125 words per option) are written during adaptation and baked verbatim into runtime Section 14 ("authored choice moments"). Phase 3 cold opens are injected as `[OPENING_SCENE_INJECTION_POINT]` on turn 1. Phase 7 resolution prose is stored in `session_close_design.resolution_prose`.

Runtime Section 6 guides the **live narrator** — it cannot retroactively rewrite pre-scripted text that was generated blind to the profile. Editorial audits the finished text, catches the failures, but cannot re-run phases. The only fix is to inject the profile at generation time.

### Doc evidence: where injection is mandated

| Deliverable | Phase | Required input |
|-------------|-------|----------------|
| D3 (Phase 4 Beat Architecture) | Session Architecture | `[PASTE VOICE LOCK — Section 1 only (Voice DNA Profile)]` |
| D4 (Phase 5 Choice Design) | Choice Design | `[PASTE VOICE LOCK — Section 1 + Section 2 (Master Rule 1)]` |
| D6 (Phase 8 Editorial) | Editorial | `[PASTE COMPLETE VOICE LOCK — all three sections]` |
| D8 (Runtime Narrator) | Runtime | Section 4 (Voice DNA), Section 7 (Master Rule 1 bans) |

D2 (Phase 2 StorySessionMap) and D5 (Phase 6 Consequence Mapping) are listed as consumers in the integration plan dependency table. Phase 2 alignment `voice_signature` values and NPC tonal registers should be grounded in the real author profile; Phase 6 `narrative_execution` instructions should be written in author voice.

---

## Operating principle

The injection fix follows the same operating principle as V2 and V2.2: no silent improvements to the prompt text already approved in the deliverables. The only additions are:

1. A constitutional framing block before each prompt's data payload (see `_voice-profile-context.blade.php` below).
2. The sliced JSON payload itself.

No system prompt text was changed. The voice profile is added to the **user prompt** (the data block each agent receives alongside the system prompt), matching the existing pattern in `editorial-verification/prompt.blade.php`.

---

## Architecture decision: slice-per-phase, not full profile everywhere

Injecting the full ~50kb profile into every phase would cause token bloat and likely push Phase 5 past its 720s timeout. The fix uses doc-aligned slices via a new static helper, mirrors the existing `dropVoiceQuotes()` compression in `RuntimeNarratorTemplateBuilder`, and passes exactly what each deliverable specifies.

| Slice | JSON fields | Phases |
|-------|------------|--------|
| `dna` | `profile_type` + `author_voice_dna_profile` (quotes stripped) | Phase 4 (D3: Section 1 only) |
| `dnaAndBans` | `dna` + `master_rule_1_hard_bans` | Phases 3, 5, 6, 7 (D4: Sections 1+2) |
| `alignmentContext` | diction, dialogue fingerprints, comparative exclusion, negative space, collocations, sentence patterns (no technique quotes) | Phase 2 (alignment voice signatures + NPC tone) |
| `full` | entire profile including `fourteen_point_audit_protocol` | Phase 8 (D6: all three sections) |

**Phase 3 uses `dnaAndBans` not `dna`:** The cold open (120–180 words) is the first player-facing prose and is injected at turn 1. It must honour ban lists from the moment it is written — Section 1 alone is insufficient for authored prose.

---

## Files created

### `app/Ai/Adaptation/VoiceProfilePromptSlice.php` — **new**

Static helper class. Four public slice methods, one private `stripQuotes()` method that mirrors the `dropVoiceQuotes()` cascade in `RuntimeNarratorTemplateBuilder` (clears `signature_writing_techniques[].quotes`, `sentence_level_patterns.demonstrative_quotes`, `diction_fingerprint.distinctive_diction_quotes`, `paragraph_architecture.demonstrative_quotes`, `emotional_range_map[].quote`, `collocation_fingerprint[].quotes`, truncates `comparative_exclusion[].differentiating_techniques` to the text before the em-dash).

### `resources/views/ai/agents/adaptation/_voice-profile-context.blade.php` — **new**

Constitutional framing partial included at the top of every updated user prompt blade. Exact text:

```
=== VOICE PROFILE (VOICE LOCK — CONSTITUTIONAL LAW) ===

This profile overrides all generic style defaults, StoryGuard tonal guesses, and phase-level improvisation.
Voice Lock wins every conflict. Generic AI storytelling loses.
Any prose you write that violates this profile is rejected — not "close enough."

[optional $voiceProfileLabel describing which sections are included]

[json_encode($voiceProfile)]
```

The `$voiceProfileLabel` is set per-phase with a description of the slice and a one-line instruction for how the model uses it (e.g. "all 115–125 word outcomes must embody this profile").

---

## Files modified

### Jobs (7)

Each job received two changes: (a) the `VoiceProfilePromptSlice` import, (b) the sliced profile added to the `view(...)` data array, (c) a `RuntimeException` guard before the first `try` block.

| Job | Slice | Guard message |
|-----|-------|--------------|
| `app/Jobs/Adaptation/StorySessionMapJob.php` | `alignmentContext` | "Phase 2 (StorySessionMap)" |
| `app/Jobs/Adaptation/EntryPointDiagnosisJob.php` | `dnaAndBans` | "Phase 3 (EntryPointDiagnosis)" |
| `app/Jobs/Adaptation/SessionArchitectureJob.php` | `dna` | "Phase 4 (SessionArchitecture)" |
| `app/Jobs/Adaptation/ChoiceDesignJob.php` | `dnaAndBans` | "Phase 5 (ChoiceDesign)" |
| `app/Jobs/Adaptation/ConsequenceMappingJob.php` | `dnaAndBans` | "Phase 6 (ConsequenceMapping)" |
| `app/Jobs/Adaptation/SessionCloseJob.php` | `dnaAndBans` | "Phase 7 (SessionClose)" |
| `app/Jobs/Adaptation/EditorialVerificationJob.php` | `full` (guard only; already wired) | "Phase 8 (EditorialVerification)" |

The null guard pattern:
```php
if (empty($adaptation->voice_profile)) {
    throw new \RuntimeException(
        'voice_profile missing — Voice Lock must complete before Phase N (JobName)'
    );
}
```

This guard is placed outside the `try` block on session-level jobs (before `$session->update(['session_status' => ...])`) and outside the `try` block on the story-level `StorySessionMapJob` (immediately after `$adaptation = $this->story->adaptation`). It surfaces pipeline-order bugs with a clear message instead of silently generating generic prose.

### Prompt blades (7)

Each user prompt blade received `@include('ai.agents.adaptation._voice-profile-context', [...])` at the top, before the first data block. The `$voiceProfileLabel` for each is phase-specific:

| Blade | Label |
|-------|-------|
| `story-session-map/prompt.blade.php` | "Alignment context subset — diction, dialogue fingerprints, comparative exclusion, negative space (ground alignment voice_signature and NPC tonal registers in the real author voice)" |
| `entry-point-diagnosis/prompt.blade.php` | "Sections 1+2 — Voice DNA + Master Rule 1 bans (the cold open you write must embody this profile)" |
| `session-architecture/prompt.blade.php` | "Section 1 — Voice DNA (beat placement and posture shift design must serve this author's rhythm and signature techniques)" |
| `choice-design/prompt.blade.php` | "Sections 1+2 — Voice DNA + Master Rule 1 bans (all 115–125 word outcomes must embody this profile)" |
| `consequence-mapping/prompt.blade.php` | "Sections 1+2 — Voice DNA + Master Rule 1 bans (narrative_execution instructions must be written in the author's voice, not generic game prose)" |
| `session-close/prompt.blade.php` | "Sections 1+2 — Voice DNA + Master Rule 1 bans (resolution_prose must embody this profile)" |
| `editorial-verification/prompt.blade.php` | "All three sections — Voice DNA + Master Rule 1 bans + 14-Point Audit Protocol (feed Q11–Q17)" — refactored from inline `json_encode` to `@include` partial; behaviour unchanged |

### `app/Console/Commands/ExportAdaptationCommand.php`

Added `voice_profile` to both export formats:
- **CSV:** header row now reads `story_status, format_detection, ip_audit, voice_profile, story_session_map`; value row shows `done` / `pending`
- **JSON:** `story_wide` object now includes `"voice_profile": $adaptation->voice_profile` between `ip_audit` and `story_session_map`

This means `adaptation:export <slug>` produces a self-contained QA artefact without needing a separate `tinker` one-liner to extract the profile. The Oz export issue (profile absent from `Oz-V2-2.json`, needing `oz-voice-profile.json` as a separate file) is resolved for all future exports.

### `Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php`

Step 3 (blade render smoke test) updated. The seven user prompt blade entries that previously either had no `voiceProfile` variable or were missing from the fixture map entirely are now fully stubbed:

```php
'voiceProfile' => ['profile_type' => 'NOVELIST', 'author_voice_dna_profile' => [], 'master_rule_1_hard_bans' => []],
'voiceProfileLabel' => 'stub',
```

All 27 blades pass step3 render (`ok` — no thrown exceptions, non-zero byte output).

---

## Validation

Local validation run:

```
php "Adaptation layer/.../pipeline-upgrade-v2-validation-runner.php" step3
```

Output (selected):
```
ok   ai.agents.adaptation.story-session-map.prompt  [1092 bytes]
ok   ai.agents.adaptation.entry-point-diagnosis.prompt  [1034 bytes]
ok   ai.agents.adaptation.session-architecture.prompt  [668 bytes]
ok   ai.agents.adaptation.choice-design.prompt  [753 bytes]
ok   ai.agents.adaptation.consequence-mapping.prompt  [987 bytes]
ok   ai.agents.adaptation.session-close.prompt  [832 bytes]
ok   ai.agents.adaptation.editorial-verification.prompt  [731 bytes]
=== end step3 ===
```

PHP syntax lint (`php -l`) clean on all 9 modified PHP files.

---

## Pipeline flow after this fix

```
IpTrimmingMerge
  → FormatDetectionJob
    → IpAuditJob
      → [VoiceLockChapterJob × N]
        → VoiceLockMergeJob  ← writes voice_profile
          → StorySessionMapJob         ← voice_profile: alignmentContext
            → EntryPointDiagnosisJob   ← voice_profile: dnaAndBans  [NEW]
            → SessionArchitectureJob   ← voice_profile: dna          [NEW]
            → ChoiceDesignJob          ← voice_profile: dnaAndBans   [NEW]
            → ConsequenceMappingJob    ← voice_profile: dnaAndBans   [NEW]
            → SessionCloseJob          ← voice_profile: dnaAndBans   [NEW]
            → EditorialVerificationJob ← voice_profile: full         [already wired]
            → RuntimeNarratorAssemblyJob → Section 6+7               [already wired]
```

Phases 2–7 now all have constitutional access to the profile at **generation time**. The null guard on each job ensures any pipeline-order regression (e.g. a re-run where Voice Lock merge hasn't completed yet) surfaces immediately as a clear error rather than a silent quality failure.

---

## Impact on Oz editorial failures

The Oz partial-completion run (pre-fix) showed repeated Phase 5 diction drift: `moral complexity`, `reclaimed agency`, `ownerless`, `coercive power`, `socially transgressive`. These are all terms in the Voice Lock IP-specific ban list that Phase 5 was generating without ever seeing it.

After re-adaptation on Cloud with this fix:

- Phase 5 choice outcomes should use Baum diction clusters (`little`, `wicked`, `strange`, `good`, `afraid`, `sorry`) instead of modern evaluative vocabulary
- Phase 3 cold opens should not contain second-person interiority that the Voice Lock specifies as absent from Baum (no deep introspective modernization)
- Phase 7 resolution prose should be plain storybook register grounded in action, feeling, and direct moral naming
- Editorial Section B Q11–Q16 (voice audit) should show improvement from current RED / ~13/23 passing

Runtime Section 14 (authored choice moments) will align with Section 6 (Voice Lock) because both are generated from the same profile.

---

## What is not changed

- System prompts — no changes to any `system-prompt.blade.php` file. The deliverables already say "author's voice" and "reference Voice Profile"; the missing piece was the data payload.
- Voice Lock extraction — `VoiceLockChapterJob`, `VoiceLockMergeJob`, `VoiceLockMergeAgent`, `VoiceLockChapterAgent`, `VoiceLockSchema.php` — untouched.
- Runtime narrator template — `runtime-narrator-template.blade.php`, `RuntimeNarratorTemplateBuilder.php` — untouched. Section 6 and 7 already correct.
- Editorial verification agent output schema — untouched.
- Migration files — no new columns required. `voice_profile` already exists in `story_adaptations`.
- `ChaosEngineService`, `ChaosModeController` — untouched. Runtime behaviour unchanged.

---

## QA stories — pending Cloud re-adaptation

| Story | Slug | Format | Expected improvement |
|-------|------|--------|---------------------|
| The Wonderful Wizard of Oz | `the-wonderful-wizard-of-oz` | Novelist 1A | Phase 5 diction drift resolved; Editorial B improved |
| Alice's Adventures in Wonderland | `alice-in-wonderland` (if applicable) | Novelist 1A | Same pattern |

Run on Cloud after deploy:
```bash
php artisan stories:run-adaptation the-wonderful-wizard-of-oz --force
php artisan adaptation:export the-wonderful-wizard-of-oz
```

Export success criteria: `story_wide.voice_profile` present in JSON output (no longer requires separate tinker extraction).

---

## Known iteration targets (not in scope of this fix)

- **Editorial auto-remediation** — editorial currently flags voice failures but does not re-run earlier phases. A future loop could re-run Phase 5 on RED Section B verdict. This requires an orchestration change to `EditorialVerificationJob` or a new job inserted after it.
- **Section 5 vs Section 6 hierarchy** — `runtime-narrator-template.blade.php` Section 5 loads StoryGuard `layer_4_voice_tonal_canon` (Phase 2 AI output) and Section 6 loads the real Voice Lock profile. Both are "Tier 1, always loaded". With Phase 2 now receiving `alignmentContext` from the real profile, `layer_4_voice_tonal_canon` should become more consistent with Section 6 over time, but an explicit hierarchy note between the two sections may still be warranted.
- **Screenwriter IPs** — no 1B story has been re-adapted since V2.2. The `dnaAndBans` and `dna` slices are format-neutral (same top-level keys); `profile_type: SCREENWRITER` will flow through correctly when the first 1B re-adapt runs.
