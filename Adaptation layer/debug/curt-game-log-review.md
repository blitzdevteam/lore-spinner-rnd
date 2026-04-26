# Curt's Actual Game Log — Evidence Review

**Date:** 2026-04-26
**Source data:** `Adaptation layer/debug/curt-game-log.json` (28 prompts, game `01kpv313jddy575ct6bv6cak4j`, story Alice's Adventures in Wonderland, played 2026-04-22 17:15 → 21:45 UTC).
**Companion docs:**
- `curt-feedback-diagnosis.md` — root-cause hypotheses.
- `curt-feedback-fix.md` — fix plan.
- `runtime-logic.md` — per-turn data flow.

This doc is the *evidence pass*. The diagnosis was written before we had the actual game log. With the log in hand, three things happen:

1. **Defect B (no state persistence) is hard-confirmed** — every state column is `null` in the export.
2. **A new critical defect is discovered** — `Game::prompts()` has `->oldest()` baked into the relation, and `PromptController` chains `->latest()->first()` on top of it. The two ORDER BY clauses stack and the database picks the **oldest** row, not the latest. **Every player input overwrites `prompts[0].prompt` instead of attaching to the prompt it answered**, and the conversation history sent to the AI is the **first 6 turns of the game**, frozen forever.
3. **Defects A (rehash) and C (choices) are visible in the narrative arc** — Curt's session reads like a movie of the canonical Alice plot rolling forward turn after turn, with no sign that any player input has weight.

The new defect from item 2 promotes from "unknown" to **the most likely single explanation for Curt's #1, #2, #3, and #4 simultaneously.** It belongs at the top of the fix plan.

---

## 1. Hard evidence on the game record

```json
"current_session_number": null,
"current_beat_type": null,
"branching_choices_taken": null,
"tracked_dimensions": null,
"branch_resolution_log": null,
```

All four state columns: **null** after 28 turns spanning ~30 minutes of active play across 16 distinct events (Chapter 1 → Chapter 3 in the canonical text).

This is direct verification of `curt-feedback-diagnosis.md` §3 (Defect B). Nothing in the runtime writes to these columns. The only state-related update I expected to see was `current_session_number` (the one column `PromptController` does maintain) — and it is also `null`, which means the export captures a Game where `current_event_id` advanced through events but **no event in this game's history was ever a session-boundary crossing**. For Alice's Session 1 range `1-23`, all of Curt's reached events (id 1 through... the Caucus-race event, story-position somewhere in the late teens) sit inside Session 1, so no transition was triggered. The runtime never had cause to update `current_session_number`. That is consistent with my diagnosis but doesn't add new information.

---

## 2. New defect — `oldest()` + `latest()` collision

### 2.1 The bug

`Game::prompts()` declares `->oldest()` on the relation:

```76:79:app/Models/Game.php
public function prompts(): HasMany
{
    return $this->hasMany(Prompt::class)->oldest();
}
```

`PromptController::store()` chains `->latest()->first()` on the same relation to find "the latest prompt to attach the player's input to":

```31:33:app/Http/Controllers/User/Game/PromptController.php
$game->prompts()->latest()->first()?->update([
    'prompt' => $isContinue ? '__continue__' : $prompt,
]);
```

`buildConversationHistory()` does the same thing on a larger window:

```193:198:app/Http/Controllers/User/Game/PromptController.php
$prompts = $game->prompts()
    ->latest()
    ->limit(6)
    ->get()
    ->reverse();
```

`CreatePromptAction` does it too:

```14:14:app/Actions/Prompt/CreatePromptAction.php
$currentPrompt = $game->prompts()->latest()->first();
```

**Laravel does not deduplicate `orderBy` clauses.** `oldest()` adds `ORDER BY created_at ASC`. Then `latest()` appends `ORDER BY created_at DESC`. The resulting SQL is:

```sql
SELECT * FROM prompts WHERE game_id = ?
ORDER BY created_at ASC, created_at DESC
LIMIT 1
```

In MySQL/Postgres, multiple ORDER BY clauses on the same column behave as: sort by the first; for ties, use the second. `created_at` has microsecond precision, so ties effectively never occur. **The second clause is silently discarded; `ORDER BY ASC` wins; `first()` returns the OLDEST prompt.**

### 2.2 The symptom in the log — and it is unmistakable

The export shows `prompts[0].prompt = "Support the Dodo by urging everyone to try a more energetic plan to get dry."` That string is **identical** to `prompts[27].choices[0]`, which the narrator generated on Curt's most recent turn. So this is Curt's **last-recorded selection** — but it lives on `prompts[0]`, the cold-open record from `2026-04-22T17:16:39+00:00`.

Every other prompt — `prompts[1]` through `prompts[27]` — has `prompt: null`. Twenty-seven turns of player input, vanished. Each one was written, then overwritten on the next turn, all of them landing on `prompts[0]`.

This is consistent with the bug. It is hard to explain any other way.

### 2.3 Downstream consequences (every one of them visible in the log)

**Consequence 1 — the AI never sees what the player chose.**
`buildConversationHistory()` returns the 6 OLDEST prompts (turns 1–6: rabbit, fall, marmalade jar, picture-grab, hall, key-find), reversed. The narrator's `CONVERSATION SO FAR` block on **turn 28** contains turns 1 → 6 as if they are turns 22 → 28. Every turn after the 6th is invisible to the model.

**Consequence 2 — `PLAYER'S ACTION:` is overwritten every turn.**
The new prompt row is created right after the overwrite. So `PLAYER'S ACTION` on turn N is the actual current input — but the model sees `prompts[0].prompt` (the most recent overwrite) inside the conversation transcript that's reconstructed from the DB on the next turn. Since `prompts[0]` is at the *top* of the reversed list, it appears as a **player turn from way back in the conversation** — possibly creating ghost-references to actions the player never took.

**Consequence 3 — the narrator drifts to canonical autopilot.**
With history frozen at turns 1–6 (rabbit chase + fall + hall) and the current turn's `PLAYER'S ACTION` being a one-off divergent input, the narrator's safest path is the system-prompt's canon-fidelity rule: keep narrating the canonical Alice plot. Reading `prompts[1]` through `prompts[27]` chronologically tells the canonical story almost verbatim — Alice falls, finds key, can't reach door, drinks bottle, shrinks, eats cake, doesn't grow, *somehow* becomes huge, cries flood, meets Mouse, swims to shore, Caucus-race. **The plot rolls forward with or without the player.**

This is also exactly Curt's #1 ("story moves backward / re-explains setup") in disguise — it's not really moving backward. It's that the player's actions have **zero effect**, and the narrator is running through canonical beats while the player feels like they keep being delivered to scenes they didn't ask for.

### 2.4 Suggested verification

```bash
php artisan tinker --execute="
\$game = App\Models\Game::find('01kpv313jddy575ct6bv6cak4j');
echo 'oldest+latest result: ', \$game->prompts()->latest()->first()->id, PHP_EOL;
echo 'fresh latest result:  ', App\Models\Prompt::where('game_id', \$game->id)->latest()->first()->id, PHP_EOL;
"
```

Expected: the first line returns `01kpv338ae37s32e8mtkpfkw8k` (prompts[0]); the second returns `01kpvjevjw23g5jgapb8afj55g` (prompts[27]). If those match my prediction, the bug is confirmed at runtime.

### 2.5 Fix

Two-character change at the call sites:

- `PromptController.php:31` — `$game->prompts()->latest()->first()` → `$game->prompts()->reorder('created_at', 'desc')->first()`. The `reorder` method clears existing orderBy clauses before applying the new one, so the relation's `oldest()` is overridden cleanly.
- `PromptController.php:193-198` — same, plus the `limit(6)`.
- `CreatePromptAction.php:14` — same.

Or, the more conservative fix: drop `->oldest()` from `Game::prompts()` (the relation), and explicitly call `->oldest()` everywhere the existing oldest-first behavior is needed. This avoids action-at-a-distance.

This fix is XS-effort, P0-impact. **It should ship before any other fix in the plan**, because:

1. It is a likely cause of Curt's #1, #2, #3, #4 all at once.
2. Without it, the WS-A turn-trace logging in the fix plan will continue to capture phantom "player_input" values from `prompts[0]` — making every other diagnostic step misleading.
3. It is independent of WS-B / WS-C and ships in <30 min.

---

## 3. Re-reading Curt's symptoms with this defect in mind

### 3.1 #1 "Story appears to move backward"

Re-explained, with high confidence:
- The player chose to dive into the hole (turn 1) → the narrator narrated the dive (turn 2 response).
- The player called out to the rabbit at the lip of the hole (some later turn) → input was overwritten on `prompts[0]`, the new prompts row created with `prompt: null`. The next turn's narrator received `prompts[0]` as a "history" entry showing "Support the Dodo..." (or whatever the most recent overwrite was) and the canonical hedge-hole context.
- The narrator could not reconcile a Dodo-themed action with hedge-hole context, fell back to canon-fidelity, and **re-narrated the hedge/hole setup** — exactly Curt's "returned to hedge/hole setup and re-explained entry."

The "moving backward" experience is the narrator re-anchoring to canonical opening beats whenever the player input doesn't make sense in the broken history it received.

### 3.2 #2 "Rehashes already-covered events"

Same mechanism. Conversation history is permanently turns 1–6. After turn 6, every turn's narrator sees turns 1–6 as "what just happened." Repetition is structurally guaranteed once you pass turn 6.

The diagnosis blamed "the screenplay text never rotates out + 6-turn shallow window." That's still true, but the **window doesn't rotate at all** for this game — making the diagnosis's hypothesis the optimistic version of the actual situation.

### 3.3 #3 "Progress not preserved"

Two layers, both confirmed:
1. State columns are `null` (Defect B from diagnosis §3 — confirmed).
2. Even *implicit* progress in the chat history is gone: after turn 6, no player input survives into the next turn's prompt context. So inventory, decisions, social moves — none of them propagate.

This is much worse than the diagnosis assumed. The diagnosis said "after 6 turns, the oldest history rotates out." The actual situation: "the most recent 22 turns are completely invisible."

### 3.4 #4 "Cannot move story forward"

Re-framed: the player CAN move the story forward — it's just that whether the story moves forward has nothing to do with the player. `advance_event` is decided by the model based on context that does not include the player's actions. Curt's events advanced 16 times across 28 turns:

| Turn | Event title |
|---|---|
| 1 | Alice notices the White Rabbit |
| 2-4 | Alice falls down the well |
| 5-6 | Alice chases the Rabbit into the hall |
| 7 | Alice finds key and unlocks small door |
| 8-9 | Alice glimpses garden beyond the door |
| 10-11 | Alice finds bottle and drinks it |
| 12-13 | Alice shrinks and forgets the key |
| 14-15 | Alice finds cake and eats it |
| 16 | Alice grows and talks to her feet |
| 17-18 | Alice hits roof and cries |
| 19-20 | White Rabbit flees as Alice asks |
| 21-23 | Alice questions identity while fanning |
| 24 | Alice shrinks and drops the fan |
| 25-27 | Alice runs to door and falls into pool |
| (skipped) | Alice meets Mouse in the pool |
| (skipped) | Animals gather and swim to shore |
| 28 | Wet party debates and hears Mouse read |
| (current) | Dodo proposes and runs a Caucus-race |

The story marched through 16+ events — but Curt's experienced agency was zero, because none of his choices were visible to the model after turn 6. So the **subjective** experience of "I can't move the story forward" is correct, even though the event pointer kept advancing.

### 3.5 #5 "Choices feel leading"

Confirmed in the log. Reading `prompts[N].choices` chronologically:

- Choices on turn 8 (after Alice has unlocked the small door): "Open the little door and peer through immediately" / "Pocket the golden key and check the curtain..." / "Test the tiny key once more on a nearby full-sized door". All three move forward; option 1 is the canonical-next-beat ("peer through").
- Choices on turn 10 (Alice trying to figure out the size puzzle): "Stride to the glass table and search it for anything useful" / "Kneel at the little doorway..." / "Close the door partway and tug the curtain aside". Option 1 is canon (find the bottle).
- Choices on turn 28 (debating drying off, Dodo proposing): "Press the Dodo to propose exactly what the energetic plan is, right now" / "Appeal to the Mouse..." / "Challenge the Duck...". Option 1 is canonically forward.

In every observed turn, **option 1 is the canonical-next-beat**, exactly per the system prompt's "convergence gradient" rule (`runtime-logic.md` §4.2 / `system-prompt.blade.php` lines 162-175). Curt's "leading" perception is *the documented behavior of the prompt*.

There is also no evidence that **authored A/B/C from `session_choice_design.branching_choice_*`** ever surfaced in any choice list. The narrator generated three forward-momentum options each turn, never the pre-authored options the adaptation pipeline produced. This further confirms diagnosis §5.4 (no link between authored choices and rendered choices).

### 3.6 #6 "Preamble feels stylistically wrong"

The export does not include the `stories.opening` field (only the gameplay prompts), so I cannot directly verify the preamble Curt saw. But: the **first event narration** (turn 1, `prompts[0].response`) renders the cold-open content in clean Carroll voice — "Heat lies over the bank in a lazy sheet..." — proving that `entry_point_diagnosis.cold_open` is being consumed by the in-game narrator (`isSessionStart=true` path). The defect described in §6 of the diagnosis (story-creation-time `StoryOpeningGeneratorJob` doesn't read the cold open) remains hypothesis-only, but the in-game-side wiring is verified working.

### 3.7 #7 "TTS broken"

Already disposed of as not-a-defect. The log doesn't add anything.

### 3.8 #8 "Surrealism masks failures"

The log proves Curt's point empirically. Read `prompts[0]` through `prompts[27]` as a story and it **reads cohesively**, because the canonical Alice plot is internally consistent. A reader without the diagnostic frame wouldn't notice that the player has been gaslit for 27 turns.

The case for instrumented turn-tracing (WS-A in the fix plan) is now stronger: this game **looks** functional in narrative terms while being structurally broken.

---

## 4. Updated fix-plan priority

Insert one new item at the top of `curt-feedback-fix.md` workstreams:

| New priority | Item | Effort | Impact |
|---|---|---|---|
| **P0 — ship before anything else** | **Fix the `oldest()` + `latest()` collision** in `app/Models/Game.php`, `app/Http/Controllers/User/Game/PromptController.php` (2 sites), `app/Actions/Prompt/CreatePromptAction.php`. Use `reorder('created_at', 'desc')` or drop the relation-level `oldest()`. | XS (one PR, trivial diff) | Likely root cause of Curt's #1, #2, #3, #4. Without this, all other fixes are diagnosed against corrupted data. |

Then continue with WS-A (turn-trace logging + `isFirstTurnInEvent`) so we can verify the fix landed cleanly.

The same `->oldest()->latest()` pattern exists in `app/VoiceLab/Models/VoiceLabSession.php:66` and probably in `app/VoiceLab/Actions/ProcessVoiceTurnAction.php:93`. Worth a sweep-fix at the same time, but out of scope for the game runtime cycle.

---

## 5. What this evidence does NOT prove

For honesty:

- **The `oldest()` + `latest()` collision is high confidence as the explanation, not certainty.** I have not run the SQL against the live DB to confirm MySQL/Postgres processes the two ORDER BY clauses as I described. The repro in §2.4 will give certainty; the symptom (`prompts[0].prompt` populated, all others null) is already very strong evidence.
- **`current_session_number = null` is consistent with both my diagnosis and the alternative hypothesis** (no session boundary was crossed in this game). It does not by itself prove Defect B. The other three null columns (`branching_choices_taken`, `tracked_dimensions`, `branch_resolution_log`) do prove Defect B because they should have at least one entry per turn (`branch_resolution_log`) regardless of session boundary.
- **The session-boundary cold-open re-narration mechanism (diagnosis §1.3) is not verified by this log** because Curt did not cross a session boundary. It remains a latent issue, not an active one.
- **The `StoryOpeningGeneratorJob` defect (diagnosis §6) is not verifiable from this log** because the export doesn't include `stories.opening`. The in-game cold-open path (which is a different code path) IS verified working from `prompts[0].response`.

---

## 6. Recommended doc deltas

1. **`curt-feedback-fix.md`** — add the new P0 item at the top of the priority list (§1 of this doc, item 2.5 above). Re-order WS sections so the prompt-overwrite fix is the first PR, before WS-A.
2. **`curt-feedback-diagnosis.md`** — add a short subsection in §10 noting the new defect was discovered post-export and the impact reassessment. Link here.
3. **(This doc)** — kept as the verification record. Future work on this game's data should reference this file.

I'll wait for confirmation before patching the other two docs, since the ordering of the fix plan is a decision worth keeping explicit.

---

*End of evidence review. Cross-link: `curt-feedback-diagnosis.md` for hypotheses, `curt-feedback-fix.md` for the action plan, `runtime-logic.md` for per-turn data flow.*
