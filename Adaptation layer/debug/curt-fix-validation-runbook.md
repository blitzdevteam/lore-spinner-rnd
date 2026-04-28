# Curt Fix Series — Live Validation Runbook

**Companion to:** `curt-fix-process-log.md`
**Scope:** WS-0 / WS-C / WS-A / WS-B (commits `44938d1`, `4adb165`, `b66015d`, `a7272d2`) + **Curt Fix v2 — No Fallbacks** (schema strict-mode fix, kill silent stubs, B4 matcher rewrite, GameTraceCommand type fix, LLM observability)
**Environment:** the deployed Laravel container (your `pgsql` runs there). All commands below are written for **inside the app container**. Replace the `php` prefix with whatever your project uses (`sail artisan`, `docker compose exec app php`, etc.) — examples shown with bare `php artisan`.

> **Validation PASS 2 (post-Curt-Fix-v2):** the prior `### Result` blocks below are historical evidence from the failed PASS 1 run (when the silent stub was masking the OpenAI strict-mode rejection). Re-run the steps in the new validation order — `step5` reset → **`step11` first** → `step5b` → `step9` → 1 UI turn → `game:trace` → 5 more turns — and overwrite the result blocks. PASS 2 is GREEN when every probe lines up with its expected output below.

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
Using DEFAULT_VALIDATION_GAME_ID=01kpe60znegetqss98x1kvxrb7 (Curt historical game_id in curt-game-log.json: 01kpv313jddy575ct6bv6cak4j). Pass CLI id or set CURT_FIX_VALIDATION_GAME_ID to override.
first_prompt_created=yes prompt_id=01kq7gpd2xxp18ynd1gyvmg0y0
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
Using DEFAULT_VALIDATION_GAME_ID=01kpe60znegetqss98x1kvxrb7 (Curt historical game_id in curt-game-log.json: 01kpv313jddy575ct6bv6cak4j). Pass CLI id or set CURT_FIX_VALIDATION_GAME_ID to override.
first_prompt_created=yes prompt_id=01kq8rxsps5kdwpwqjbmmgsr60
first_response_first_400=
Heat shimmers off the river stones, and your stockings stick to the back of your knees as you lean over the grass, half-listening to your sister’s page-turning.Then a White Rabbit flashes past so close you catch the clean, sharp scent of crushed clover—and it mutters, plainly, like a person: “Oh dear! Oh dear! I shall be late!”You don’t freeze. You lunge up, skirt snagging on a thistle, because th
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

world_state=
{
    "objects": [],
    "conditions": [],
    "knowledge": [],
    "relationships": [],
    "flags": [],
    "location": null,
    "updated_at": "2026-04-27T13:08:03+00:00"
}
tracked_dimensions=[]
branching_choices_taken=[]
current_beat_type=ESCALATION
branch_resolution_log_count=2
---

## Step 7 — `game:trace` over the same playthrough

**Prerequisite:** same as Step 6 — you need logged turns in `narration` log and prompt rows; right after a bare reset, trace will be empty or stale.

```bash
php artisan game:trace 01kpe60znegetqss98x1kvxrb7
```

**Expected:**
- One row per turn, in chronological order.
- Each row prints `event_id_before/after`, `session_number_before/after`, `turn_count`, `is_first_turn_in_event`, `advance_event_returned`, classification, mapped option, state-delta summary, world-state counts.
- All four hard rules show `ok` (`event_id_after >= event_id_before`, `advance_event=true → event changed`, `session_number_after` does NOT regress below `session_number_before` when both are non-null — a null `session_number_after` is legitimate for events the adaptation pipeline has not yet backfilled, choices differ from previous turn).
- `session_number_before` / `session_number_after` are sourced from `events.session_number` (the authoritative source). If `games.current_session_number` drifts from the current event, the trace prints a `(drift: games.current_session_number=…)` note next to the session line — informational, not a violation.
- Footer reports `total_violations: 0`.

### Result

=== GAME ===
id:                    01kpe60znegetqss98x1kvxrb7
story_id:              1
current_event_id:      3
current_session_number: null

=== TURN TRACE (2 rows) ===

--- TURN 1 (logged 2026-04-27T13:07:50+00:00) ---
  event_id:            1 -> 2
  session_number:      1 -> null
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         false
  prompt_hash:         a5793c75c69c
  player_input:        "Pick up the bottle labeled DRINK ME"
  narrator_response:   "The scene unfolds before you..."
  choices_returned:
    A) Continue forward
    B) Investigate your surroundings
    C) Take a moment to reflect

In GameTraceCommand.php line 285:
                                                                               
  App\Console\Commands\GameTraceCommand::assertHardRules(): Argument #1 ($eve  
  ntBefore) must be of type ?string, int given, called in /var/www/html/app/C  
  onsole/Commands/GameTraceCommand.php on line 119       
