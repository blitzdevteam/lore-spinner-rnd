# Pipeline Upgrade V2 — Validation Runbook

**Process log:** `Adaptation layer/Chaos adaptation/v2-implementation/process-log/v2-process-log.md`
**Runner script:** `Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php`
**Style:** mirrors `narration-fix-validation-runbook-2026-05-02.md`.

This runbook describes how to validate the V2 pipeline upgrade end to end on Laravel Cloud. Cursor cannot run these probes — Daniel runs them manually after the implementation batch lands.

## Pre-flight

| Check | Command | Expected |
|---|---|---|
| Workdir | `pwd` | repo root |
| Branch | `git status -sb` | working on `main` |
| Schema | `php artisan migrate:status` | three new V2 migrations present and not yet run |
| Queue | `php artisan queue:work --once adaptation` | runs without error |
| Logs | `tail -f storage/logs/narration-*.log` | open in a second pane |

## Rollback anchor

**Pre-V2 HEAD SHA:** `89c6e2d` — `feat(writer-lab): full extraction visibility, cold-open/start-event separation, light mode, font boost` (2026-05-24, Danielnrahimi).

This is the commit on `main` immediately before the V2 batch was applied. Every change introduced by the V2 implementation lives in the commit(s) **after** `89c6e2d`. To verify locally:

```
git log --oneline 89c6e2d..HEAD
```

To roll back, revert every V2 commit back to (but not including) `89c6e2d`, then run the migration rollback below:

```
git revert --no-edit 89c6e2d..HEAD
```

```
php artisan migrate:rollback --path=database/migrations/2026_05_24_000003_add_v2_state_columns_to_chaos_sessions.php
php artisan migrate:rollback --path=database/migrations/2026_05_24_000002_add_runtime_narrator_prompt_to_session_adaptations.php
php artisan migrate:rollback --path=database/migrations/2026_05_24_000001_add_v2_pipeline_columns_to_story_adaptations.php
```

The legacy Chaos partials live on disk under `resources/views/ai/agents/chaos/partials/` — they remain untouched as reference but no code reads them in V2. After rollback you'll need to restore the pre-V2 `ChaosStoryConfig` (it carried a `voice_partial` key per story) from git history.

## Validation runner

Every step has a `php artisan tinker`-friendly probe in `pipeline-upgrade-v2-validation-runner.php`. Invoke as:

```
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" stepN <story_slug>
```

`<story_slug>` defaults to `alice-in-wonderland`. Pick a story that has been adapted under V2 for steps 6+.

## Step list

### Step 1 — Migrations + enum

```
php artisan migrate
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step1
```

Expected:

```
ok   2026_05_24_000001_add_v2_pipeline_columns_to_story_adaptations
ok   2026_05_24_000002_add_runtime_narrator_prompt_to_session_adaptations
ok   2026_05_24_000003_add_v2_state_columns_to_chaos_sessions

AdaptationStatusEnum cases:
  ...
  ip-trimming
  voice-lock
  ...

ip-trimming case present: yes
voice-lock case present:  yes
```

### Step 2 — Model casts

```
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step2
```

Expected:

- `StoryAdaptation` casts `ip_trimming` and `voice_profile` as `json`.
- `SessionAdaptation` casts `runtime_narrator_assembled_at` as `datetime`.
- `ChaosSession` casts `world_state` (json — UPGRADED IN PLACE), `alignment_scaffold` (json), and `is_climactic_choice` (boolean).
- There must be NO `world_state_v2` cast. The legacy `world_state` JSON column now holds the new literary-memory shape directly (Daniel's 2026-05-24 in-place correction).

### Step 3 — Blade render probe

```
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step3
```

Expected: every blade renders to a non-zero byte count. No `FAIL` lines.

### Step 4 — Runtime narrator template render

```
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step4
```

Expected: all four injection-point tokens (`[SYMBOLIC_MEMORY_INJECTION_POINT]`, `[ALIGNMENT_TILT_INJECTION_POINT]`, `[OPENING_SCENE_INJECTION_POINT]`, `[WORLD_STATE_TIERED_INJECTION_POINT]`) present in rendered output.

### Step 5 — Alignment translator leak scan

```
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step5
```

Expected: zero hits for the literal strings `chaotic`, `lawful`, `neutral` in the runtime-injected text. The story-native label (e.g. `Curious`) must be present.

### Step 6 — Persisted adaptation outputs

Re-run the pipeline for a chaos-enabled story:

```
php artisan stories:run-adaptation alice-in-wonderland --force
# wait for queue:work to drain the adaptation queue (5-15 min on Laravel Cloud)
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step6 alice-in-wonderland
```

Expected: every key (`ip_trimming`, `format_detection`, `ip_audit`, `voice_profile`, `story_session_map`) is `ok`. The `story_session_map` payload contains `persistent_state_schema`, `world_reactivity_rules`, `story_guard_canon`, `alignment_labels`.

### Step 7 — Per-session outputs

```
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step7 alice-in-wonderland
```

Expected: every session has `session_architecture`, `session_choice_design`, `choice_consequence_map`, `session_close_design`, `editorial_verification`, AND a non-empty `runtime_narrator_prompt` plus a `runtime_narrator_assembled_at` timestamp.

### Step 8 — Runtime template size budget

```
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step8 alice-in-wonderland
```

Expected: every session reports a byte count ≤ `RuntimeNarratorTemplateBuilder::MAX_PROMPT_CHARS` (65,000). Any `FAIL (over cap)` indicates the editor must split that session.

### Step 9 — Hard-ban scan against the assembled prompt

```
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step9 alice-in-wonderland
```

Expected: every session reports `ok (no ban tokens leaked)`. The universal-ban scan deliberately excludes Section 7 of the template (the ban list itself).

### Step 10 — Chaos Mode start (live)

In the browser or via curl: `POST /chaos-mode/start` with `story_slug=alice-in-wonderland`. Expected:

- HTTP 200 with a narrator response.
- `chaos.start` log line in `storage/logs/narration-*.log`.
- New `chaos_sessions` row with `world_state` populated in the upgraded literary shape (object_states / relationship_updates / emotional_ledger rows present) and `alignment_scaffold` populated with `{chaotic, lawful, neutral}` ints.

### Step 11 — Chaos Mode turn (live)

Continue the session from step 10. Expected:

- `chaos.turn` log line per turn.
- `world_state` updates merged correctly; `emotional_ledger` accumulates.
- `alignment_scaffold` increments monotonically.
- On a Choice #3 / Choice #4 turn, `is_climactic_choice` flips to true and `defining_choice_id` / `defining_choice_line` are populated.

### Step 12 — Tiered state loader

After a climactic turn (Choice #3 or #4), the NEXT turn's system prompt should include the `PERSISTENT STATE — TIER 3` block. Inspect via:

```
grep -A 20 "PERSISTENT STATE — TIER 3" storage/logs/narration-*.log
```

Expected: the block appears on the turn after a climactic choice, and is absent on regular turns.

### Step 13 — Un-adapted story 422 probe

Per Daniel's correction, stories whose pipeline has not been re-run under V2 must NOT fall back to the legacy partials. Pick a chaos-enabled story whose `runtime_narrator_prompt` is still NULL and call `POST /chaos-mode/start`:

```
curl -i -X POST https://<host>/chaos-mode/start -H 'X-Inertia: true' \
  -d 'story_slug=the-tell-tale-heart'
```

Expected: `HTTP/1.1 422` with body containing `re-adapted under V2`.

```
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step13 the-tell-tale-heart
```

### Step 14 — Reconciliation

```
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step14 alice-in-wonderland
```

Expected:

- `story adaptation_status: completed` once every session has `runtime_narrator_prompt` set.
- `sessions missing runtime_narrator_prompt: none`.
- If any session is missing the assembled prompt, the status reads `partial-completion` and the editor can re-dispatch `RuntimeNarratorAssemblyJob` manually.

## Pass / fail table

| Step | Description | Status |
|---|---|---|
| 1 | Migrations + enum |   |
| 2 | Model casts (`world_state` cast present; no `world_state_v2` sidecar) |   |
| 3 | Blades render |   |
| 4 | Runtime template render |   |
| 5 | Alignment leak scan |   |
| 6 | Per-story pipeline outputs |   |
| 7 | Per-session pipeline outputs |   |
| 8 | Runtime template size budget |   |
| 9 | Hard-ban scan on assembled prompt |   |
| 10 | Chaos Mode start (live) |   |
| 11 | Chaos Mode turn (live) |   |
| 12 | Tiered state loader |   |
| 13 | Un-adapted story 422 |   |
| 14 | Reconciliation |   |

A green run is steps 1-14 all `ok`. Steps 1-5 and 8/9/12-14 are fully automatable. Steps 6, 7, 10, 11 require a queue worker and a real session.

## Known limitations

- **No legacy fallback.** Old chaos sessions for stories that have not been re-adapted under V2 are unplayable; re-run `php artisan stories:run-adaptation <slug> --force` per story. This is deliberate (Daniel's 2026-05-24 correction).
- **Hard quotas vs. caps.** The Phase 4 / Phase 5 quotas (4 / 4-6 / 6-10) come straight from the canonical Deliverable text. The product target is "caps not quotas"; treat the runbook quotas as gates for *this* batch and revisit in a follow-up iteration.
- **Compression cascade.** Stories with very long sessions may hit `RuntimeNarratorTemplateBuilder`'s 65k cap. The builder will throw and the assembly job will log a `runtime_narrator_assembly.compression_failed` event. Daniel splits the offending session manually and re-runs.
