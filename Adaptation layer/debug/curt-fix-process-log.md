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

(Filled in immediately after `git push`.)

- **Commit SHA:** _(see below)_
- **Branch:** `main`
- **Push:** `origin main`