---

## Step 8 — narration log file shape

```bash
tail -n 40 storage/logs/narration-$(date +%F).log
```

**Expected (Curt Fix v2):** four log row types now appear, all on the `narration` channel:

| Row | Emitted from | What it proves |
|---|---|---|
| `narration.cold_open_audit` | `GameController::generateFirstNarration` (every begin) | Whether `entry_point_diagnosis.cold_open` was non-empty when the system prompt was rendered. `cold_open_present=true` + a recognizable `cold_open_first_120` ("Heat shimmers off the river stones…") means WS-C content is reaching the model. |
| `narration.llm_success` | both `GameController::generateFirstNarration` and `PromptController::generateNarration` | The OpenAI call returned. `state_delta_keys_present` should list all 10 sub-keys (objects_acquired, objects_lost, objects_transformed, conditions_added, conditions_removed, location_changed, knowledge_gained, relationship_changes, tracked_path_update, flags_set) on a successful WS-B turn. `input_classification` must be in the schema enum (one of `expressive/branch_aligned/emergent/unsupported/opening`, plus `authored_choice` from the runbook prompt) — **never** the legacy `freeform`. |
| `narration.llm_failed` | both call sites (only on exception) | The pre-fix silent catch is gone. Any LLM failure now logs `exception` class + `message` + `system_prompt_bytes` + `history_turns`. If you see this in PASS 2, the schema fix is incomplete or there's an unrelated upstream error — read the `message` and act. |
| `narration.turn` | `PromptController::store` (post-success only — failure path no longer logs this row) | Existing per-turn audit row: `state_delta_summary` counts, `world_state_object_count`, `mapped_option`, `mapped_choice_id`, `deterministic_match`, `system_prompt_hash`. |

If your env isn't UTC, swap `$(date +%F)` for whatever date the file is named with.

### Result
[2026-04-27 13:07:50] production.INFO: narration.turn {"game_id":"01kpe60znegetqss98x1kvxrb7","event_id_before":1,"event_id_after":2,"session_number_before":1,"session_number_after":null,"turn_count":1,"is_first_turn_in_event":false,"advance_event_returned":true,"force_advanced":false,"is_continue":false,"input_classification":"freeform","mapped_choice_id":null,"mapped_option":null,"deterministic_match":false,"state_delta_summary":{"objects_acquired":0,"objects_lost":0,"objects_transformed":0,"conditions_added":0,"conditions_removed":0,"location_changed":"","knowledge_gained":0,"relationship_changes":0,"tracked_path_update":0,"flags_set":0},"world_state_object_count":0,"world_state_condition_count":0,"player_input_first_120":"Pick up the bottle labeled DRINK ME","narrator_response_first_120":"The scene unfolds before you...","choices_returned":["Continue forward","Investigate your surroundings","Take a moment to reflect"],"system_prompt_hash":"a5793c75c69cd47b1f94a96c56a41cb7cd85d45087d51be1d2090b1c3c08894d","logged_at":"2026-04-27T13:07:50+00:00"} 
[2026-04-27 13:08:03] production.INFO: narration.turn {"game_id":"01kpe60znegetqss98x1kvxrb7","event_id_before":2,"event_id_after":3,"session_number_before":1,"session_number_after":null,"turn_count":1,"is_first_turn_in_event":false,"advance_event_returned":true,"force_advanced":false,"is_continue":false,"input_classification":"freeform","mapped_choice_id":null,"mapped_option":null,"deterministic_match":false,"state_delta_summary":{"objects_acquired":0,"objects_lost":0,"objects_transformed":0,"conditions_added":0,"conditions_removed":0,"location_changed":"","knowledge_gained":0,"relationship_changes":0,"tracked_path_update":0,"flags_set":0},"world_state_object_count":0,"world_state_condition_count":0,"player_input_first_120":"Drink from the bottle","narrator_response_first_120":"The scene unfolds before you...","choices_returned":["Continue forward","Investigate your surroundings","Take a moment to reflect"],"system_prompt_hash":"7f066d10404df2f5606a1b2113695c6e877333e00c8817e68d6c93c46d2d8318","logged_at":"2026-04-27T13:08:03+00:00"} 

---

## Step 9 — deterministic-match smoke test against live data

This proves WS-B B4 actually fires for the real session_choice_design rows the adaptation pipeline produced. Curt Fix v2 rewrote the matcher around the real nested shape (`branching_choice_*.option_a.text` etc.) plus the `expressive_choices[]` array. Inputs are now near-verbatim copies of the canonical S1_C1 Alice option text so the Jaccard scorer has real overlap to grip.

```bash
php "Adaptation layer/debug/curt-fix-validation-runner.php" step9
```

