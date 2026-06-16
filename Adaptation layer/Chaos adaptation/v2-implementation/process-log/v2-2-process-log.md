# LoreSpinner Pipeline Upgrade V2.2 — Implementation Process Log

**Date started:** 2026-06-13  
**Plan reference:** `.cursor/plans/v2.2_pipeline_integration_88bbda04.plan.md`  
**Source of truth:** `Adaptation layer/Chaos adaptation/V2.2/` + EXECUTION DOCUMENT (Paul Review additions)  
**Deployment:** Laravel Cloud — push triggers automatic redeploy. Pipeline validation runs on Cloud post-deploy.

---

## Operating principle

Prompt text from V2.2 deliverables is copied verbatim into Blade views with only mechanical adaptations:

1. `@include('ai.agents.adaptation._master-context', ...)`
2. Blade variables for `format_detection`, `ip_audit`, and merge fragment placeholders
3. Trailing footer lines dropped

No silent "improvements" to Thomas/Paul prompt text.

---

## Pipeline reorder (V2.2 fix)

**Old order (broken on fresh `--force`):**
```
IpTrimmingMerge → VoiceLock → VoiceLockMerge → FormatDetection → IpAudit → StorySessionMap
```
Voice Lock saw `format = UNKNOWN` and `ip_audit = null`.

**New order (approved):**
```
IpTrimmingMerge → FormatDetection → IpAudit → VoiceLock chapters → VoiceLockMerge → StorySessionMap
```

**Why IpAudit precedes VoiceLock:** V2.2 Deliverables 1A/1B require Format Detection (1A vs 1B routing) and Phase 1 IP Audit scorecard as merge prompt input.

---

## Files changed

### Jobs
- `app/Jobs/Adaptation/IpTrimmingMergeJob.php` — dispatches `FormatDetectionJob`
- `app/Jobs/Adaptation/FormatDetectionJob.php` — chains `IpAuditJob`
- `app/Jobs/Adaptation/IpAuditJob.php` — dispatches VoiceLock chapter batch
- `app/Jobs/Adaptation/VoiceLockMergeJob.php` — chains only `StorySessionMapJob`
- `app/Jobs/Adaptation/VoiceLockChapterJob.php` — passes format to agent
- `app/Jobs/Adaptation/RunAdaptationPipelineJob.php` — comment diagram updated

### Agents / schema
- `app/Ai/Agents/Adaptation/VoiceLockSchema.php` — **new** novelist + screenwriter schemas
- `app/Ai/Agents/Adaptation/VoiceLockAgent.php` — format routing
- `app/Ai/Agents/Adaptation/VoiceLockMergeAgent.php` — format routing + ip_audit injection
- `app/Ai/Agents/Adaptation/VoiceLockChapterAgent.php` — format routing + expanded fragment schema
- `app/Ai/Agents/Adaptation/ConsequenceMappingAgent.php` — `consequence_visibility` on paths

### Blade prompts (new)
- `resources/views/ai/agents/adaptation/voice-lock/chapter-system-prompt-novelist.blade.php`
- `resources/views/ai/agents/adaptation/voice-lock/chapter-system-prompt-screenwriter.blade.php`
- `resources/views/ai/agents/adaptation/voice-lock/system-prompt-novelist.blade.php`
- `resources/views/ai/agents/adaptation/voice-lock/system-prompt-screenwriter.blade.php`
- `resources/views/ai/agents/adaptation/voice-lock/_voice-lock-universal-bans.blade.php`

### Blade prompts (updated)
- `voice-lock/merge-prompt.blade.php` — V2.2 synthesis fields
- `chaos/runtime-narrator-template.blade.php` — Section 6 expanded + Section 17 Paul Review
- `session-architecture/system-prompt.blade.php` — beat ending + first-3-min rules
- `choice-design/system-prompt.blade.php` — choice contrast rules
- `consequence-mapping/system-prompt.blade.php` — consequence visibility rule

### Builder / validation
- `app/Ai/Adaptation/RuntimeNarratorTemplateBuilder.php` — compression for collocation quotes
- `validation/pipeline-upgrade-v2-validation-runner.php` — V2.2 blades + `step_v22`

### Docs
- `validation/pipeline-upgrade-v2-2-validation-runbook.md` — **new** Cloud operator guide
- `process-log/v2-2-process-log.md` — this file

---

## Schema deltas (`voice_profile` JSON)

**Top level:** `profile_type` = `NOVELIST` | `SCREENWRITER`

**Novelist (1A) additions in `author_voice_dna_profile`:**
- `narrator_perspective`, `dialogue_tag_patterns`
- `collocation_fingerprint` (15–20), `negative_space_map` (≥5), `comparative_exclusion` (2–3)
- `show_explain_ratio`
- `signature_writing_techniques[].frequency`

