# Narration Fix — Process Log

**Started:** 2026-05-02
**Prior work:** [`curt-fix-process-log-v2-2026-04-28.md`](curt-fix-process-log-v2-2026-04-28.md) (Curt Fix v2 → HEAD `16d8667`).
**Companion docs:**
- [`session1-narration-debug-guide-2026-05-02.md`](session1-narration-debug-guide-2026-05-02.md) — diagnostic that produced this plan.
- [`../../.cursor/plans/narration_fix_plan_5888ae5b.plan.md`](../../.cursor/plans/narration_fix_plan_5888ae5b.plan.md) — implementation plan.

**Scope:** Post-Curt-v2 20-turn Alice playthrough (`01kpe60znegetqss98x1kvxrb7`) surfaced 6 new defects. Infrastructure (logs, trace rules, state delta plumbing) was green; narration/adaptation consumption was broken. This batch fixes: scene rendering on event advance, off-script player acknowledgment, passive-path recording via continue, and exit-point drift (session close never firing) — with a Phase 7 pipeline change so session end becomes an authored integer the same way session start already is.

---

## Rollback anchor

**Previous stable HEAD before this work:** `16d8667`. This batch lives entirely after that commit.

```bash
# Full rollback of this batch (once committed):
git revert HEAD       # or the actual commit SHA once landed
```

---

## Why this batch existed

Live 20-turn playthrough trace showed:

- Events 5–17 all advanced, but the narrator kept writing the previous scene. DRINK ME, shrinking, EAT ME, growing, identity spiral — none rendered. Journal looked complete; player experience was a fall that never ended.
- Turn 10: player invented a water bottle. Narrator silently rewrote the action into "dusty glass," ignoring the specific claimed act.
- Turn 14: `matchAuthoredChoice` correctly served verbatim S1_C2 options. Player hit continue. `advance_returned: true` fired. `branching_choices_taken` remained null. The authored dimension was silently lost.
- Session 1 close prose + S1_C3 never fired anywhere in the 20-turn run. The adaptation `session_close_design` had no machine-usable trigger — only narrative/stickiness data. Runtime had no way to know when to inject it.

Root causes identified:

1. **`PromptController::store()`** wrote `event_id = $resolvedEventId` (the event advanced *to*) on the prompt row. Next turn counted `>= 1` prompt for that event → `isFirstTurnInEvent = false` → system prompt said "do not re-narrate screenplay" → model continued previous scene.
2. **`system-prompt.blade.php` turn-1 block** said "you MAY narrate the screenplay" — soft permission. With strong conversation-history momentum from the prior scene, the model ignored it.
3. **Off-script rule** (`system-prompt.blade.php`) told the model to "integrate + show reaction + steer back" — no discipline about honoring the *specific claimed action*. Model rewrote intent silently.
4. **`matchAuthoredChoice`** was never called on a continue turn (player input was `''`). Even when the previous turn had served authored choices verbatim, the continue became a free advance.
5. **`session_close_design` schema** (produced by Phase 7) contained `resolution_prose`, `hook_transition`, `session_end_choice`, `stickiness_audit` — no `session_close_trigger_event_id`. The runtime had nothing deterministic to key on. Same class of bug as entry-point drift, on the exit side.

---

## Batch — Narration Fix

**Goal:** Make scenes actually render on event advance, honor off-script acts as side quests, record passive-path dimensions on continue, and eliminate exit-point drift by making session close an authored integer.

### Files touched

