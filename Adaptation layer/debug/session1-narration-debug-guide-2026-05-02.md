# Narration Debug Guide — Session 1
**Game:** `01kpe60znegetqss98x1kvxrb7` | **Date:** 2026-05-02

---

## The system we are building

Every turn, regardless of how the player inputs, the narrator should:

1. Open the current event's scene if it just changed
2. Respond to what the player did — including off-script actions — with enough genuine follow-through to feel like a real choice mattered, then steer naturally back toward the session's active events (off-script = side quest, not a wall)
3. Surface the adaptation's designed choice at the right moment, not a generic action menu
4. Record what the player decided — including passive choices made via continue
5. Accumulate state from what the player actually experienced, not from canon assumptions
6. Fire the session close at the right moment with the retention hook intact

Right now, none of steps 1–6 are consistently working.

---

## What is broken and why

### 1. Events advance but their scenes never render

This is the root problem. The journal shows 18 events as "reached." The player never experienced most of them.

When `advance_returned: true` fires, the new event's content enters the system prompt. But the model reads the conversation history (previous scene still active) and a `__continue__` input and does the obvious thing: it keeps the previous scene going. There is no hard signal that says "scene has changed — open it now."

**Evidence from the trace:**

| Turn | Event advanced to | What that event is | What the narrator wrote |
|---|---|---|---|
| 6 | 6 | Alice finds bottle and drinks it | "The darkness slips past you in cool bands…" (still falling) |
| 7 | 7 | Alice shrinks and forgets the key | "The descent keeps unspooling…" (still falling) |
| 8 | 8 | Alice finds cake and eats it | "You twist in the long, impossible drop…" (still falling) |
| 14 | 12 | Alice questions identity while fanning | "You try again—because trying is the only thing that feels like movement…" (still in the hall) |

DRINK ME, the shrinking, EAT ME, the growing scene, the identity spiral — none of these appeared in the narration. The journal logged them; the player never saw them.

**Where to look:** `resources/views/ai/agents/narration/system-prompt.blade.php` — the `is_first_turn_in_event` flag needs to become a hard override instruction when the event has just changed: "THIS IS A NEW SCENE. Open [event title] before anything else." Right now it is a soft signal the model ignores when history momentum is strong.

---

### 2. Off-script actions are either walled or silently erased — they should be side quests

When a player does something off-canon, there are two failure modes in this trace:

- **Turn 2 (gun):** Handled well. The narrator acknowledged the attempt ("Your hand goes, with sudden certainty, to where a gun ought to be—") before redirecting. This is the right pattern.
- **Turn 3 (go home, call buddy):** The mood bent slightly but the choice menu immediately snapped back to rabbit-hole options. The player's departure was not played with at all.
- **Turn 10 (invented water bottle):** The narrator redirected to "dusty glass" without acknowledging the player's specific act of drinking and pocketing. The fiction was silently dropped.

The design intent is: **off-script actions are side quests, not blocks.** The narrator should follow the player's emergent thread for a beat or two — enough to feel like it mattered — then find a natural path back to the session's active events. "You pocket the dusty bottle anyway, not sure why, and the sense of movement ahead pulls you back toward the lamp-lit end of the passage." That's steering, not erasing.

**Where to look:** `resources/views/ai/agents/narration/system-prompt.blade.php` — the current off-canon handling rule. It needs a "follow for one beat, then return" instruction rather than a hard redirect. The `input_classification: unsupported` path is where this lands.

---

### 3. Designed choices never surface — the narrator generates its own

The adaptation data is passed to the model (`choice_design_keys=branching_choice_1,expressive_choices,branching_choice_2,branching_choice_3` confirmed in logs). But in 20 turns only S1_C2 surfaced correctly (turn 14). The three expressive choices and the DRINK ME branch never appeared.

The model will not use authored choices unless it is told exactly when to use them. Dumping the choice design into context and hoping the model picks it up does not work. The system prompt needs to tell the narrator: "when you are writing the first turn in beat ESCALATION (fall), use this specific expressive question as your three options — not your own."

There is also a data problem underneath this: the DRINK ME branching choice exists in the `beat_map` but has **no entry in `session_choice_design`**. The runtime consumes `session_choice_design`. So the DRINK ME branch cannot be served regardless of prompt quality — it is not there to serve.

**Where to look:**
- `resources/views/ai/agents/narration/system-prompt.blade.php` — add per-beat instructions that tell the model which specific choice to surface at which moment
- Adaptation data for Session 1 — add the DRINK ME branching choice as a proper `branching_choice` entry with options A/B/C. This is the Q4 REVISE item the pipeline's own editorial verification flagged and that was never resolved

---

### 4. The Session 1 choice design is internally misaligned

The `beat_map` and `session_choice_design` disagree on what "Choice 2" is:
- `beat_map` says Choice 2 = DRINK ME posture
- `session_choice_design` says S1_C2 = the Rabbit/gloves/fan moral moment

