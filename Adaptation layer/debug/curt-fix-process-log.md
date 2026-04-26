# Curt Feedback Fix — Process Log

**Started:** 2026-04-26
**Plan:** `.cursor/plans/curt_fix_execution_plan_29528c2d.plan.md`
**Companions:** `curt-feedback-diagnosis.md`, `curt-feedback-fix.md`, `curt-game-log-review.md`, `runtime-logic.md` (sibling to `debug/`)

---

## Rollback anchor

**Pre-fix-series HEAD:** `6dd6fa943a23cecb709e61367205c4cca69bd26a`

Single-line full rollback (reverts everything in this fix series, including all four batches once landed):

```bash
git reset --hard 6dd6fa943a23cecb709e61367205c4cca69bd26a
```

This SHA is the commit immediately preceding Batch 0. If a later batch needs surgical revert without unwinding the others, prefer `git revert <batch-sha>` against that batch's commit (each batch is one commit).

---

## Batch 0 — WS-0: `Game::prompts()` ordering collision

**Workstream:** WS-0 (P0).
**Goal:** Drop `->oldest()` from the `Game::prompts()` HasMany relation so chained `->latest()->first()` and `->latest()->limit(6)` no longer stack ORDER BY clauses and return the oldest row instead of the newest.
**Why P0:** Empirical evidence in `curt-game-log-review.md` shows player input was being overwritten onto turn-1's prompt every turn, and the narrator's "last 6 prompts" conversation history window never advanced past the first 6 prompts of the entire game. This single defect explains a large share of Curt's #1, #2, #3, #4 symptoms and corrupts the data every other defect is diagnosed against.

### Files touched

| File | Change |
|---|---|
| `app/Models/Game.php` | Drop `->oldest()` from the `prompts()` relation; add docblock explaining the trap. |
| `app/Http/Controllers/User/GameController.php` | `show()` eager-load converted from `'prompts:col1,col2'` shorthand to closure form so explicit `->oldest()` preserves the UI's chronological assumption. |

Companion documentation (untracked → tracked in this commit):

- `Adaptation layer/debug/curt-feedback-diagnosis.md` — full diagnosis of Curt's feedback (Defects A–D).
- `Adaptation layer/debug/curt-feedback-fix.md` — fix plan covering WS-0/A/B/C/D.
- `Adaptation layer/debug/curt-game-log-review.md` — empirical review of Curt's actual game log JSON; surfaced the WS-0 defect.
- `Adaptation layer/debug/curt-game-log.json` — raw game log captured during Curt's testing (referenced by the review).
- `Adaptation layer/debug/curt-fix-process-log.md` — this file.
- `Adaptation layer/runtime-logic.md` — runtime data-flow companion document (precedes this fix series).

### SQL verification (pre-commit)

Captured locally with a `php -r` script against the booted Laravel kernel:

```sql
-- show() eager-load (UI chronological)
order by "created_at" asc

-- latest()->first() (PromptController::store, CreatePromptAction)
order by "created_at" desc

-- latest()->limit(6) (buildConversationHistory)
-- window function with single ORDER BY
partition by "prompts"."game_id" order by "created_at" desc

-- where('event_id', …)->count()
-- (no ORDER BY needed)
```

No more stacked `ORDER BY created_at ASC, created_at DESC`. `latest()->first()` now returns the actual newest prompt.

### Caller audit

Every `Game::prompts()` callsite was audited; only three were ordering-dependent (and all are now correct without the relation-level `->oldest()`):

| Site | Pattern | Order-dependent? | Status post-fix |
|---|---|---|---|
| `GameController::show()` | eager-load for UI render | YES (oldest-first) | Explicit `->oldest()` in closure |
| `GameController::reset()` | `->delete()` | no | unchanged |
| `GameController::begin()` | `->exists()` / `->create()` | no | unchanged |
| `PromptController::store()` line 31 | `->latest()->first()?->update()` | YES (newest) | works correctly |
| `PromptController::store()` line 37–39 | `->where('event_id', …)->count()` | no | unchanged |
| `PromptController::store()` line 78 | `->prompts()->create()` | no | unchanged |
| `PromptController::buildConversationHistory()` | `->latest()->limit(6)->reverse()` | YES (newest 6, in chronological order) | works correctly |
| `WipeStoriesCommand` | `->delete()` | no | unchanged |
| `CreatePromptAction::handle()` | `->latest()->first()` | YES (newest) | works correctly |