**Expected (post-Curt-Fix-v2):**
- `extracted_candidates=` should be ≥ 12 for Alice S1 (3 branching slots × 3 options + N expressive items × 3 options). On the third-try export it lands at 18.
- Input 1 (`Sprint after him and dive for the rabbit-hole`) matches `option=A choice_id=S1_C1` (substring shortcut → score ≥ 0.85).
- Input 2 (`Keep him in sight but slow just long enough to clock landmarks`) matches `option=B choice_id=S1_C1` (substring shortcut).
- Input 3 (`Shout into the void`) returns `(none)`.

If candidate count is 0 or matches all return `(none)`, the `session_choice_design` row is empty in this DB — that's a content-pipeline issue, not a runtime bug.

### Result

Using DEFAULT_VALIDATION_GAME_ID=01kpe60znegetqss98x1kvxrb7 (Curt historical game_id in curt-game-log.json: 01kpv313jddy575ct6bv6cak4j). Pass CLI id or set CURT_FIX_VALIDATION_GAME_ID to override.
session=1
choice_design_keys=branching_choice_1,expressive_choices,branching_choice_2,branching_choice_3
extracted_candidates=18
  [0] option=A choice_id=S1_C1 text="You sprint after him and dive for the rabbit-hole the instant you see it."
  [1] option=B choice_id=S1_C1 text="You keep him in sight but slow just long enough to clock landmarks and the shape"
  [2] option=C choice_id=S1_C1 text="You call out to him first and watch how he reacts before you commit to the hole."

INPUT: Sprint after him and dive for the rabbit-hole
MATCH: {"option":"A","choice_id":"S1_C1","text":"You sprint after him and dive for the rabbit-hole the instant you see it."}

INPUT: Keep him in sight but slow just long enough to clock landmarks
MATCH: {"option":"B","choice_id":"S1_C1","text":"You keep him in sight but slow just long enough to clock landmarks and the shape of the hedge."}

INPUT: Shout into the void
MATCH: (none)
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

## Step 11 — direct NarrationAgent probe (Curt Fix v2)

This is the **first thing to run after the schema-strict fix lands** — it bypasses both controllers and calls `NarrationAgent::make($systemPrompt)->prompt(...)` against a tiny throwaway system prompt. If OpenAI is going to reject our schema for being non-strict-compliant (or any other reason), this surfaces the raw exception class + message + first frame instead of letting it disappear into a stub. Step 5b and the controller path can then be trusted to actually exercise the full pipe.

```bash
php "Adaptation layer/debug/curt-fix-validation-runner.php" step11
```

**Expected (success path — what PASS 2 should look like):**

```
agent_class=App\Ai\Agents\NarrationAgent
system_prompt_bytes=<a few hundred>
status=ok
response_bytes=<>0
response_first_300=<some Carroll-flavored opening prose>
choices_count=3
input_classification=opening
state_delta_keys=objects_acquired,objects_lost,objects_transformed,conditions_added,conditions_removed,location_changed,knowledge_gained,relationship_changes,tracked_path_update,flags_set
```

**Expected (failure path — diagnosis surface):**

```
status=fail
exception_class=<some Prism / OpenAI / Throwable subclass>
exception_message=<the actual API error — typically "Invalid schema for response_format ... additionalProperties is required" if the schema fix regressed>
first_frame=<file>:<line> <Class>::<method>
```

If failure: read the exception_message verbatim. The most common symptoms after this point are (a) `OPENAI_API_KEY` missing in the env, (b) provider rate-limit / quota, (c) a model rename. None of those are runtime-logic bugs and none can hide behind silent stubs anymore.

### Result
Using DEFAULT_VALIDATION_GAME_ID=01kpe60znegetqss98x1kvxrb7 (Curt historical game_id in curt-game-log.json: 01kpv313jddy575ct6bv6cak4j). Pass CLI id or set CURT_FIX_VALIDATION_GAME_ID to override.
agent_class=App\Ai\Agents\NarrationAgent
system_prompt_bytes=487
status=ok
response_bytes=826
response_first_300=The river lies slack and shining, as if the heat has pressed it flat. Tall grass leans toward the water in slow surrender, and the air tastes faintly of mud and sun-warmed reeds. You stand at the bank where the damp earth gives way to a skin of ripples, listening to the small, steady noises that sur
choices_count=3
input_classification=opening
state_delta_keys=objects_acquired,objects_lost,objects_transformed,conditions_added,conditions_removed,location_changed,knowledge_gained,relationship_changes,tracked_path_update,flags_set
Laravel © 2026
---

## Rollback reference

| Scope | Command |
|---|---|
| Entire fix series (incl. v2) | `git reset --hard 6dd6fa9` (then `git push --force-with-lease origin main` if already pushed) |
| Curt Fix v2 only | `git revert <v2 sha — fill after commit>` (restores the WS-B-as-shipped state, including the silent stub) |
| WS-B only | `git revert a7272d2` |
| WS-A only | `git revert b66015d` |
| WS-C only | `git revert 4adb165` |
| WS-0 only | `git revert 44938d1` |

