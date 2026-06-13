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

## Known issues / iteration targets

- Merge job timeout remains 420s — monitor on first full-novel re-adapt after V2.2 deploy
- Anima Machina (1B screenplay QA) deferred until novelist baselines pass
- Existing stories require `php artisan stories:run-adaptation <slug> --force` on Cloud for full V2.2 enforcement
