---
name: Narration Fix Plan
overview: "Fix 5 narration bugs identified in the session 1 diagnostic: events advancing without rendering their scenes, off-script actions erased without acknowledgment, authored branching choices bypassed by continue, session close design never firing, and exit point drift (session end is implicit/heuristic where session start is already explicit)."
todos:
  - id: fix1-prompt-event-id
    content: "PromptController.php line 162: change prompt creation from event_id=$resolvedEventId to event_id=$currentEvent->id"
    status: completed
  - id: fix2-scene-open-signal
    content: "system-prompt.blade.php lines 282-284: replace soft 'MAY narrate' with hard 'NEW SCENE — OPEN IT NOW' directive"
    status: completed
  - id: fix3-offscript-rule
    content: "system-prompt.blade.php lines 145-152: rewrite off-script rule to honor specific claimed action and allow 1-2 beat side quest before steering"
    status: completed
  - id: fix4-continue-authored-branch
    content: "PromptController.php store(): add continue-fallback check that detects previous turn served an authored choice set and defaults to option C"
    status: completed
  - id: fix6-exit-point-pipeline
    content: "Adaptation pipeline Phase 7: extend session_close_design schema to include session_close_trigger_event_id as an explicit integer selected from the event list"
    status: completed
  - id: fix5-session-close
    content: "PromptController.php renderSystemPrompt(): use session_close_trigger_event_id from adaptation data (not range heuristic) to fire session close injection"
    status: completed
isProject: false
---

# Narration Fix Plan

## Root cause map

```mermaid
flowchart TD
    A["Turn fires in event 5\nadvance_returned = true"] --> B["Prompt created with\nevent_id = nextEvent.id (6)"]
    B --> C["Next turn: count(prompts where event_id=6) = 1"]
    C --> D["isFirstTurnInEvent = false"]
    D --> E["System prompt says:\n'screenplay already narrated, do not repeat'"]
    E --> F["Model continues previous scene\nEvent 6 (DRINK ME) never rendered"]
```



The fix is a one-line change in prompt creation, paired with a stronger scene-open signal in the system prompt.

---

## Change 1 — Fix prompt `event_id` assignment (root cause)

**File:** `[app/Http/Controllers/User/Game/PromptController.php](app/Http/Controllers/User/Game/PromptController.php)` — line 162

**Current:**

```php
$game->prompts()->create([
    'event_id' => $resolvedEventId,
    'response' => $aiResult['response'],
    'choices'  => $aiResult['choices'],
]);
```

**Change to:**

```php
$game->prompts()->create([
    'event_id' => $currentEvent->id,   // record the event being narrated, not the one advanced to
    'response' => $aiResult['response'],
    'choices'  => $aiResult['choices'],
]);
```

After this change: when event 5 → 6 advances, the prompt is recorded under event 5. The next turn counts 0 prompts for event 6, so `isFirstTurnInEvent = true` fires correctly.

`$resolvedEventId` is only used in this one place — no other side effects.

---

## Change 2 — Strengthen scene-open signal in system prompt

**File:** `[resources/views/ai/agents/narration/system-prompt.blade.php](resources/views/ai/agents/narration/system-prompt.blade.php)` — lines 282–284

**Current:**

```
@if(!empty($isFirstTurnInEvent))
This is TURN 1 of this event. You MAY narrate the CURRENT_EVENT screenplay...
```

**Change to:**

```
@if(!empty($isFirstTurnInEvent))
=== NEW SCENE — OPEN IT NOW ===
The story has moved to a new event. The previous scene is complete.
Your response MUST open the CURRENT_EVENT scene before anything else.
The conversation history shows the previous scene — that scene is over. Ignore its momentum.
Narrate the CURRENT_EVENT screenplay (as cinematic prose) up to the first natural decision point.
```

This makes the scene-open a hard directive, not a soft permission — which is what the model needs to override conversation history momentum.

---

## Change 3 — Off-script actions acknowledged before redirect (side quest intent)

**File:** `[resources/views/ai/agents/narration/system-prompt.blade.php](resources/views/ai/agents/narration/system-prompt.blade.php)` — lines 145–152