`Event::prompts()` and `VoiceLabSession::prompts()` are unaffected — they live on different parents.

### Commit metadata

- **Commit SHA:** `44938d1`
- **Branch:** `main`
- **Push:** `origin main` (pushed cleanly, no force, no hooks skipped)
- **Stat:** 8 files changed, 2941 insertions, 2 deletions (diff dominated by the companion docs landing alongside the 2-line code fix; code-only diff is the 16-line model docblock add + the closure-form eager-load).
- **Surgical revert (this batch only):** `git revert 44938d1`

### USER REVIEW CHECKPOINT — Batch 0

After this commit lands, the next batch (WS-C — first-narration cold open) begins.

---

## Batch 1 — WS-C: cold-open lookup hardening + prompt rewrite

**Workstream:** WS-C (P1).
**Goal:** Make the SessionAdaptation lookup in `GameController::generateFirstNarration` resilient to `firstEvent->session_number === null` (the seed-time default until the adaptation pipeline runs), and rewrite the cold-open block in `system-prompt.blade.php` from "creative brief, generate your own" to "this IS your first response, preserve detail, do not substitute generic phrasing."

### Pre-flight (static, since Docker is currently down)

| Question | Answer | Source |
|---|---|---|
| Does the seeder set `session_number` on Alice's events? | NO. Default is null at seed-time. | `database/seeders/AddSingleStorySeeder.php` (no `session_number` writes); `database/migrations/2026_04_15_000003_add_session_number_to_events_table.php` line 14 (`->nullable()`) |
| Where does `session_number` get backfilled? | `StorySessionMapJob` (lines 88, 117, 123) — runs only after the adaptation pipeline kicks off. | `app/Jobs/Adaptation/StorySessionMapJob.php` |
| Does Alice's S1 cold open exist in the export? | YES. Carroll-voiced ("Heat shimmers off the river stones…"). | `database/exports/adapptation-third-try.json` line 396 |
| Failure mode this fix addresses | If a game starts before the adaptation pipeline writes back `events.session_number`, the original gate on `firstEvent->session_number !== null` skips the lookup entirely and the cold-open block never enters the prompt. The new fallback always probes for Session 1 of the story. | n/a |

Live DB pre-flight (which gate fails *for the production seed today*) deferred until Docker is back up; the static evidence already proves the fix is strictly more permissive — it cannot regress the cold-open block being available, only widen the cases where it is.

### Files touched

| File | Change |
|---|---|
| `app/Http/Controllers/User/GameController.php` | `generateFirstNarration()`: keep the original session-specific lookup, add a Session-1-of-story fallback, push the COMPLETED status check into the WHERE clause for both queries (was a post-fetch null-out). |
| `resources/views/ai/agents/narration/system-prompt.blade.php` | `--- SESSION COLD OPEN ---` block rewritten to "(PRIMARY SOURCE FOR YOUR FIRST RESPONSE)" with five hard rules: voice/rhythm match, verbatim detail preservation, HTML resegmentation allowed, end at first decision point, do not bring in EVENT.text. |

### Validation evidence

Offline render of the system-prompt Blade against a stubbed SessionAdaptation modeled on Alice's actual S1 export:

```
PRIMARY SOURCE marker:                  YES
Carroll cold-open prose present:        YES
White Rabbit anchor present:            YES
Hard rule 2 (preserve detail):          YES
Hard rule 5 (cold open IS source):      YES
Rendered length:                        10654 bytes
```

Block excerpt confirmed to render the new instructions verbatim with the Carroll prose embedded. No linter errors.

### Commit metadata

- **Commit SHA:** `4adb165`
- **Branch:** `main`
- **Push:** `origin main` (pushed cleanly)
- **Stat:** 3 files changed, 71 insertions, 12 deletions
- **Surgical revert (this batch only):** `git revert 4adb165`

### USER REVIEW CHECKPOINT — Batch 1

Cold-open block is now (a) reachable for any first event of a story whose Session 1 has been adapted, and (b) framed as the directed source of the first response, not a generic creative brief. Next batch: WS-A (observability — narration log channel + game:trace command + isFirstTurnInEvent block).

---