**Screenwriter (1B) additions:**
- `action_line_metrics`, `screenplay_structure_metrics`
- `emotional_vocabulary_hierarchy`, `screenplay_to_prose_protocol`

**Consequence mapping:** `paths[].consequence_visibility` with what/when/how fields.

Legacy `voice_profile` rows without `profile_type` still render via defensive Section 6 defaults.

---

## Reconciliations

- **Chapter/merge split retained** — production adaptation of V2.2 monolithic Voice Lock phase
- **Binary format routing** — `NOVEL` → 1A, `SCREENPLAY` → 1B
- **Paul Review boundaries** — cadence/custom input in D8 only; beat rules in D3; contrast in D4; visibility design in D5
- **300–350 word runtime target** applies to narrator turns, not pipeline choice outcome text (115–125 words)

---

## QA test stories

| Title | Format | Reference | Status |
|-------|--------|-----------|--------|
| Wizard of Oz | Novelist 1A | REFERENCE Baum | Pending Cloud re-adapt |
| Sherlock | Novelist 1A | REFERENCE Doyle | Pending Cloud re-adapt |
| Anima Machina | Screenwriter 1B | (none) | Deferred |

Validation outcomes will be recorded here after operator runs on Laravel Cloud.

---

## June 15 — Session-start opening injection fix

**Problem:** `loadSessionContext()` was collapsing `cold_open` and `opens_with` into a single `opening_scene` winner. When `opens_with` was non-empty (sessions 2+), the full Phase 3 cold open — 120–180 word authored prose with `must_reintroduce` and the 3-minute opening hook — was silently discarded. Only the one-sentence arc handoff reached the narrator.

**Root cause:** The doc's intent was `opens_with` = continuity seed *supplementing* the cold open, but the code treated it as a *replacement*.

**Fix:** `loadSessionContext()` now returns four separate fields; the old `opening_scene` collapse is removed.

| New field | Source | Sessions |
|---|---|---|
| `cold_open` | `entry_point_diagnosis.cold_open` | All |
| `opening_handoff` | `arc_progression.opens_with` | 2+ (empty for session 1) |
| `emotional_promise` | `entry_point_diagnosis.emotional_promise` | All |
| `must_reintroduce` | `entry_point_diagnosis.format_specific_cut.must_reintroduce` | All |

`renderSystemPrompt()` gained an `isSessionStart: bool` parameter replacing the old `?string $currentScene`. On `true`, a new `buildOpeningSection()` helper assembles:
- Sessions 2+: handoff → cold open → emotional promise → must_reintroduce
- Session 1: cold open → emotional promise → must_reintroduce

On `false` (mid-session turns), the injection point receives a single continuation line.

### Files changed

- `app/Services/ChaosEngineService.php` — `loadSessionContext()` return shape, `renderSystemPrompt()` signature, `buildOpeningSection()` new private method
- `app/Http/Controllers/User/GameController.php` — `begin()` and `nextSession()` call sites (removed dead `$sceneForOpener` variable, pass `isSessionStart: true`)
- `app/Http/Controllers/User/Game/PromptController.php` — `store()` call site (`isSessionStart: false`)
- `app/Http/Controllers/ChaosMode/ChaosModeController.php` — `start()`, `continueTurn()`, `continueSession()` call sites
- `app/Console/Commands/DumpChaosPromptCommand.php` — removed `$openingScene` variable, pass `isSessionStart: true`
- `validation/pipeline-upgrade-v2-validation-runner.php` — `step_v22` extended with 13 new assertions for context shape + call sites

### Behaviour before vs after

| Scenario | Before | After |
|---|---|---|
| Session 1 open | cold open injected | cold open + emotional_promise + must_reintroduce |
| Session 2+ open | only `opens_with` one-liner (cold open dropped) | handoff + **full cold open** + emotional_promise + must_reintroduce |
| Mid-session turn | `(continuation turn…)` | same — explicit continuation line |

### No re-adaptation required

The fix is runtime-only (injection layer). Existing `runtime_narrator_prompt` caches remain valid — the `[OPENING_SCENE_INJECTION_POINT]` token is still in every cached prompt; only what gets substituted into it changes.

---

## June 15 — Mechanical Section 13 strip on continuation turns

### Problem

Even after the `isSessionStart` refactor, the cached `runtime_narrator_prompt` still contains the full Section 13 block on every turn: the header, the "THIS IS THE HARD START" instruction, and the FIRST-3-MINUTES PROTOCOL. On continuation turns, `strtr` replaced `[OPENING_SCENE_INJECTION_POINT]` with a one-line continuation message, but the surrounding narrator instructions stayed in the prompt — the model was still asked to self-suppress them rather than mechanically not seeing them.