**Current:**

```
If the Player attempts something off-track:
- Integrate it as an in-scene attempt by Alice.
- Show believable character/environment reaction.
- Then present choices that guide back toward canon momentum.
```

**Change to:**

```
If the Player attempts something off-track:
- Honor the specific action they claimed — not just their general intent.
  If they said "I found a bottle and drank from it," acknowledge THAT act before grounding it
  in what actually exists. Never silently replace the player's claimed action with a different one.
- Follow the off-script thread for 1-2 turns if the player doubles down.
  Treat it as a side quest: give it real in-world consequence, let it breathe.
- Then let the scene's gravity naturally pull back toward the session's active events.
  The steering must feel organic — never abrupt, never a wall, never a named redirect.
- Choices should open a path back without naming the destination.
```

---

## Change 4 — Continue on active authored choice records the passive path

**File:** `[app/Http/Controllers/User/Game/PromptController.php](app/Http/Controllers/User/Game/PromptController.php)` — `store()` method, around line 47

After the current `$deterministicMatch` assignment, add a continue-fallback check:

```php
// existing
$deterministicMatch = $this->matchAuthoredChoice(
    playerInput: $isContinue ? '' : $prompt,
    sessionAdaptation: $sessionAdaptation,
);

// NEW — if continue and previous choices were an authored set, default to option C
if ($isContinue && $deterministicMatch === null) {
    $lastChoices = $game->prompts()->latest()->first()?->choices ?? [];
    foreach ($lastChoices as $choiceText) {
        $match = $this->matchAuthoredChoice((string) $choiceText, $sessionAdaptation);
        if ($match !== null) {
            // Previous turn served an authored branching choice — continue = passive path (C)
            $deterministicMatch = [
                'option'    => 'C',
                'choice_id' => $match['choice_id'],
                'text'      => (string) ($lastChoices[2] ?? $choiceText),
            ];
            break;
        }
    }
}
```

This means: if the player hits continue and the previous turn's choices matched an authored branching set, the system records option C (the passive/opportunistic path), narrates its consequence, and advances with the dimension recorded.

---

## Change 5 — Exit point drift: add `session_close_trigger_event_id` to the adaptation pipeline

**The problem:** Session start is explicit — `entry_point_diagnosis.start_event_id` is an integer pointing at a real event, authored by the pipeline. Session end is implicit — the runtime currently has to guess using a heuristic ("last 5 events of the range"), which is non-deterministic and pacing-unsafe.

This is the same class of bug as entry point drift, on the exit side.

**The fix has two parts:**

### Part A — Adaptation pipeline (Phase 7 prompt change)

The Phase 7 prompt that generates `session_close_design` must be extended to also output a `session_close_trigger_event_id`. The prompt must instruct the LLM to **select a specific event from the event list** — not describe it abstractly:

```
From the provided event list for this session, select the exact event that represents
the session's closing beat — the moment where the player makes their final decision
before the session ends. Return its event_id as an integer.
```

The output schema for `session_close_design` becomes:

```json
{
  "session_close_trigger_event_id": 12,
  "resolution_prose": "...",
  "hook_transition": "...",
  "session_end_choice": { ... }
}
```

This field is an **authored decision**, not a computed value. The pipeline selects it; the runtime executes it. No guessing.

**Migration for existing data:** Alice's current `session_close_design` in `database/exports/adapptation-third-try.json` does not have this field. After the pipeline prompt change, re-run Phase 7 for session 1, or manually patch: `session_close_trigger_event_id: 12` (the "Alice questions identity while fanning" event, which is where S1_C3 is designed to fire).

### Part B — Runtime: use explicit field, not heuristic

**File:** `[app/Http/Controllers/User/Game/PromptController.php](app/Http/Controllers/User/Game/PromptController.php)` — `renderSystemPrompt()`

