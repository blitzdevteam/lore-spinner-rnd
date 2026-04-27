# Curt Fix Series — Live Validation Runbook

**Companion to:** `curt-fix-process-log.md`
**Scope:** WS-0 / WS-C / WS-A / WS-B (commits `44938d1`, `4adb165`, `b66015d`, `a7272d2`)
**Environment:** the deployed Laravel container (your `pgsql` runs there). All commands below are written for **inside the app container**. Replace the `php` prefix with whatever your project uses (`sail artisan`, `docker compose exec app php`, etc.) — examples shown with bare `php artisan`.

---

## Conventions

- **Reference game id (Curt’s capture — historical, do not remove):** `01kpv313jddy575ct6bv6cak4j` — the `game_id` stored in `Adaptation layer/debug/curt-game-log.json`. Use it only when replaying or comparing against that exact export; it is **not** the default for current lab runs.
- **Active validation game id (runbook + runner default — not Curt’s):** `01kpe60znegetqss98x1kvxrb7` — use this in all **copy-paste commands below** and when appending debug results, so logs line up with the game you are actually playing now. Update this line in the runbook when you rotate to a fresh Alice game.
- **Runner game id resolution:** `curt-fix-validation-runner.php` accepts an optional second argument. If omitted, it uses env `CURT_FIX_VALIDATION_GAME_ID`, then falls back to the **active validation** id above (stderr prints which default was used and reminds you of Curt’s historical id).
- `<STORY_ID>` — UUID of the Alice story. `\App\Models\Story::where('title','like','%Alice%')->first()->id`.
- **Do not use `php artisan tinker <<'TINKER'` in one-line deployment shells** (Laravel Cloud, CI “run command”, some SSH wrappers). Bash needs real newlines before the closing `TINKER`; if the UI flattens the snippet, you get `syntax error near unexpected token '('` and `wanted TINKER`. For Steps 4, 5, 5b, and 9 use the bootstrap script instead (same PHP, one line, heredoc-free):

  `php "Adaptation layer/debug/curt-fix-validation-runner.php" step4`

  Or with an explicit id: `php "Adaptation layer/debug/curt-fix-validation-runner.php" step4 '01kpe60znegetqss98x1kvxrb7'`

- For each step, paste the output (or a redacted summary) under the `### Result` heading I left empty.

---

## Prep — environment sanity

```bash
php artisan migrate:status | grep -i world_state
```