## Batch 2 — WS-A: observability + first-turn signal

**Workstream:** WS-A (P1).
**Goal:** Make every player turn observable (A1) and stop relying on the model to infer first-vs-subsequent turn (A2).

### Files touched

| File | Change |
|---|---|
| `config/logging.php` | New `narration` channel — daily-rotated file at `storage/logs/narration.log`, `LOG_NARRATION_LEVEL`/`LOG_NARRATION_DAYS` env hooks. |
| `app/Http/Controllers/User/Game/PromptController.php` | A1: `Log::channel('narration')->info('narration.turn', [...])` at the bottom of `store()`, after the prompts row is created and the game is refreshed (so `event_id_after` / `session_number_after` reflect the real post-turn state). A2: `'isFirstTurnInEvent' => $turnCount === 0` passed into the system-prompt view. |
| `app/Http/Controllers/User/GameController.php` | A2: `'isFirstTurnInEvent' => true` and `'turnCount' => 0` passed unconditionally for first-narration. |
| `resources/views/ai/agents/narration/system-prompt.blade.php` | A2: replaces the `@if(!empty($turnCount))` block (which silently failed on `turnCount === 0` because `empty(0)` is true) with an explicit two-state `=== TURN STATE ===` block. Pacing-pressure block now only fires under the `!isFirstTurnInEvent` branch. |
| `app/Console/Commands/GameTraceCommand.php` (new) | Pretty-prints the structured `narration.turn` entries for one game id, asserts four hard rules per row, summarizes total violations. DB-tolerant (warns and continues if DB unreachable). |

### Validation evidence

System-prompt assertions (offline render across turn states):

```
TURN 1 (turnCount=0, isFirstTurnInEvent=true):
  has TURN STATE marker:        YES
  has TURN 1 of this event:     YES
  no "already narrated":        YES   (correct — turn 1 may narrate the screenplay)
  no PACING block:              YES   (correct — no pacing pressure on turn 1)

TURN 3 (turnCount=2, isFirstTurnInEvent=false):
  has TURN STATE marker:        YES
  has TURN 3 of this event:     YES
  has "already narrated":       YES
  has PACING block:             YES

TURN 5 (turnCount=4, isFirstTurnInEvent=false):
  has TURN 5 of this event:     YES
  has FINAL turn pacing:        YES
```

Bug fixed in passing: the previous `@if(!empty($turnCount))` guard never fired on turn 1 (`empty(0) === true` in PHP). Turn 1 used to get **no** turn-state instruction at all — the model had to infer it. The new explicit signal closes that hole, which is a structural contributor to Curt's #2 (rehash drift).

`game:trace` command end-to-end test: synthetic log rows written to `storage/logs/narration-YYYY-MM-DD.log`, command parsed both rows, evaluated all 4 rules per row, reported `rows_shown: 2 / total_violations: 0`, and printed a per-turn report with prompt hash, choices, and rule status. Synthetic log file deleted after test.

DB resilience verified: with Docker down (live DB unreachable), the command warns "DB lookup failed... Continuing with log-only trace" and proceeds.

### Commit metadata

- **Commit SHA:** `b66015d`
- **Branch:** `main`
- **Push:** `origin main` (pushed cleanly)
- **Stat:** 6 files changed, 434 insertions, 8 deletions
- **Surgical revert (this batch only):** `git revert b66015d`

### USER REVIEW CHECKPOINT — Batch 2

Observability is in place. Every turn now produces a structured log row that is rule-checked by `game:trace`. The model now receives an explicit first-turn-vs-subsequent-turn signal instead of having to infer it. Next batch: WS-B (state persistence — `world_state` migration, NarrationAgent schema, persistence loop, system-prompt feedback).

---

## Batch 3 — WS-B: persistent world state + structured choice routing

**Workstream:** WS-B (P1).
**Goal:** Give the runtime a memory. The narrator now (a) emits a structured `state_delta` per turn, (b) the runtime applies it cumulatively to a new `games.world_state` JSON column, (c) the next system prompt feeds the world state back to the model as TRUTH OF RECORD, and (d) authored A/B/C branches are detected deterministically before the model even sees the input — closing Curt's "the bottle didn't shrink me", "no inventory", "branches feel cosmetic" symptoms.

### Files touched

