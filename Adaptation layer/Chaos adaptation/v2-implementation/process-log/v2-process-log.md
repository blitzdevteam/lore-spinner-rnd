# LoreSpinner Pipeline Upgrade V2 — Implementation Process Log

**Date started:** 2026-05-24
**Branch:** `main` (per Daniel's instruction — full upgrade lands on main; no feature branch)
**Plan reference:** `.cursor/plans/lorespinner_pipeline_upgrade_v2_*.plan.md`
**Source of truth for all prompt text:** the nine `.md` deliverables in `Adaptation layer/Chaos adaptation/#4 DOCS - LORESPINNER SYSTEM PIPELINE ADDITION PROMPTS - V2 (MAY 21ST 2026)/`.
**Implementation guidance:** the `.pdf` / `.docx` files in `Adaptation layer/Chaos adaptation/#5 DOCS - V2 PIPELINE PHASE BREAKOUT AND IMPLEMENTATION GUIDE/`.
**Validation runbook:** `Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runbook.md`.
**Validation runner script:** `Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php`.

---

## Operating principle

Every prompt text shipped in this branch is **verbatim** from the corresponding `#4` Deliverable `.md` file, with the only allowed adaptations being:

1. Replacement of `[PASTE MASTER CONTEXT BLOCK HERE]` with `@include('ai.agents.adaptation._master-context', ...)`.
2. Replacement of `[PASTE PHASE X OUTPUT]` placeholders with Blade `{{ json_encode(...) }}` or `{{ $variable }}` expressions sourced from prior phase outputs.
3. Substitution of source-text placeholders with the Blade variables Lorespinner's existing jobs already pass into `prompt.blade.php` views.
4. Trailing line normalization (Blade requires a trailing newline; the docs end with `## END OF DELIVERABLE N` which is dropped because it would leak into the LLM prompt).

No prompt instruction, ban-list entry, task wording, or count threshold has been edited or "improved." If the canonical prompt says *exactly 4 branching choices*, the schema and the prompt both say exactly 4.

---

## Reconciliations between earlier discussion and the canonical docs

### 0. Daniel's correction (2026-05-24): in-place upgrade, no v2 shadow columns or legacy code paths

> "World state upgraded version will not create v2 it will be the upgraded I don't want unused variables. go as planned and upgrade."

Applied across the entire batch. There are NO `_v2` shadow columns, NO `_v2_delta` schema siblings, NO legacy fallback branches.

**Runtime prompt path — hard cutover, NO V1 fallback.**

`ChaosModeController::renderSystemPrompt()` reads the cached `session_adaptations.runtime_narrator_prompt` exclusively. If null, the `start` / `continueSession` endpoints return:

> `422 — This story has not been re-adapted under V2 yet. Re-run the adaptation pipeline (php artisan stories:run-adaptation <slug> --force) before starting Chaos Mode.`

There is no fallback to `resources/views/ai/agents/chaos/system-prompt.blade.php` or to `partials/{story}.blade.php`. Those files remain on disk as historical reference but are not loaded by any code path. `ChaosStoryConfig` has had `voice_partial` removed. The `show()` endpoint gates each story with a `v2_ready` flag (true iff at least one session has a non-empty `runtime_narrator_prompt`) so the story selector UI hides un-adapted stories.

**Persistent state shape — in-place upgrade of `world_state`.**

The existing `chaos_sessions.world_state` JSON column is upgraded in place to the new literary-memory shape (object_states, npc_dispositions, world_flags, unresolved_promises, emotional_ledger, location, knowledge, notes, player_style, recent_action_history). There is NO `world_state_v2` sidecar column and NO `WorldStateV2` value object. `mergeStateDelta()` on `ChaosModeController` consumes the new shape directly with no legacy code path.

`**ChaosNarrationSchema.state_delta` — in-place upgrade.**

The single `state_delta` block in the narrator's structured response is the new literary-memory shape. There is NO `world_state_v2_delta` sibling key. Old V1 keys (`conditions`, `items`, `location`, `relationships`, `knowledge`, `notes` as separate top-level deltas) are removed; they are subsumed by `object_states`, `relationship_updates`, `world_flags`, `unresolved_promises`, `emotional_ledger_entries` plus the (still scalar) `location`, `knowledge`, `notes` natural-language fields.

**Consequence for old chaos sessions.** Any of the 9 existing Chaos stories whose runtime prompt has not been assembled is temporarily unplayable until `php artisan stories:run-adaptation <slug> --force` is run for it. This is a deliberate trade-off; carrying dead-code legacy paths through the upgrade was rejected. All 9 stories are still in active development, so the cost is acceptable.

### Original four cautions

The user's pre-execution analysis raised four cautions. The canonical Deliverable text resolves them as follows:

### 1. Choice density — canonical says **hard quotas**, not caps

Deliverable 3 Task 2 "INTERACTION COUNT VERIFICATION" requires:

- Branching choices: must be exactly 4
- Emotional choices: must be 4–6
- Posture shifts: must be 6–10

The user's preference was "design *up to* 4 / *up to* 4–6 / *up to* 6–10 — use fewer when the story breathes better." We implement the canonical quotas. The user's preference is recorded here as a known iteration target. Loosening from quotas to caps is a future prompt revision that can be done without touching schema (schema enforces minimums via the LLM's structured-output validator only; the `branching_choices` array is required but length is described, not constrained by JSON Schema cardinality).

### 2. NPC dispositions — canonical uses **qualitative tags**, not raw numeric stats

Deliverable 2 Task 6B specifies `Trust: LOW / MEDIUM / HIGH` plus narrative descriptors (`What raises it` / `What lowers it`) and a behavioral-triggers list. This is between strict prose and `{trust: 0.6}`. The schema mirrors it exactly. No numeric scores are ever returned by the pipeline or persisted in runtime state.

### 3. Story-native alignment labels at runtime

Deliverable 2 Task 9 generates per-IP labels (e.g. for Alice: "Curious / Proper / Contrary"). Deliverable 8 Section 7 stores the internal `chaotic|lawful|neutral` tendency only in Tier 1 state. The Chaos runtime translates these to the story-native labels via a translator helper in `ChaosModeController` before injection into the assembled prompt. The literal strings "CHAOTIC", "LAWFUL", "NEUTRAL" never appear in any narrator-visible runtime section.

### 4. Cold open vs start event

Already correctly separated in the existing code. `EntryPointDiagnosisAgent` returns both `cold_open` (prose) and `start_event_position` (resolved to `start_event_id` by `EntryPointDiagnosisJob`). Deliverable 8 puts cold open in Section 13 and the start-event-anchored script in Section 12. The assembler preserves this split.

---

## Schema changes

### `story_adaptations`


| Column          | Type                   | Purpose              |
| --------------- | ---------------------- | -------------------- |
| `ip_trimming`   | `longText` (json cast) | Deliverable 7 output |
| `voice_profile` | `longText` (json cast) | Deliverable 1 output |


### `session_adaptations`


| Column                          | Type        | Purpose                                           |
| ------------------------------- | ----------- | ------------------------------------------------- |
| `runtime_narrator_prompt`       | `longText`  | Assembled Deliverable 8 template (≤ 65 000 chars) |
| `runtime_narrator_assembled_at` | `timestamp` | When the assembly job last wrote the prompt       |


### `chaos_sessions`


| Column                 | Type       | Purpose                                                                                                                                                                                               |
| ---------------------- | ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `world_state`          | `json`     | UPGRADED IN PLACE — new literary-memory shape (location, conditions, items, object_states, relationship_updates, world_flags, knowledge, notes, player_style, unresolved_promises, emotional_ledger). |
| `alignment_scaffold`   | `json`     | NEW — hidden `{chaotic, lawful, neutral}` int counter; never injected into narrator prompt                                                                                                            |
| `symbolic_memory`      | `longText` | NEW — Section 8 runtime updates rendered as natural language                                                                                                                                          |
| `defining_choice_id`   | `string`   | NEW — captured when a turn resolves a branching choice (Social Echo)                                                                                                                                  |
| `defining_choice_line` | `text`     | NEW — matching Phase 5 Task 8 defining line                                                                                                                                                           |
| `is_climactic_choice`  | `boolean`  | NEW — Tier 3 state loader trigger                                                                                                                                                                     |


No legacy runtime-prompt fallback. Stories not yet re-adapted under V2 are unplayable (controller returns 422) until `stories:run-adaptation <slug> --force` populates `session_adaptations.runtime_narrator_prompt` for them. The new state columns start NULL and populate as turns occur.

---

## Pipeline shape

### V2.1 map/merge architecture (2026-05-25 refactor — resolves IP Trimming + Voice Lock timeouts)

The original V2 shape dispatched `IpTrimmingJob` and `VoiceLockJob` as single-pass jobs against the full source text (~200k chars). Both hit the OpenAI `/v1/responses` cURL 600s timeout with 0 bytes received. They have been replaced with chapter-by-chapter map/merge pipelines.

```
RunAdaptationPipelineJob
  -> [IpTrimmingChapterJob × N chapters]  (parallel batch, gpt-5.4-mini per chapter)
  -> IpTrimmingMergeJob                   (PHP merge + spine synthesis call, gpt-5.4)
  -> [VoiceLockChapterJob × N chapters]   (parallel batch, gpt-5.4 per chapter)
  -> VoiceLockMergeJob                    (full Voice DNA synthesis call, gpt-5.4)
  -> Bus::chain([
       FormatDetectionJob,                (uses trimmed source from ip_trimming)
       IpAuditJob,                        (uses trimmed source + story_spine + world_rules)
       StorySessionMapJob v2,             (uses world_rules + conversion_notes)
     ])
  -> (per session batch)
       EntryPointDiagnosisJob             (uses Story::getSessionTrimmedText())
       SessionArchitectureJob v2          (uses Story::getSessionTrimmedText())
       ChoiceDesignJob v2                 (uses Story::getSessionTrimmedText())
       ConsequenceMappingJob v2
       SessionCloseJob                    (uses Story::getSessionTrimmedText())
       EditorialVerificationJob v2
       RuntimeNarratorAssemblyJob         (Deliverable 8)
  -> AdaptationStatusReconciliationJob
```

#### Key V2.1 details

- `IpTrimmingChapterJob` (gpt-5.4-mini, 180s) stores a partial fragment (story_spine_fragment, world_rules_fragments, content_triage_log, interactive_conversion_notes, trimmed_chapter_text) in Laravel cache at `ip_trimming_fragment:{story_id}:{chapter_id}`.
- `IpTrimmingMergeJob` collects all fragments, PHP-merges world_rules/triage/conversion_notes/trimmed_text, makes ONE small spine synthesis call (gpt-5.4), writes the Deliverable 7 package to `story_adaptations.ip_trimming`. Includes `trimmed_source_text.chapter_segments[]` for session-accurate slicing.
- `VoiceLockChapterJob` (gpt-5.4, 300s) stores a compact voice observation fragment in cache at `voice_lock_fragment:{story_id}:{chapter_id}`. Uses FULL ORIGINAL chapter content.
- `VoiceLockMergeJob` collects all voice fragments, makes ONE full synthesis call (gpt-5.4), writes the Deliverable 1 package to `story_adaptations.voice_profile`. Dispatches FormatDetection → IpAudit → StorySessionMap chain.
- `Story::getSessionTrimmedText(int $sessionNumber)` resolves chapter segments via `events.session_number`, returning the correct trimmed text slice for the given session. Previously all session jobs used a fixed first-16k-chars window of the raw source.
- All 11 adaptation agents upgraded: `gpt-5.2` → `gpt-5.4`.
- `IpTrimmingJob.php` and `VoiceLockJob.php` deleted (dead code — nothing dispatches them).

### Original V2 shape (superseded — kept for reference only)

```
RunAdaptationPipelineJob
  -> IpTrimmingJob              (DELETED — replaced by chapter batch + merge)
  -> FormatDetectionJob
  -> IpAuditJob
  -> VoiceLockJob               (DELETED — replaced by chapter batch + merge)
  -> StorySessionMapJob v2
  -> (per session batch) ...
  -> AdaptationStatusReconciliationJob
```

---

## File inventory (created)

### V2 batch (original)

- `app/Ai/Agents/Adaptation/IpTrimmingAgent.php`
- `app/Ai/Agents/Adaptation/VoiceLockAgent.php`
- `app/Jobs/Adaptation/RuntimeNarratorAssemblyJob.php`
- `app/Ai/Adaptation/RuntimeNarratorTemplateBuilder.php`
- `resources/views/ai/agents/adaptation/ip-trimming/{system-prompt,prompt}.blade.php`
- `resources/views/ai/agents/adaptation/voice-lock/{system-prompt,prompt}.blade.php`
- `resources/views/ai/agents/chaos/runtime-narrator-template.blade.php`
- `database/migrations/2026_05_24_000001_add_v2_pipeline_columns_to_story_adaptations.php`
- `database/migrations/2026_05_24_000002_add_runtime_narrator_prompt_to_session_adaptations.php`
- `database/migrations/2026_05_24_000003_add_v2_state_columns_to_chaos_sessions.php`
- `Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runbook.md`
- `Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php`

### V2.1 batch — map/merge refactor (2026-05-25)

- `app/Ai/Agents/Adaptation/IpTrimmingChapterAgent.php` — per-chapter extraction, gpt-5.4-mini
- `app/Ai/Agents/Adaptation/IpTrimmingMergeAgent.php` — spine synthesis, gpt-5.4
- `app/Ai/Agents/Adaptation/VoiceLockChapterAgent.php` — per-chapter voice analysis, gpt-5.4
- `app/Ai/Agents/Adaptation/VoiceLockMergeAgent.php` — full Voice DNA synthesis, gpt-5.4
- `app/Jobs/Adaptation/IpTrimmingChapterJob.php`
- `app/Jobs/Adaptation/IpTrimmingMergeJob.php`
- `app/Jobs/Adaptation/VoiceLockChapterJob.php`
- `app/Jobs/Adaptation/VoiceLockMergeJob.php`
- `resources/views/ai/agents/adaptation/ip-trimming/chapter-system-prompt.blade.php`
- `resources/views/ai/agents/adaptation/ip-trimming/chapter-prompt.blade.php`
- `resources/views/ai/agents/adaptation/ip-trimming/merge-system-prompt.blade.php`
- `resources/views/ai/agents/adaptation/ip-trimming/merge-prompt.blade.php`
- `resources/views/ai/agents/adaptation/voice-lock/chapter-system-prompt.blade.php`
- `resources/views/ai/agents/adaptation/voice-lock/chapter-prompt.blade.php`
- `resources/views/ai/agents/adaptation/voice-lock/merge-prompt.blade.php`

### V2.1 batch — deleted (dead code)

- `app/Jobs/Adaptation/IpTrimmingJob.php` (**deleted** — replaced by chapter batch + merge)
- `app/Jobs/Adaptation/VoiceLockJob.php` (**deleted** — replaced by chapter batch + merge)

## File inventory (modified)

- `app/Enums/Adaptation/AdaptationStatusEnum.php` — `IP_TRIMMING`, `VOICE_LOCK`
- `app/Models/StoryAdaptation.php` — fillables + casts
- `app/Models/SessionAdaptation.php` — fillables + casts
- `app/Models/ChaosSession.php` — fillables + casts
- `app/Jobs/Adaptation/RunAdaptationPipelineJob.php` — prepend IpTrimming + insert VoiceLock
- `app/Jobs/Adaptation/StorySessionMapJob.php` — append `RuntimeNarratorAssemblyJob` to per-session chain
- `app/Jobs/Adaptation/AdaptationStatusReconciliationJob.php` — require `runtime_narrator_prompt`
- `app/Ai/Agents/Adaptation/StorySessionMapAgent.php` — Tasks 6-9 schema
- `app/Ai/Agents/Adaptation/SessionArchitectureAgent.php` — 5-beat schema
- `app/Ai/Agents/Adaptation/ChoiceDesignAgent.php` — 3 choice categories
- `app/Ai/Agents/Adaptation/ConsequenceMappingAgent.php` — full consequence maps
- `app/Ai/Agents/Adaptation/EditorialVerificationAgent.php` — 23-question schema
- `resources/views/ai/agents/adaptation/story-session-map/system-prompt.blade.php`
- `resources/views/ai/agents/adaptation/session-architecture/system-prompt.blade.php`
- `resources/views/ai/agents/adaptation/choice-design/system-prompt.blade.php`
- `resources/views/ai/agents/adaptation/consequence-mapping/system-prompt.blade.php`
- `resources/views/ai/agents/adaptation/editorial-verification/system-prompt.blade.php`
- All matching `prompt.blade.php` user-payload views — extended to pass voice profile, persistent state schema, story guard layers, etc. into the relevant phases.
- `app/Http/Controllers/ChaosMode/ChaosModeController.php` — load cached `runtime_narrator_prompt` exclusively (422 if null), tiered state loader, story-native alignment translator, `mergeStateDelta` consumes the new literary-memory shape directly into the existing `world_state` column, no legacy partial fallback
- `app/Ai/Agents/Chaos/ChaosNarrationSchema.php` — `state_delta` upgraded in place to the literary-memory shape (no `world_state_v2_delta` sibling key)
- `app/ChaosMode/ChaosStoryConfig.php` — `voice_partial` key removed from the whitelist

---

## Risk acknowledgements

1. **IP Trimming + Voice Lock — chapter-batched (V2.1).** Both phases now run per-chapter with smaller context windows; timeouts on large sources are eliminated. If a chapter is itself very large (e.g. a single 40k-char act), the chapter-agent timeout (180s IP / 300s Voice) still applies — in practice this means chapters under ~8k words are safe. Stories with anomalously long chapters should be split at chapter-authoring time, not at adaptation time.
2. **65 000-char ceiling on assembled prompt.** Long source pages can push the assembled prompt past the cap. The assembler implements Deliverable 8's compression cascade (compress Section 12 first → drop Voice Profile examples → flag for editorial split). The runbook covers detection.
3. **Cost.** Running V2 pipeline on the 9 existing Chaos stories costs roughly $5.50–$9.00 per IP (~$60–$80 total) per Deliverable 7's figures. Daniel runs this manually per story via `php artisan stories:run-adaptation`.
4. **Production game runtime unchanged.** The non-Chaos `PromptController` / `NarrationAgent` flow is not touched in this batch. The new fields it might consume are present in DB but unused on that surface today.

---

## Implementation checkpoints

- Migrations + enum + model casts authored.
- Pre-pipeline jobs (IP Trimming, Voice Lock).
- Phase 2/4/5/6/8 prompt + schema upgrades.
- Runtime Narrator Template + assembler + per-session assembly job.
- Chaos Mode runtime rewire + in-place `world_state` upgrade + new `ChaosNarrationSchema.state_delta`.
- Reconciler update — `AdaptationStatusEnum::COMPLETED` only when every session has a non-empty `runtime_narrator_prompt`.
- Validation runbook + runner.
- Final process log pass.

## Known limitations after this batch

1. **Story re-adaptation is manual AND mandatory.** Every existing Chaos story must be re-run through `php artisan stories:run-adaptation <slug> --force` before its sessions become playable again. Until then, `start` / `continueSession` return 422. This is the in-place upgrade trade-off Daniel accepted.
2. **Voice Lock map/merge is automatic (V2.1).** Replaced the manual split-and-merge strategy from V2. Voice Lock now processes each chapter independently and synthesises a unified voice profile automatically. Manual split is no longer needed.
3. **65 000-char prompt cap is a soft fail.** The compression cascade in `RuntimeNarratorTemplateBuilder` (full source → compressed → titles-only → drop voice quotes) handles most cases. If even the most aggressive level still overflows, the assembly job logs `runtime_narrator_assembly.compression_failed` and the reconciler refuses to mark the story COMPLETED. The editorial fix is to split the offending session in `story_session_map.session_allocation` and re-run from Phase 4. There is no fallback render path.
4. **Choice density is canonical quotas (4 / 4–6 / 6–10), not caps.** Daniel's preferred "use fewer when the story breathes better" relaxation is a future prompt revision — the schemas do not enforce array cardinality at the JSON-Schema level, so loosening will not require schema changes.
5. **Production game runtime not touched.** Only Chaos Mode reads the V2 outputs. The Social Echo / production-game surface gets the new fields stored in DB but they are unused on that surface today.
6. `**voice_partial` already removed.** `ChaosStoryConfig` ships without the key. Per-story partial files under `resources/views/ai/agents/chaos/partials/` remain on disk as historical reference but are not loaded by any code path.

## Validation handoff (final)

Daniel runs `Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runbook.md` against Laravel Cloud. The 14-step runner script `Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php` automates the mechanical probes (migrations, casts, Blade render, runtime template render, alignment translator, persisted outputs, prompt size, hard-ban scan, tiered loader, 422 gate for un-adapted stories, reconciler gate). The remaining manual steps are the actual Chaos Mode start/turn HTTP calls and visual confirmation of climactic-turn Tier 3 behaviour.

The rollback anchor (commit SHA immediately before this batch lands on `main`) is **`89c6e2d`** — `feat(writer-lab): full extraction visibility, cold-open/start-event separation, light mode, font boost` (Danielnrahimi, 2026-05-24). Documented at the top of the runbook under "Rollback anchor". Migrations can be rolled back with `php artisan migrate:rollback --step=3`; this drops the four new chaos_sessions columns (`alignment_scaffold`, `symbolic_memory`, `defining_choice_id`, `defining_choice_line`, `is_climactic_choice`), and the `world_state` column is left intact. Because the upgrade is in-place, rolling back does NOT restore the pre-V2 `world_state` shape — live chaos sessions written after deploy will contain the new literary-memory keys. The only true rollback path is to checkout the pre-batch SHA and re-deploy. A "soft disable" is `UPDATE session_adaptations SET runtime_narrator_prompt = NULL` — the controller then returns 422 for every story (no V1 fallback render path), gating Chaos Mode behind a clear "needs re-adaptation" message until the SHA rollback happens.

## Validation summary (Cursor pre-handoff smoke)

Run locally before handing off; these are quick and do not need the queue or external services:


| Probe                                                                                                   | Status |
| ------------------------------------------------------------------------------------------------------- | ------ |
| `php -l` across all modified PHP files                                                                  | green  |
| Runner step 2 (model casts present, no `world_state_v2` sidecar — in-place upgrade respected)          | green  |
| Runner step 3 (every new/upgraded Blade renders — including 7 new V2.1 views)                          | green  |
| Runner step 4 (runtime narrator template renders with all 4 injection markers)                          | green  |
| Runner step 5 (no `chaotic`/`lawful`/`neutral` leak; story-native label present)                       | green  |
| All adaptation agents confirmed on `gpt-5.4` (was `gpt-5.2`)                                           | green  |
| `IpTrimmingChapterAgent` on `gpt-5.4-mini`, `VoiceLockChapterAgent` on `gpt-5.4`                       | green  |
| Dead code removed (`IpTrimmingJob.php`, `VoiceLockJob.php` deleted)                                    | green  |


Runner steps 1 + 6–14 require Laravel Cloud (live DB, queue worker, real Chaos endpoints). Daniel runs those manually per the runbook.

---

## Doc reorg — 2026-05-24 (post-implementation)

Per Daniel: the docs belong inside `Adaptation layer/Chaos adaptation/` in route-friendly sub-dirs (no `#N`, no SCREAMING_CASE, no spaces — these paths may surface in routes / CLI invocations / log scrapes). Moved the three V2 docs out of their scattered initial locations into a single `v2-implementation/` slot, with `process-log/` and `validation/` as the two clearly-scoped children:

| Before | After |
| ------ | ----- |
| `chaos-mode/v2-process-log.md` | `Adaptation layer/Chaos adaptation/v2-implementation/process-log/v2-process-log.md` |
| `Adaptation layer/debug/pipeline-upgrade-v2-validation-runbook.md` | `Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runbook.md` |
| `Adaptation layer/debug/pipeline-upgrade-v2-validation-runner.php` | `Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php` |

Path repointing applied:

- Runner `__DIR__` autoload + bootstrap walk-up changed from `'/../../...'` (2 levels) to `'/../../../../...'` (4 levels: `validation/` → `v2-implementation/` → `Chaos adaptation/` → `Adaptation layer/` → repo root).
- All 14 invocation lines in the runbook (`php "Adaptation layer/debug/..."`) rewritten to the new path.
- All cross-references inside this process log + runbook header repointed.
- Rollback SHA `89c6e2d` stamped into the runbook's "Rollback anchor" section.
- Post-move smoke: `php "…/validation/pipeline-upgrade-v2-validation-runner.php" step3` → green (every Blade renders), confirming bootstrap path still resolves.

The existing peer doc `Adaptation layer/debug/curt-fix-validation-runner.php` is intentionally left in `debug/` — that runner predates this batch and is unrelated to the V2 upgrade.

---

## New stories wave — 2026-05-29

Four stories need to be made playable in Chaos Mode under V2. Two were already seeded and in `ChaosStoryConfig` (just need the V2 pipeline run); two are new additions requiring seeding first.

### Status at handoff

| Story | In ChaosStoryConfig | In DB | TXT exists | V2 pipeline |
|---|---|---|---|---|
| The Adventure of the Speckled Band (Sherlock) | ✅ | ✅ | ✅ | ❌ needs run |
| The Tell-Tale Heart | ✅ | ✅ | ✅ | ❌ needs run |
| The Masque of the Red Death | ✅ (added 2026-05-29) | ❌ needs seeding | ✅ (converted from PDF) | ❌ needs run |
| The Wonderful Wizard of Oz | ✅ (added 2026-05-29) | ❌ needs seeding | ✅ | ❌ needs run |

### Code changes in this batch (2026-05-29)

1. **PDF → TXT conversion** — `database/stories/RnD/The Masque of the Red Death copy.pdf` converted to `database/stories/The Masque of the Red Death_script.txt` (13 768 bytes, 209 lines). Used `smalot/pdfparser` inline via PHP — same logic as `AddSingleStorySeeder::convertPdf()`.

2. **`app/ChaosMode/ChaosStoryConfig.php`** — two entries appended:
   - `the-masque-of-the-red-death` / protagonist `Prospero` / rating MATURE
   - `the-wonderful-wizard-of-oz` / protagonist `Dorothy` / rating EVERYONE

3. **`database/seeders/AddSingleStorySeeder.php`** — `getStoryConfig()` now dispatches on `SEED_STORY` env var:
   - `SEED_STORY=masque` → `configMasque()`
   - `SEED_STORY=wizard-of-oz` → `configWizardOfOz()`
   - default → `configLotr()` (unchanged)
   - Shared creator extracted to `classicsCreator()` helper.

### Commands to run on Laravel Cloud (in order)

#### 1. Seed The Masque of the Red Death

```bash
SEED_STORY=masque php artisan db:seed --class=AddSingleStorySeeder --force
```

This runs: chapter extraction → event extraction → system prompt → opening generation → publishes the story.

#### 2. Seed The Wonderful Wizard of Oz

```bash
SEED_STORY=wizard-of-oz php artisan db:seed --class=AddSingleStorySeeder --force
```

#### 3. Run V2 adaptation pipeline on all four stories

Each command dispatches `RunAdaptationPipelineJob` onto the `adaptation` queue. Run them one at a time (or together — they are queue-safe).

```bash
php artisan stories:run-adaptation the-adventure-of-the-speckled-band --force
php artisan stories:run-adaptation the-tell-tale-heart --force
php artisan stories:run-adaptation the-masque-of-the-red-death --force
php artisan stories:run-adaptation the-wonderful-wizard-of-oz --force
```

Monitor each with:
```bash
php artisan adaptation:export <slug>
```

#### 4. Verify each story is V2-ready

After all four pipelines complete, each story's `session_adaptations.runtime_narrator_prompt` must be non-null. The controller's `show()` endpoint uses `v2_ready` to unhide the story in the selector UI. The quickest spot-check:

```bash
# Should return the assembled narrator prompt (not null)
php artisan tinker --execute="
  \$story = \App\Models\Story::where('slug', 'the-masque-of-the-red-death')->first();
  echo \$story->sessionAdaptations()->whereNotNull('runtime_narrator_prompt')->count() . ' sessions ready';
"
```

Repeat for each slug. Once all sessions show a non-null prompt, the story is playable in Chaos Mode.

### Notes

- **Masque of the Red Death** is a single-chapter short story (~13k chars). The adaptation pipeline will produce 1 session. The assembled prompt will be well inside the 65 000-char cap.
- **Wizard of Oz** is a full-length novel (~180k chars). Expect the chapter-batch IP Trimming and Voice Lock jobs to spawn roughly 24 chapter workers. Runtime ~15–25 min end-to-end on the adaptation queue.
- Both new stories use the `The Classics, Unbound` creator (same as Alice, Sherlock, LOTR, etc.).