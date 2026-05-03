# Narration Fix — Validation Runbook

**Date:** 2026-05-02
**Batch:** Narration Fix (see `narration-fix-process-log-2026-05-02.md`)
**Runner:** `Adaptation layer/debug/curt-fix-validation-runner.php` (steps 12–14 added)
**Companion:** `Adaptation layer/debug/narration-fix-process-log-2026-05-02.md`

These steps validate the 5 core fixes shipped in the Narration Fix batch:

| Fix | What it targets |
|-----|----------------|
| Fix 1 | Prompt `event_id` records the narrated event, not the next one |
| Fix 2 | `isFirstTurnInEvent` fires a hard `NEW SCENE` directive, not a soft suggestion |
| Fix 3 | Off-script rule honors specific claimed actions + allows 1-2 beat side quest |
| Fix 4 | `__continue__` on an authored branching turn defaults to option C and records the dimension |
| Fix 5A/5B | `session_close_trigger_event_id` is now an explicit authored integer, consumed at runtime |

---

## Pre-flight

```bash
cd "/Users/daniel/Desktop/FLOW/DnD/PHP MVP/LoreSpinner RandD/lore-spinner-rand"
export CURT_FIX_VALIDATION_GAME_ID=<your-test-game-ulid>
```

---

## Step 1 — Schema probe (from Curt Fix v2 runbook, still valid)

Confirm `NarrationAgent` schema top-level keys haven't regressed:

```bash
php artisan tinker --execute="
\$agent = new \App\Ai\Agents\NarrationAgent('test');
\$schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory();
\$shape = \$agent->schema(\$schema);
echo 'top_keys=' . implode(',', array_keys(\$shape)) . PHP_EOL;
"
```

**Expected:** `top_keys=response,choices,advance_event,input_classification,mapped_choice_id,mapped_option,state_delta`

### Result

```
top_keys=
```

---

## Step 2 — System-prompt probe (Fix 1 + Fix 2 + Fix 3 + Fix 5B)

Renders the system prompt with `isFirstTurnInEvent=true` and checks that every new directive block is present:

```bash
php "Adaptation layer/debug/curt-fix-validation-runner.php" step12
```

**Expected:**
```
ok   NEW SCENE — OPEN IT NOW          [scene-open directive (Fix 1+2)]
ok   Honor the specific action         [off-script side-quest rule (Fix 3)]
ok   TURN STATE                        [turn state block present]
ok   ADAPTATION LAYER CONTEXT          [adaptation context injected]
ok   PRE-AUTHORED BRANCHING CHOICES    [branching choices block present]
miss SESSION CLOSE (EXIT POINT         [session close block — only present when current event = trigger]
```

The last line should be `miss` unless your test game's current event is exactly the trigger event. The first five must all be `ok`.

Additional output to confirm:
```
rendered_bytes=<non-zero>
session_close_trigger_event_id=(not set — legacy fallback active)  ← expected for Alice legacy data
would_fire_session_close=no
```

### Result

```
```

---

## Step 3 — DB: verify session_close_trigger_event_id (Fix 5A)

Checks whether the session adaptation row for the game's current story has the explicit trigger ID set. For Alice (pre-pipeline re-run), this will print a ready-to-paste patch command.

```bash
php "Adaptation layer/debug/curt-fix-validation-runner.php" step13
```

**Expected (new adaptation, post pipeline re-run):**
```
session_number=1
session_close_design_present=yes
session_close_trigger_event_id=<integer>
trigger_event_title=<event title>
status=ok
```

**Expected (legacy Alice adaptation — no re-run yet):**
```
session_close_trigger_event_id=MISSING — needs DB patch or Phase 7 re-run
Patch command (Alice session 1, story <id>):
php artisan tinker --execute="..."
```

In the legacy case, paste and run the printed patch command, then re-run step 3 to confirm `status=ok`.

### Result

```
```

---

## Step 4 — Play session: scene transitions (Fix 1 + Fix 2)

Manual play validation. Confirm the narrator actually opens a new scene when `event_id` advances.

1. Reset the game: `php "Adaptation layer/debug/curt-fix-validation-runner.php" step5`
2. Start the game: `php "Adaptation layer/debug/curt-fix-validation-runner.php" step5b`
3. Play: enter `I follow the rabbit` (or any S1_C1 input).
4. Play: enter `__continue__`.

**Expected:** narrator's Turn 4 response opens Alice's descent into the well ("falling, falling…"). It should NOT continue the hedgerow scene from Turn 2.