| File | Change |
|---|---|
| `database/migrations/2026_04_26_000001_add_world_state_to_games_table.php` (new) | Adds nullable `world_state` JSON column to `games`, after `branch_resolution_log`. |
| `app/Models/Game.php` | `world_state` added to docblock + `$casts` (JSON). |
| `app/Http/Controllers/User/GameController.php` | `reset()` nullifies `world_state`. `generateFirstNarration` passes empty `worldState` and `null` `deterministicMatch` to view. |
| `app/Ai/Agents/NarrationAgent.php` | Schema expanded: adds required `input_classification`, `mapped_choice_id`, `mapped_option`, and a comprehensive `state_delta` object (objects acquired/lost/transformed, conditions added/removed, location, knowledge, relationships, tracked-path votes, write-once flags). All sub-fields required; uses constructor-form `object()` syntax to satisfy `Illuminate\JsonSchema\JsonSchemaTypeFactory`. |
| `app/Http/Controllers/User/Game/PromptController.php` | `store()` rewritten end-to-end: deterministic authored-choice detection (B4), world-state-aware system prompt (B3), structured-output capture, cumulative `applyStateDelta`, idempotent `appendBranchingChoice`, `mergeTrackedDimensions`, capped `appendBranchResolutionLog`, `resolveCurrentBeatType` walk. New helpers: `resolveSessionAdaptation`, `matchAuthoredChoice`, `normalizeForMatch`, `applyStateDelta`, `appendBranchingChoice`, `mergeTrackedDimensions`, `appendBranchResolutionLog`, `resolveCurrentBeatType`, `summarizeStateDelta`. Log row now includes classification, mapped option/choice, state-delta summary, and world-state object/condition counts. |
| `resources/views/ai/agents/narration/system-prompt.blade.php` | New `=== PERSISTENT WORLD STATE (TRUTH OF RECORD) ===` block listing held objects (with qualifier + contents), active conditions, known facts, relationships, raised flags, and current location. New `=== AUTHORED-CHOICE ROUTING (RUNTIME-DETECTED) ===` block forcing `input_classification=authored_choice`, `mapped_option`, `mapped_choice_id` when the runtime has matched. `OUTPUT REQUIREMENT` enumerates all seven structured fields and the ten `state_delta` sub-fields with discipline rules. |
| `resources/views/ai/agents/narration/prompt.blade.php` | Adds a `DETERMINISTIC AUTHORED-CHOICE MATCH` hint at the bottom of the user prompt when the runtime has matched, repeating the must-set fields. |
| `Adaptation layer/debug/wsb-validation.php` (new, validation harness) | DB-tolerant unit-style harness for the six new helpers. |

### B4 — deterministic authored-choice detection

`matchAuthoredChoice` normalizes the player input and each authored option's text (lowercase, strip punctuation, collapse whitespace), tokenizes both into ≥3-char tokens, and emits a match when the Jaccard-style overlap ratio is ≥0.6 OR the normalized input is a substring of an option (or vice versa, score floored at 0.85). When matched, the system prompt and user prompt both receive a "you MUST route to this option" hint, and the runtime records the option/choice_id deterministically — even if the model returned the wrong field, the runtime's match wins (`$deterministicMatch['option'] ?? $aiResult['mapped_option']`).

### B2 — persistence loop

After each turn:

1. `applyStateDelta(world_state, ai.state_delta)` — additive for objects/conditions/knowledge/relationships/flags, subtractive for `objects_lost` / `conditions_removed`, mutating for `objects_transformed`, last-write-wins for `location_changed`. `updated_at` always rewritten.
2. `appendBranchingChoice` — keyed by `(session_number, event_id)`; second call for the same key updates the row instead of appending, so a player's evolving turn within one event doesn't bloat the array.
3. `mergeTrackedDimensions` — accumulates path votes per dimension (e.g., `curiosity_vs_caution → ['curiosity', 'curiosity', 'caution']`). The session-end resolution job can read these to pick a path.
4. `appendBranchResolutionLog` — capped at last 200 entries.
5. `resolveCurrentBeatType` — walks the `session_architecture` beat list one step on advance, clamps at the last beat.

All five updates batched into a single `$game->update()`. Then the prompts row is created (so the post-turn refreshed state is what the next turn observes).

### B3 — system-prompt feedback