The `consequence_map` and `branch_opportunities` in `story_session_map` reference these IDs to build cross-session payoffs. With the IDs misaligned, those payoff paths are wired to the wrong moments.

This needs to be resolved before any runtime fix for choice surfacing will be reliable. The canonical Session 1 branching set needs to be decided once and written consistently everywhere: beat map, choice design IDs, consequence map headings, and story session map references.

**Where to look:** `database/exports/adapptation-third-try.json` → `sessions[0].editorial_verification.question_results.consequential_choices` — the REVISE verdict and the exact instruction for how to reconcile.

---

### 5. Authored branching choice served, but continue leaves dimension unrecorded

Turn 14: S1_C2 was correctly matched and the verbatim authored options were served. The player hit continue. `advance_returned: true`. No dimension was recorded.

Continue is intentional — players should be able to autopilot. But when a branching choice is active and the player continues, that should be treated as the passive/opportunistic path (typically Option C in the designs) and recorded as such. Right now it advances clean with null dimension, breaking every downstream payoff that depends on S1_C2.

**Where to look:** `app/Http/Controllers/User/Game/PromptController.php` — in the continue handling path, check whether the prior turn's choices were a deterministic B4 match. If yes, record Option C (or the design's designated passive path) before advancing.

---

### 6. Session close design never fires

The session close (`session_close_design`) contains the S1_C3 identity-proof choice and the retention hook that seeds Session 2. Turn 15 passed the trigger event (event 12, "Alice questions identity while fanning") on a continue. The authored close prose and hook never appeared.

There is no code that detects "player has reached the session-end event" and injects the session close context. This is a missing feature in the runtime.

**Where to look:** `app/Http/Controllers/User/Game/PromptController.php` — add a session-close detection check: when `current_event_id` reaches or passes the position of the session's last authored branching choice, inject `session_close_design` prose and `session_end_choice` into the narration context for that turn.

---

### 7. State is built from canon assumptions, not from player experience

In the trace, the fan and gloves appear in choices from turn 14 onward as if the player has them. But the player never experienced the scenes where Alice picks them up (events 11–12 were continue-advanced without rendering). The narrator references the key, the bottle, the fan as present because canon says they should be — not because the player acquired them through narrated play.

Separately: when a player invents an object (turn 10 water bottle), the current state system has no way to decide whether to accept or deny that claim, so it defaults to denial. An off-script acquisition that the narrator chose to play along with should be able to enter `objects_acquired`. One that was denied should not.

**Where to look:** `app/Http/Controllers/User/Game/PromptController.php` — the `applyStateDelta` path. The `state_delta` from the model should be the only source of state truth, not canon inference. Check whether the world state fed back into the next turn's system prompt reflects what the player actually did or what canon says happened.

---

### 8. Rule checks pass even when the session is broken

"All green, 0 violations" in the trace. But the DRINK ME scene was never narrated, S1_C2 dimension was not recorded, the session close didn't fire, and state is canon-assumed not player-earned.

The current rules check ordering, session number integrity, and type safety. They do not check:
- Whether the narrator's response matches the current event (scene transition check)
- Whether an authored branching choice was served and recorded when the event called for one
- Whether `input_classification: authored_choice` or a dimension was recorded at known choice-moment event positions

Until the trace rules cover these, a broken session will always look healthy.

**Where to look:** `app/Console/Commands/GameTraceCommand.php` — add rules that check for known authored-choice positions within a session and flag when those events were passed without a `mapped_choice_id` in the log.

---

## Fix order

| # | Fix | Depends on |
|---|---|---|
| 1 | Hard scene-transition signal on event advance | Nothing — start here |
| 2 | Off-script "side quest" follow-then-steer pattern | Nothing — can run in parallel with 1 |
| 3 | Resolve S1 choice design misalignment (data) | Nothing — prerequisite for 4 |
| 4 | Add DRINK ME as proper branching choice in session_choice_design | Fix 3 first |
| 5 | Per-beat authored choice injection in system prompt | Fix 1 + Fix 3 |
| 6 | Continue on active authored branch → record passive path | Fix 5 |
| 7 | Session close detection and injection | Fix 1 |
| 8 | State from player experience, not canon assumption | Fix 1 + Fix 5 |
| 9 | Add narration/choice-surface checks to game:trace rules | All of the above |

---

## Test for Fix 1 (scene transition)

1. Reset to event 1. Play one S1_C1 input. Confirm advance to event 2.
2. Hit continue. The narrator response must open event 2's scene ("Alice falls down the well") — not continue the hedgerow.
3. Hit continue again. Must open event 3 (lamp-lit hall / passage).
4. If events 2 and 3 still narrate the fall, the `is_first_turn_in_event` flag is not overriding conversation history. Escalate to a hard injected line in the system prompt: `"=== NEW SCENE: [event title] — open this scene before responding to player input ==="`.