**Check log:**
```bash
tail -f storage/logs/narration-$(date +%Y-%m-%d).log | grep -E '"event_id"|"is_first_turn_in_event"'
```

The `event_id` in the prompt row for Turn 3 should increment. `is_first_turn_in_event: true` should appear on the same row.

### Result

```
```

---

## Step 5 — Play session: off-script side quest (Fix 3)

Test that the narrator acknowledges a specific invented action rather than silently erasing it.

After Step 4 (while still in the falling scene), enter: `I found a bottle in my pocket and drank from it.`

**Expected:** narrator acknowledges the drinking act (even if briefly), does NOT silently replace it with something that already exists in the scene, and by Turn 2 of the side quest gently steers back to the falling or the objects at the bottom.

### Result

```
```

---

## Step 6 — Play session: `__continue__` on authored branch (Fix 4)

Test that `__continue__` on a branching choice turn records Option C.

1. Play forward until the narrator presents S1_C2 choices verbatim (Turn ~14 in the Alice trace — gloves/fan decision). If the game doesn't reach it organically, set `current_event_id` to event 11 manually.
2. Enter `__continue__`.

**Expected outcome (log):**
```bash
php "Adaptation layer/debug/curt-fix-validation-runner.php" step14
```

```
continue_authored_default_hits=1
last_hit_first_300=...mapped_choice_id: S1_C2...defaulted_option: C...
status=ok — continue defaulted to authored branch option C
```

**Expected outcome (DB):** `branching_choices_taken` on the game row now contains `S1_C2` with option `C`.

### Result

```
```

---

## Step 7 — Play session: session close fires (Fix 5B)

Test that the SESSION CLOSE block injects when the game reaches the trigger event.

1. Apply the DB patch from Step 3 so `session_close_trigger_event_id` is set (e.g., event 12 for Alice).
2. Set the game's `current_event_id` to the trigger event:
   ```bash
   php artisan tinker --execute="
   \$g = App\Models\Game::find('<your-game-ulid>');
   \$e = App\Models\Event::find(12);
   \$g->update(['current_event_id' => \$e->id]);
   echo 'moved to: ' . \$e->title;
   "
   ```
3. Re-run the system-prompt probe:
   ```bash
   php "Adaptation layer/debug/curt-fix-validation-runner.php" step12
   ```

**Expected:**
```
ok   SESSION CLOSE (EXIT POINT        [session close block (Fix 5)]
would_fire_session_close=YES
```

4. Play one turn (any input). Narrator must deliver resolution prose + session-end choice A/B/C. It must NOT open a new mid-session scene.

### Result

```
```

---

## Step 8 — Pipeline probe: Phase 7 schema (Fix 5A)

After re-running the adaptation pipeline on any story, verify `session_close_trigger_event_id` is present:

```bash
php artisan tinker --execute="
\$sa = App\Models\SessionAdaptation::latest()->first();
\$d = \$sa->session_close_design;
echo 'trigger_event_id=' . (\$d['session_close_trigger_event_id'] ?? 'MISSING') . PHP_EOL;
echo 'trigger_event_position=' . (\$d['session_close_trigger_event_position'] ?? 'MISSING') . PHP_EOL;
"
```

**Expected:**
```
trigger_event_id=<integer>
trigger_event_position=<integer>
```

### Result

```
```

---

## Pass / Fail summary

| Step | Probe | Expected | Status |
|------|-------|----------|--------|
| 1 | Schema top_keys | response,choices,advance_event,... | |
| 2 | System-prompt probe (step12) | 5 × ok; session close miss | |
| 3 | session_close_trigger_event_id in DB (step13) | ok or patched | |
| 4 | Scene transition on event advance | New scene opens | |
| 5 | Off-script side quest | Act acknowledged before grounding | |
| 6 | `__continue__` on authored branch (step14) | option C recorded, log hit = 1 | |
| 7 | Session close fires at trigger event | SESSION CLOSE block injects | |
| 8 | Phase 7 schema after pipeline re-run | trigger_event_id = integer | |

All 8 steps must pass before considering the Narration Fix batch validated.

---

## Notes

- **Legacy Alice data** (pre-pipeline): Steps 3 and 8 require the manual DB patch or a Phase 7 re-run. Steps 1–2 and 4–7 can be validated independently once the patch is applied.
- **Rollback anchor:** `16d8667` — revert to this commit if any step shows a regression not present before this batch.