| File | Change |
|---|---|
| [`app/Http/Controllers/User/Game/PromptController.php`](../../app/Http/Controllers/User/Game/PromptController.php) | Prompt row now records `event_id = $currentEvent->id` (the event narrated) not `$resolvedEventId` (the event advanced to). `store()` gains a continue-on-authored-branch fallback that matches the previous turn's served choices against `session_choice_design` and defaults continue to option C with the matched `choice_id`. `renderSystemPrompt()` adds exit-point detection using `session_close_design.session_close_trigger_event_id`; legacy `event_range` heuristic preserved as fallback for pre-pipeline-change adaptations. |
| [`resources/views/ai/agents/narration/system-prompt.blade.php`](../../resources/views/ai/agents/narration/system-prompt.blade.php) | Turn-1 block replaced with hard `=== NEW SCENE — OPEN IT NOW ===` directive telling the model to ignore prior-scene momentum. Off-script rule rewritten to honor the specific claimed action, allow 1-2 beat side-quest playthrough, then steer back organically. New `SESSION CLOSE` block injected when `$isSessionEnd && $sessionCloseDesign` — delivers resolution prose, hook, session-end choice A/B/C, final line. |
| [`app/Ai/Agents/Adaptation/SessionCloseAgent.php`](../../app/Ai/Agents/Adaptation/SessionCloseAgent.php) | Schema adds required `session_close_trigger_event_position` integer. Exit-point counterpart to `start_event_position` (Phase 3). |
| [`resources/views/ai/agents/adaptation/session-close/system-prompt.blade.php`](../../resources/views/ai/agents/adaptation/session-close/system-prompt.blade.php) | New Task 0 instructs the LLM to select a real event from the provided list, not describe one abstractly. STOP GATE updated to cover all four tasks. |
| [`resources/views/ai/agents/adaptation/session-close/prompt.blade.php`](../../resources/views/ai/agents/adaptation/session-close/prompt.blade.php) | Injects `SESSION EVENT LIST` with `story_position`, `title`, `objectives` — same format the entry-point diagnosis prompt uses. |
| [`app/Jobs/Adaptation/SessionCloseJob.php`](../../app/Jobs/Adaptation/SessionCloseJob.php) | Loads session events via the same `loadSessionEventsWithStoryPosition()` pattern as `EntryPointDiagnosisJob`. Resolves the LLM's chosen `session_close_trigger_event_position` back to a concrete `session_close_trigger_event_id` before persisting. Final `session_close_design` now contains both the integer ID and the narrative payload. |

### Architectural principle landed

Start and end of every session are now **authored integers**, produced by the pipeline and consumed by the runtime:

- Phase 3 / `EntryPointDiagnosisJob` → `entry_point_diagnosis.start_event_id`
- Phase 7 / `SessionCloseJob` → `session_close_design.session_close_trigger_event_id`

Runtime is a pure executor — it does not decide where sessions start or end.

### Defect → fix mapping

| Defect | Symptom | Fix |
|---|---|---|
| D1 | Events advanced, scenes never narrated (turns 6–17 all continuing the fall) | Prompt `event_id` = `$currentEvent->id`; turn-1 becomes hard scene-open directive |
| D2 | Turn 10 bottle: specific action silently replaced | Off-script rule rewritten — honor claimed action, side-quest 1-2 beats, steer back organically |
| D3 | S1_C2 served on turn 14, continue advanced clean, no dimension recorded | Continue-fallback matches last turn's choices; defaults to option C with matched `choice_id` |
| D4 | Session close + S1_C3 never fired | Explicit `session_close_trigger_event_id` written by Phase 7, consumed by `renderSystemPrompt()` |
| D5 | Exit-point drift (same class as entry-point drift) | `SessionCloseAgent` schema + system prompt + job mirror the entry-point pattern |

### Observability additions

- New log row: `narration.continue_authored_default` — fires when continue is redirected to option C of a matched authored set. Records `game_id`, `event_id`, `choice_id`, `defaulted_option`.

### Legacy data notes

Alice's current `session_close_design` (in `database/exports/adapptation-third-try.json`) has no `session_close_trigger_event_id`. Runtime detects this and falls back to the legacy `event_range - 4` heuristic so existing games don't break. New stories adapted after this batch will have the explicit field.

For Alice specifically, a manual DB patch setting `session_close_trigger_event_id: 12` (event "Alice questions identity while fanning", where S1_C3 is designed to fire) is the clean migration path without re-running the pipeline.

---

## Validation — to run

Per the plan's validation section:

1. **Fix 1:** Reset game. Play one S1_C1 input. Hit `__continue__`. Narrator must open event 2's scene ("Alice falls down the well"), not continue the hedgerow.
2. **Fix 3:** Type "I found a bottle and drank from it." Narrator must acknowledge the drinking act before grounding it in what exists.
3. **Fix 4:** Play through to event 11 where S1_C2 choices surface. Hit `__continue__`. Check `narration.turn` log row — must show `mapped_choice_id: S1_C2`, `mapped_option: C`, `deterministic_match: true`. A `narration.continue_authored_default` row should appear alongside.
4. **Fix 5A:** After re-running Phase 7 on any story, `session_close_design.session_close_trigger_event_id` must be a non-null integer pointing at a real session event.
5. **Fix 5B:** With `session_close_trigger_event_id: 12` set on Alice S1, play to event 12 turn 1. System prompt must contain the `SESSION CLOSE (EXIT POINT — FIRE NOW)` block; narrator must deliver resolution prose + session-end choice A/B/C.

---

## USER REVIEW CHECKPOINT — Narration Fix complete

- All 6 plan todos implemented.
- No linter errors across the 6 touched files.
- Commit pending user review + approval.

Append future batches below this checkpoint.