```php
$isSessionEnd = false;
$sessionCloseDesign = null;

if ($sessionAdaptation?->session_close_design && $turnCount === 0) {
    $closeDesign = $sessionAdaptation->session_close_design;
    $triggerEventId = $closeDesign['session_close_trigger_event_id'] ?? null;

    if ($triggerEventId !== null) {
        // Explicit authored trigger — deterministic
        $isSessionEnd = $currentEvent->id === (int) $triggerEventId;
    } else {
        // Fallback heuristic (legacy data without the field)
        $sessionEventRange = $sessionAdaptation->entry_point_diagnosis['session_event_range'] ?? null;
        if ($sessionEventRange) {
            [, $rangeEnd] = array_map('intval', explode('-', $sessionEventRange));
            $isSessionEnd = $currentEvent->position >= ($rangeEnd - 4);
        }
    }

    if ($isSessionEnd) {
        $sessionCloseDesign = $closeDesign;
    }
}
```

The fallback heuristic stays for existing adaptation data that doesn't yet have the field. New data produced after the pipeline change will use the explicit path.

Pass `$isSessionEnd` and `$sessionCloseDesign` into the view. In `system-prompt.blade.php`, add a block inside the adaptation section (after the branching choices block):

```
@if(!empty($isSessionEnd) && !empty($sessionCloseDesign))
--- SESSION CLOSE ---
The session is reaching its end. When the current beat resolves naturally, deliver the
session close prose below, then present the SESSION-END CHOICE as the player's final decision.
Do not rush to it — wait for the scene's natural resolution point.

CLOSE PROSE:
{{ $sessionCloseDesign['resolution_prose'] ?? '' }}

HOOK:
{{ $sessionCloseDesign['hook_transition'] ?? '' }}

SESSION-END CHOICE:
{{ $sessionCloseDesign['session_end_choice']['choice_question'] ?? '' }}
A: {{ $sessionCloseDesign['session_end_choice']['option_a']['text'] ?? '' }}
B: {{ $sessionCloseDesign['session_end_choice']['option_b']['text'] ?? '' }}
C: {{ $sessionCloseDesign['session_end_choice']['option_c']['text'] ?? '' }}
@endif
```

---

## Architectural principle: start and end must both be explicit

```mermaid
flowchart LR
    subgraph pipeline [Adaptation Pipeline]
        P1["Phase 3\nentry_point_diagnosis\nstart_event_id = 1"]
        P7["Phase 7\nsession_close_design\nsession_close_trigger_event_id = 12"]
    end
    subgraph runtime [Runtime]
        R1["isSessionStart\n= currentEvent.id === start_event_id"]
        R2["isSessionEnd\n= currentEvent.id === session_close_trigger_event_id"]
    end
    P1 --> R1
    P7 --> R2
```



The runtime is a **pure executor**. It does not decide where sessions start or end — the adaptation pipeline decides, stores the decision as integers, and the runtime reads them. Both entry and exit are now authored decisions.

---

## Files changed

- `[app/Http/Controllers/User/Game/PromptController.php](app/Http/Controllers/User/Game/PromptController.php)` — Changes 1, 4, 5B
- `[resources/views/ai/agents/narration/system-prompt.blade.php](resources/views/ai/agents/narration/system-prompt.blade.php)` — Changes 2, 3, 5B
- Adaptation pipeline Phase 7 prompt — Change 5A (adds `session_close_trigger_event_id` to output schema)
- `database/exports/adapptation-third-try.json` — manual patch: add `session_close_trigger_event_id: 12` to Session 1's `session_close_design`

---

## How to validate

1. Reset game (`step5`). Play one S1_C1 input. Hit `__continue_`_. The narrator response must open the DRINK ME scene, not continue the hedgerow. Confirms Fix 1.
2. Type "I found a bottle and drank from it." The narrator must acknowledge the drinking act before redirecting. Confirms Fix 3.
3. Play up to turn 14 conditions. Hit `__continue__` when S1_C2 is shown. Check `narration.turn` log — `mapped_choice_id: S1_C2`, `mapped_option: C` must appear. Confirms Fix 4.
4. Check `session_close_design.session_close_trigger_event_id` is present in the DB/export for session 1. Confirms Fix 5A.
5. Play to event 12. Session close prose and S1_C3 identity question must appear. Confirms Fix 5B.