When `world_state` has any of `objects/conditions/knowledge/relationships/flags/location` populated, a `=== PERSISTENT WORLD STATE (TRUTH OF RECORD) ===` block is injected. It enumerates inventory with qualifiers ("bottle labeled DRINK ME (drained) — contains: …"), active conditions with notes ("small: shrunk to ten inches"), facts known, named-character dispositions, raised flags, and current sub-location. The block ends with a CRITICAL clause: held objects remain held unless this turn explicitly drops/uses/transforms them; conditions persist until removed; `state_delta` is the channel for any change.

### Validation evidence

`Adaptation layer/debug/wsb-validation.php` exercises a 6-turn Alice scenario in-process (no live DB needed). All 28 assertions green:

```
[1] matchAuthoredChoice                       (5/5)
[2] applyStateDelta cumulative buildup        (10/10)
[3] mergeTrackedDimensions accumulation       (3/3)
[4] appendBranchingChoice idempotency         (4/4)
[5] appendBranchResolutionLog cap (250→200)   (1/1)
[6] resolveCurrentBeatType beat-walk          (4/4)
failures: 0
```

The 6-turn scenario specifically covers Curt's failure modes:

- Turn 1: cold-open location set + first knowledge fact + flag raised.
- Turn 2: bottle picked up (object acquired with qualifier `full` and contents).
- Turn 3: bottle drunk → object transformed to `drained`, condition `small` added, knowledge "The bottle made you smaller." gained, `curiosity` path voted.
- Turn 4: golden key acquired alongside the (still-held) bottle.
- Turn 5: door unlocked — location shifts to "doorway to a small garden", new knowledge gained, new flag raised.
- Turn 6: condition `small` removed, condition `frustrated` added — both held objects still retained.

After turn 6, `world_state.objects` has both `bottle labeled DRINK ME (drained)` and `small golden key`, conditions has `frustrated`, knowledge has all three accumulated facts, flags has both raised flags, location is the latest. This is exactly the cumulative behavior Curt's testing showed missing.

Schema serialization smoke test (against `JsonSchemaTypeFactory`) — output ~10.6 KB, all seven top-level fields present, `state_delta` has all ten required sub-keys, `objects_acquired` items typed as objects.

System-prompt render smoke test with the post-T5 world state + a deterministic A match — every probe hit (PERSISTENT WORLD STATE block, OBJECTS HELD list naming both items, ACTIVE CONDITIONS, location string, AUTHORED-CHOICE ROUTING block, `mapped_option = "A"` directive, TURN STATE block, OUTPUT REQUIREMENT enumerating `state_delta` and `input_classification`).

DB resilience: this batch ships a migration but no live DB run was required to validate the runtime logic. The migration is reversible (`down()` drops the column) and additive — existing games default to `world_state = NULL`, which the helpers treat as `[]`.

### Commit metadata

- **Commit SHA:** `a7272d2`
- **Branch:** `main`
- **Push:** `origin main` (pushed cleanly, `b66015d..a7272d2`)
- **Stat:** 9 files changed, 1100 insertions, 23 deletions
- **Surgical revert (this batch only):** `git revert a7272d2`

### USER REVIEW CHECKPOINT — Batch 3

The full WS-0 → WS-C → WS-A → WS-B series is now landed:

- WS-0 — `Game::prompts()` ordering collision fixed.
- WS-C — cold-open block is reachable and framed as the directed source.
- WS-A — every turn is structured-logged + rule-checked, model gets explicit first-vs-subsequent turn signal.
- WS-B — narrator emits a structured world-state delta, runtime applies it cumulatively, next prompt feeds it back; authored A/B/C branches are detected deterministically.

Curt's defect lineup mapped to the landed fixes:

| Curt symptom | Fix |
|---|---|
| Player input ghost-overwriting prior turns | WS-0 |
| Generic-feeling cold open / loss of Carroll voice | WS-C |
| Rehash drift on later turns / event never advances | WS-A (turn-state signal + log-rule visibility) |
| "Bottle didn't shrink me" / "key disappeared" / no inventory | WS-B (state_delta + world_state) |
| Branches feel cosmetic / wrong A/B/C routing | WS-B (deterministic match) |

Ready for live playthrough validation once Docker is back up. The `game:trace` command will print a per-turn audit including state-delta summary, mapped option, and world-state object/condition counts.