### Fix

Split `renderSystemPrompt()` into two paths:

**Session start (`isSessionStart = true`)**
Run `strtr()` with all four tokens. `[OPENING_SCENE_INJECTION_POINT]` receives the full opening block from `buildOpeningSection()`.

**Continuation (`isSessionStart = false`)**
Before `strtr`, locate `=== SECTION 13 —` (the section header anchor) and `[OPENING_SCENE_INJECTION_POINT]` (the injection token) using `strpos`. Replace the entire span between them (inclusive) with a single continuation marker:
`(Continuation turn — opening already delivered. Resume from conversation history; do not re-cold-open.)`

Then run `strtr()` with only the three remaining tokens. `[OPENING_SCENE_INJECTION_POINT]` is no longer in the string so its key goes unused — that is intentional.

### Effect

The model never reads "THIS IS THE HARD START" or the FIRST-3-MINUTES PROTOCOL on turn 2+. The instruction is mechanically absent, not instructionally suppressed.

### Files changed

- `app/Services/ChaosEngineService.php` — `renderSystemPrompt()` split into two `strtr` paths with Section 13 string-position strip on continuation path
- `validation/pipeline-upgrade-v2-validation-runner.php` — `step_v22` extended with 6 assertions for the strip logic

### No re-adaptation required

Existing `runtime_narrator_prompt` caches remain valid. The strip works on the cached string at runtime.

---

## Fix: RuntimeNarratorAssemblyJob — 128,000-char limit breach (Oz S2/S3/S4)

**Problem**: Sessions 2, 3, and 4 of *The Wonderful Wizard of Oz* threw `Runtime narrator template exceeds 128000 chars after all compression strategies`. Sessions 5 and 6 passed; Session 1 passed by only 83 characters.

**Root cause A — §12 includes pre-session cut events**
`RuntimeNarratorTemplateBuilder::loadSessionEvents()` loaded every event where `session_number = N`, including events that Phase 3 (`EntryPointDiagnosisAgent`) explicitly cut by setting `start_event_position`. For S2, this meant 19 extra events (positions 50–68) in §12 when the session actually opens at position 69. At `full` source mode each cut event carries its full `content` field (~1,000+ chars), producing ~19,000 bytes of source text the narrator was never meant to see. This also creates a logical contradiction between §12 (shows cut events) and §13 (`cold_open` / `start_event_position` says skip them).

**Fix A**: `loadSessionEvents()` now accepts `$startEventPosition: int` and adds `WHERE events.position >= $startEventPosition` when the value is non-zero. Call site in `build()` reads this from `$session->entry_point_diagnosis['start_event_position']`.

**Root cause B — §15 renders editorial metadata, not narrator instruction**
The consequence path rendering line included `next_session_payoff` and `defining_line_captured`. These two fields account for ~41–44% of §15's total content per session (~3,000–3,600 rendered chars). Neither field is operational narrator instruction:
- `next_session_payoff`: describes what pays off in the *next* session — wrong temporal context for the current narrator. Actual state tracking flows through the runtime world-state injection.
- `defining_line_captured`: a ≤20-word editorial "trophy quote" from Phase 5 design. The narrator generates better contextually-appropriate lines in voice; this field is design documentation, not a runtime instruction.

The existing compression cascade (§12 compress → titles-only, §6 drop-quotes) never touched §15, so S3 and S4 still breached the cap even at maximum compression.

**Fix B**: §15 path rendering stripped to `label: now: {immediate_effect} | echo: {current_session_echo}`. The `freeform_guidelines` block is untouched — those ARE operational instructions.

**Savings per session (mb_strlen chars)**:
- Fix A (S2): ~4,655 chars removed from §12 (19 fewer events at titles-only)
- Fix B (all sessions): ~3,100–3,600 chars removed from §15 permanently
- S1 after Fix B: drops from 127,917 → ~124,800 (headroom for future stories)
- S2/S3/S4: all pass comfortably after both fixes

**Files changed**:
- `app/Ai/Adaptation/RuntimeNarratorTemplateBuilder.php` — `loadSessionEvents()` signature + position filter
- `resources/views/ai/agents/chaos/runtime-narrator-template.blade.php` — §15 path line (remove payoff + defline)

**Required action**: Re-dispatch `RuntimeNarratorAssemblyJob` for failing sessions (S2, S3, S4 of any story that failed). No re-adaptation needed — assembly only.

---

## Known issues / iteration targets

- Merge job timeout remains 420s — monitor on first full-novel re-adapt after V2.2 deploy
- Anima Machina (1B screenplay QA) deferred until novelist baselines pass
- Existing stories require `php artisan stories:run-adaptation <slug> --force` on Cloud for full V2.2 enforcement