For database rollback after reverting WS-B: `php artisan migrate:rollback --step=1` will undo the `world_state` column add. Existing games won't break since the column was always nullable.

---

## When you've run everything

Append the commit SHA of the runbook update plus a `Status: GREEN/RED` line at the very bottom, then I'll fold whatever needs follow-up into a WS-D batch.





--------------------
Play 1+ turns in the UI, then:

tail -n 40 storage/logs/narration-$(date +%F).log
php artisan game:trace 01kpe60znegetqss98x1kvxrb7


#results:

[2026-04-28 00:48:08] production.INFO: narration.cold_open_audit {"story_id":1,"event_id":1,"session_adaptation_resolved":true,"session_number":1,"cold_open_present":true,"cold_open_first_120":"Heat shimmers off the river stones, and your stockings stick to the back of your knees as you lean over the grass, half-"} 
[2026-04-28 00:48:16] production.INFO: narration.llm_success {"site":"first_narration","story_id":1,"event_id":1,"response_bytes":807,"choices_count":3,"system_prompt_bytes":24016,"cold_open_present":true} 
[2026-04-28 00:52:54] production.INFO: narration.cold_open_audit {"story_id":1,"event_id":1,"session_adaptation_resolved":true,"session_number":1,"cold_open_present":true,"cold_open_first_120":"Heat shimmers off the river stones, and your stockings stick to the back of your knees as you lean over the grass, half-"} 
[2026-04-28 00:53:00] production.INFO: narration.llm_success {"site":"first_narration","story_id":1,"event_id":1,"response_bytes":809,"choices_count":3,"system_prompt_bytes":24016,"cold_open_present":true} 
[2026-04-28 01:01:09] production.INFO: narration.llm_success {"response_bytes":1158,"state_delta_keys_present":["objects_acquired","objects_lost","objects_transformed","conditions_added","conditions_removed","location_changed","knowledge_gained","relationship_changes","tracked_path_update","flags_set"],"input_classification":"authored_choice","mapped_option":"C","mapped_choice_id":"S1_C1","system_prompt_bytes":22158,"history_turns":2} 
[2026-04-28 01:01:09] production.INFO: narration.turn {"game_id":"01kpe60znegetqss98x1kvxrb7","event_id_before":1,"event_id_after":2,"session_number_before":1,"session_number_after":null,"turn_count":1,"is_first_turn_in_event":false,"advance_event_returned":true,"force_advanced":false,"is_continue":false,"input_classification":"authored_choice","mapped_choice_id":"S1_C1","mapped_option":"C","deterministic_match":true,"state_delta_summary":{"objects_acquired":0,"objects_lost":0,"objects_transformed":0,"conditions_added":1,"conditions_removed":0,"location_changed":"under the hedge (rabbit-hole edge)","knowledge_gained":2,"relationship_changes":0,"tracked_path_update":1,"flags_set":1},"world_state_object_count":0,"world_state_condition_count":1,"player_input_first_120":"You call out to him first and watch how he reacts before you commit to the hole.","narrator_response_first_120":"Your voice catches on the hot air as you send it after him—an instinctive, bright call meant to pin the impossible thing","choices_returned":["Dive into the rabbit-hole after him.","Crouch at the edge and peer into the darkness for any sign of him.","Circle the hedge and scan for another entrance or exit nearby."],"system_prompt_hash":"a57b05ec368f97147bc158109bdd1d4f596e47aa254124d8a0d4c84bc1b0a411","logged_at":"2026-04-28T01:01:09+00:00"} 



----


$results of second command:

=== GAME ===
id:                    01kpe60znegetqss98x1kvxrb7
story_id:              1
current_event_id:      2
current_session_number: null

=== TURN TRACE (1 rows) ===

--- TURN 1 (logged 2026-04-28T01:01:09+00:00) ---
  event_id:            1 -> 2
  session_number:      1 -> null
  turn_count_in_event: 1
  advance_returned:    true
  is_continue:         false
  prompt_hash:         a57b05ec368f
  player_input:        "You call out to him first and watch how he reacts before you commit to the hole."
  narrator_response:   "Your voice catches on the hot air as you send it after him—an instinctive, bright call meant to pin the impossible thing"
  choices_returned:
    A) Dive into the rabbit-hole after him.
    B) Crouch at the edge and peer into the darkness for any sign of him.
    C) Circle the hedge and scan for another entrance or exit nearby.
  rules:               3/4 PASS
    ! rule 3: session_number cleared without a transition target

=== SUMMARY ===
rows_shown:       1
total_violations: 1
Trace contains rule violations. Inspect the rows flagged above.