**Expected:** the `2026_04_26_000001_add_world_state_to_games_table` row shows `Ran` (or `Pending` if you haven't migrated yet — run `php artisan migrate` first).

### Result

  2026_04_26_000001_add_world_state_to_games_table ................... [3] Ran  

---

```bash
php artisan migrate
```

**Expected if already migrated:** `Nothing to migrate.` Otherwise it should apply `2026_04_26_000001_add_world_state_to_games_table` cleanly.

### Result

`Nothing to migrate.`
---

## Step 1 — confirm `games.world_state` column exists

```bash
php artisan tinker --execute="echo \Schema::hasColumn('games', 'world_state') ? 'world_state column: PRESENT' : 'world_state column: MISSING';"
```

**Expected:** `world_state column: PRESENT`.

### Result

world_state column: PRESENT

---

## Step 2 — schema serializes for `NarrationAgent`

```bash
php artisan tinker --execute="
\$agent = new \App\Ai\Agents\NarrationAgent('test');
\$schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory();
\$shape = \$agent->schema(\$schema);
\$out = [];
foreach (\$shape as \$k => \$v) { \$out[\$k] = \$v->toArray(); }
\$json = json_encode(['properties' => \$out]);
echo 'top_keys=' . implode(',', array_keys(\$shape)) . PHP_EOL;
echo 'state_delta_keys=' . implode(',', array_keys(\$shape['state_delta']->toArray()['properties'] ?? [])) . PHP_EOL;
echo 'bytes=' . strlen(\$json) . PHP_EOL;
"
```

**Expected (exact):**
```
top_keys=response,choices,advance_event,input_classification,mapped_choice_id,mapped_option,state_delta
state_delta_keys=objects_acquired,objects_lost,objects_transformed,conditions_added,conditions_removed,location_changed,knowledge_gained,relationship_changes,tracked_path_update,flags_set
bytes=10000  (within ±2 KB)
```

### Result

top_keys=response,choices,advance_event,input_classification,mapped_choice_id,mapped_option,state_delta
state_delta_keys=objects_acquired,objects_lost,objects_transformed,conditions_added,conditions_removed,location_changed,knowledge_gained,relationship_changes,tracked_path_update,flags_set
bytes=5645
---

## Step 3 — run the in-process WS-B helper harness

This is the script that already passed 28/28 against my host. It needs no DB but does need to boot Laravel.

```bash
php "Adaptation layer/debug/wsb-validation.php"
```

**Expected:** ends with `failures: 0`. If anything regressed in your environment (e.g., the `JsonSchema` API moved between Laravel patch versions), this is where it'll surface.

### Result

=== WS-B validation: Alice 6-turn scenario ===

[1] matchAuthoredChoice
  ok   verbatim-ish input maps to option B
  ok   lowercased verbatim maps to option A
  ok   unrelated input does not match any option
  ok   empty input does not match
  ok   short paraphrase matches via token overlap

[2] applyStateDelta cumulative buildup (6 turns)
  turn 1 applied; objects=0 conditions=0 knowledge=1 location=long hallway with the small door
  turn 2 applied; objects=1 conditions=0 knowledge=1 location=long hallway with the small door
  turn 3 applied; objects=1 conditions=1 knowledge=2 location=long hallway with the small door
  turn 4 applied; objects=2 conditions=1 knowledge=2 location=long hallway with the small door
  turn 5 applied; objects=2 conditions=1 knowledge=3 location=doorway to a small garden
  turn 6 applied; objects=2 conditions=1 knowledge=3 location=doorway to a small garden
  ok   bottle object retained across turns
  ok   bottle qualifier transformed to drained
  ok   golden key retained
  ok   small condition was removed in turn 6
  ok   frustrated condition added in turn 6
  ok   last location_changed wins
  ok   cumulative knowledge T1 retained
  ok   cumulative knowledge T3 retained
  ok   cumulative knowledge T5 retained
  ok   flag from T1 retained
  ok   flag from T5 retained

[3] mergeTrackedDimensions accumulates path votes
  ok   curiosity_vs_caution dimension recorded
  ok   three curiosity votes accumulated across turns 2,3,5
  ok   all three votes are "curiosity"

[4] appendBranchingChoice idempotency
  ok   second call updates existing entry rather than duplicating
  ok   second call overwrote advanced=true
  ok   different event creates a new entry
  ok   empty option does not append

[5] appendBranchResolutionLog grows monotonically and trims
  ok   branch_resolution_log trims to 200 entries

[6] resolveCurrentBeatType walks the beat map on advance
  ok   starts at cold_open when current is null and advancing
  ok   advances cold_open → rising_curiosity
  ok   no advance → unchanged
  ok   past last beat clamps to last

=== summary ===
failures: 0

---

## Step 4 — render the system prompt against a real Alice game

This is the offline render: no LLM call, no DB writes — just exercises `renderSystemPrompt` with the *actual* current state of an existing game.

From the **project root** inside the app container (quotes matter because the path has a space):

```bash
php "Adaptation layer/debug/curt-fix-validation-runner.php" step4
```

Same with Curt’s reference id explicit (optional):

```bash
php "Adaptation layer/debug/curt-fix-validation-runner.php" step4 '01kpe60znegetqss98x1kvxrb7'
```

**Expected (fresh game, no turns yet):**
- `PERSISTENT WORLD STATE` may MISS (correct — empty state hides the block).
- `TURN STATE`, `AUTHORED-CHOICE ROUTING` (will MISS for a fresh turn — correct), `state_delta`, `objects_acquired` should all be `ok`.
- `SESSION COLD OPEN` should be `ok` if Alice S1 is COMPLETED in `session_adaptations`.

**Expected (mid-game, after a few turns with state_delta):**
- All probes `ok` except possibly `AUTHORED-CHOICE ROUTING` (only fires when the runtime matched).
- `world_state_object_count` should be ≥1 if the player picked anything up.
- **`MISS SESSION COLD OPEN`** usually means the rendered prompt is not on the session entry event (`isSessionStart` is false), or Session 1 adaptation is missing / not `COMPLETED` — not a runner bug.

### Result

Using reference game_id from curt-game-log.json (pass id or set CURT_FIX_VALIDATION_GAME_ID to override).
ok   PERSISTENT WORLD STATE
ok   TURN STATE
MISS AUTHORED-CHOICE ROUTING
ok   state_delta
ok   objects_acquired
ok   SESSION COLD OPEN
rendered_bytes=24016
session_resolved=1
world_state_object_count=0
---

## Step 5 — live cold-open turn (the real fix for Curt's #1)

**Order matters:** `step5` only resets the DB row and **deletes all prompts**. Nothing creates the first narration until **`step5b`** (CLI) or **`POST /user/games/{game}/begin`** (browser “Begin” — a GET to the game page alone does **not** insert the first prompt).

### 5a — reset (same as before)

```bash
php "Adaptation layer/debug/curt-fix-validation-runner.php" step5
```

**Writes to DB:** deletes all prompts for that game and resets `current_event_id`, session/branch/world_state fields to match `CreateGameAction::resolveStartEvent`. `reset to event=…` prints **`events.id`** (often `1` on a fresh SQLite seed — that is normal, not the game id).

### Result (5a)
Using reference game_id from curt-game-log.json (pass id or set CURT_FIX_VALIDATION_GAME_ID to override).
reset to event=1
note: events.id is numeric in many seeds; first narration is NOT created until step5b or POST user/games/{id}/begin.

### 5b — create first narration (fixes “null prompt” if you skip the UI)

Calls `GameController::begin()` (same as `POST` route `user.games.begin`). Requires working **NarrationAgent / LLM** config in that environment.

```bash
php "Adaptation layer/debug/curt-fix-validation-runner.php" step5b
```

**Expected:** `first_prompt_created=yes` and ~400 chars of HTML stripped narration. If prompts already exist, you get `skip_first_narration` and a dump of the existing first row.

### Result (5b)
Using reference game_id from curt-game-log.json (pass id or set CURT_FIX_VALIDATION_GAME_ID to override).
first_prompt_created=yes prompt_id=01kq7fcznc5qy21br6t0zbbfyr
first_response_first_400=
Alice was beginning to get very tired of sitting by her sister on the bank, and of having nothing to do: once or twice she had peeped into the book her sister was reading, but it had no pictures or conversations in it, “and what is the use of a book,” thought Alice “without pictures or conversations?”So she was considering in her own mind (as well as she could, for the hot day made her feel very s

### Optional — dump first prompt via tinker (null-safe)

Only use this **after 5b** or after you have actually triggered begin in the UI.

```bash
php artisan tinker --execute="
\$game = \App\Models\Game::find('01kpe60znegetqss98x1kvxrb7');
\$first = \$game?->prompts()->orderBy('created_at')->first();
if (\$first === null) {
    echo 'no_prompts_yet: run step5b or POST user/games/{id}/begin before this dump.' . PHP_EOL;
} else {
echo 'first_response_first_1000=' . PHP_EOL;
echo mb_substr(strip_tags((string) \$first->response), 0, 1000) . PHP_EOL;
}
"
```

**Expected:** the first 400 chars sound like Carroll-voiced cold-open prose (the `entry_point_diagnosis.cold_open` from `database/exports/adapptation-third-try.json`), NOT generic "the scene unfolds before you" or naked screenplay.

### Result (tinker dump)
Alice was beginning to get very tired of sitting by her sister on the bank, and of having nothing to do: once or twice she had peeped into the book her sister was reading, but it had no pictures or conversations in it, “and what is the use of a book,” thought Alice “without pictures or conversations?”So she was considering in her own mind (as well as she could, for the hot day made her feel very sleepy and stupid), whether the pleasure of making a daisy-chain would be worth the trouble of getting up and picking the daisies, when suddenly a White Rabbit with pink eyes ran close by her.There was nothing so _very_ remarkable in that; nor did Alice think it so _very_ much out of the way to hear the Rabbit say to itself, “Oh dear! Oh dear! I shall be late!” (when she thought it over afterwards, it occurred to her that she ought to have wondered at this, but at the time it all seemed quite natural); but when the Rabbit actually _took a watch out of its waistcoat-pocket_, and looked at it, an
...

---

## Step 6 — live 6-turn playthrough

**Prerequisite:** Step **5b** (or UI begin) has run so **at least one prompt exists**, then you submit **six** player turns via the normal game UI (`POST` to the prompt route). If you run the Step 6 dump immediately after **5a** only, you will see `world_state=null`, `branch_resolution_log_count=0`, etc. — that only means no turns were played yet, not that WS-B is broken.

This is the canonical Alice scenario from the WS-B harness, but real:

| Turn | Action to type into the game UI | Expected world_state shift |
|---|---|---|
| 1 | (cold open already rendered — read it) | `location` set to a long-hallway-ish place; maybe a knowledge fact + flag |
| 2 | "Pick up the bottle labeled DRINK ME" | `objects_acquired` adds the bottle |
| 3 | "Drink from the bottle" | `objects_transformed` (bottle → drained) + `conditions_added` ("small"…) + `knowledge_gained` |
| 4 | "Pick up the small golden key from the table" | `objects_acquired` adds the key, bottle still held |
| 5 | "Try the golden key on the smallest door" | matches authored option **A** for C1; runtime should set `input_classification=authored_choice`, `mapped_option=A`. `location_changed` to a garden/doorway, `flags_set` includes door-unlock |
| 6 | "Try to squeeze through the doorway" | `conditions_removed` may drop "small", `conditions_added` may add "stuck"/"frustrated" |

After all 6 turns, dump:

```bash
php artisan tinker --execute="
\$game = \App\Models\Game::find('01kpe60znegetqss98x1kvxrb7')->fresh();
echo 'world_state=' . PHP_EOL . json_encode(\$game->world_state, JSON_PRETTY_PRINT) . PHP_EOL;
echo 'tracked_dimensions=' . json_encode(\$game->tracked_dimensions) . PHP_EOL;
echo 'branching_choices_taken=' . json_encode(\$game->branching_choices_taken) . PHP_EOL;
echo 'current_beat_type=' . (string) \$game->current_beat_type . PHP_EOL;
echo 'branch_resolution_log_count=' . count(\$game->branch_resolution_log ?? []) . PHP_EOL;
"
```

**Expected:**
- `world_state.objects` has at least the bottle (drained) and the golden key.
- `world_state.conditions` reflects the latest condition (no longer "small" if turn 6 changed it).
- `world_state.knowledge` is a list ≥3 entries.
- `world_state.location` is the most recent location string.
- `tracked_dimensions` has at least one dimension key with a non-empty path array.
- `branching_choices_taken` has an entry with `option=A`, `choice_id=C1`, `classification=authored_choice` (turn 5).
- `current_beat_type` advanced one or more steps from null/cold_open.
- `branch_resolution_log_count` ≈ 6 (one per turn, capped at 200).

### Result


---

## Step 7 — `game:trace` over the same playthrough

**Prerequisite:** same as Step 6 — you need logged turns in `narration` log and prompt rows; right after a bare reset, trace will be empty or stale.

```bash
php artisan game:trace 01kpe60znegetqss98x1kvxrb7
```

**Expected:**
- One row per turn, in chronological order.
- Each row prints `event_id_before/after`, `session_number_before/after`, `turn_count`, `is_first_turn_in_event`, `advance_event_returned`, classification, mapped option, state-delta summary, world-state counts.
- All four hard rules show `ok` (`event_id_after >= event_id_before`, `advance_event=true → event changed`, `session_number_after` not cleared, choices differ from previous turn).
- Footer reports `total_violations: 0`.

### Result


---

## Step 8 — narration log file shape

```bash
tail -n 20 storage/logs/narration-$(date +%F).log
```

**Expected:** structured JSON-ish lines from `narration.turn`, each with `state_delta_summary`, `mapped_option`, `mapped_choice_id`, `deterministic_match`, `world_state_object_count`. If your env isn't UTC, swap the `date` for whatever date the file is named with.

### Result


---

## Step 9 — deterministic-match smoke test against live data

This proves WS-B B4 actually fires for the real session_choice_design rows the adaptation pipeline produced.

```bash
php "Adaptation layer/debug/curt-fix-validation-runner.php" step9
```

**Expected:**
- For Alice's S1 (assuming the choice texts in your DB are roughly the canonical Wonderland choices), the first two inputs match an option (A/B/C) with `choice_id` populated, and the third returns `(none)`.
- If your authored choice texts differ from the harness assumptions, you'll see `(none)` for the first two too — that's a content-mismatch issue, not a code bug. Paste the `choice_design_keys` and we can tune the matcher's threshold or map field names.

### Result


---

## Step 10 — sanity check WS-0 stayed fixed

The most important regression to keep verified.

```bash
php artisan tinker --execute="
\$game = \App\Models\Game::find('01kpe60znegetqss98x1kvxrb7');
\$sql = \$game->prompts()->latest()->limit(6)->toSql();
echo 'sql=' . \$sql . PHP_EOL;
echo 'order_by_count=' . substr_count(strtolower(\$sql), 'order by') . PHP_EOL;
"
```

**Expected:** exactly **one** `order by` clause (the `latest()`), not the stacked `order by created_at asc, created_at desc` we hit pre-WS-0. `order_by_count=1`.

### Result
sql=select * from "prompts" where "prompts"."game_id" = ? and "prompts"."game_id" is not null order by "created_at" desc limit 6
order_by_count=1

---

## Rollback reference

| Scope | Command |
|---|---|
| Entire fix series | `git reset --hard 6dd6fa9` (then `git push --force-with-lease origin main` if already pushed) |
| WS-B only | `git revert a7272d2` |
| WS-A only | `git revert b66015d` |
| WS-C only | `git revert 4adb165` |
| WS-0 only | `git revert 44938d1` |

For database rollback after reverting WS-B: `php artisan migrate:rollback --step=1` will undo the `world_state` column add. Existing games won't break since the column was always nullable.

---

## When you've run everything

Append the commit SHA of the runbook update plus a `Status: GREEN/RED` line at the very bottom, then I'll fold whatever needs follow-up into a WS-D batch.
