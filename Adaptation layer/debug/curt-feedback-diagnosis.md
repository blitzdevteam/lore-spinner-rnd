# Curt Feedback — Code-Grounded Diagnosis

**Date:** 2026-04-26
**Source feedback doc:** Curt's playtest notes (see chat).
**Scope:** Map each reported symptom to a concrete root cause in the codebase. Curt's feedback is *signal*, not specification — several of his symptoms collapse into a smaller set of structural defects, and a few hint at issues the playtest didn't fully surface. Where the diagnosis differs from his hypothesis, that is called out explicitly.
**Companion docs:**
- `runtime-logic.md` (sibling of `Adaptation layer/`) — what the runtime actually feeds the narrator per turn.
- `adaptation-analysis.md` / `adaptation-3-analysis.md` — pre/post pipeline-fix state.
- `branch-dimensions-pollution-bug.md` — Phase 5 enrichment defect.
- `fix-event-position-drift-process-log.md` — story-global ordinal fix.

---

## 0. Executive summary

Curt's testable symptoms reduce to **three structural defects** plus one local-environment incidental:

| # | Defect | Affects Curt symptoms |
|---|---|---|
| **A** | The narrator agent receives the **full screenplay text** of `current_event` on every turn while only seeing the last 6 turns of stripped chat history. There is no "what I already narrated" memory. | #1, #2 |
| **B** | The runtime persists **zero structured player state**. Columns `branching_choices_taken`, `tracked_dimensions`, `branch_resolution_log`, `current_beat_type` exist on `games` but **nothing writes to them**. The narrator's structured output schema has no field for choice classification, mapped option, or tracked dimension. | #3, #4, partly #5 |
| **C** | Choice generation has a baked-in convergence/leading bias (mandatory "wait and observe" path, forward-momentum gradient, "steer back to canon" rule). Opening narration has a separate, design-level defect: **it does not consume `entry_point_diagnosis.cold_open` when one is present** — `StoryOpeningGeneratorJob` runs at story-creation time, before the adaptation pipeline produces the cold open, and the opening is never regenerated/replaced afterwards. So players see a generic preamble even when the adaptation has authored a story-tone-correct cold open. | #5, #6 |

\#7 (TTS) is **verified working** — TTS infra is functional; Curt's complaint is treated as a local-environment / one-off incident, not a code defect. See §7 for the disposition.
\#8 (Alice surrealism masks failures) is a meta-observation that becomes the **diagnostic methodology** — see §8.

---

## 1. "Story appears to move backward"

**Curt symptom:** Player chose to dive into the hole, then call out to rabbit; response returned to hedge/hole setup and re-explained entry.

### 1.1 What Curt's hypothesis got right
- Suspecting `current_event_id` advancement is correct in spirit. But the post-fix data (`adaptation-3-analysis.md` §3) confirms that for Alice, `start_event_id = 1` and event ranges tile `1..90` cleanly. The advancement *path* is structurally correct now.
- Suspecting fallback resetting to earlier events: there are no longer any silent fallbacks after `fix-event-position-drift` removed them.

### 1.2 The actual mechanism

It isn't that `current_event_id` rewinds. It's that the **narrator re-narrates the same event** when `advance_event = false`. The event content stays in the system prompt verbatim:

```52:60:resources/views/ai/agents/narration/system-prompt.blade.php
--- CURRENT EVENT ---
<Event position="{{ $currentEvent['position'] }}" title="{{ $currentEvent['title'] }}">
Text: {{ $currentEvent['content'] }}
@if(!empty($currentEvent['objectives']))
Objectives: {{ $currentEvent['objectives'] }}
@endif
@if(!empty($currentEvent['attributes']))
Attributes: {{ json_encode($currentEvent['attributes']) }}
@endif
</Event>
```

This block is rendered every turn while `current_event_id` doesn't change. The `event['content']` for "Alice notices the White Rabbit" includes the full hedge → watch → rabbit-hole edge screenplay material. On every turn within that event, the narrator sees that same text and is asked to "render the CURRENT_EVENT as an interactive scene".

The *anti-rehash* rules exist but are pure prompt directives:

```98:113:resources/views/ai/agents/narration/system-prompt.blade.php
=== EVENT PROGRESSION DISCIPLINE ===
- The screenplay content of the CURRENT_EVENT is narrated ONLY ONCE.
- The FIRST response in an event may narrate the screenplay (converted into cinematic prose) up to the first natural player decision point.

AFTER THE FIRST RESPONSE IN THE SAME EVENT:
- STOP narrating the event script entirely.
- DO NOT repeat or paraphrase screenplay lines.
- DO NOT copy or rephrase your own prior narration.
- DO NOT reset, rewind, or restart the scene.
```

The model is expected to honor these rules using **only** the conversation transcript (last 6 turns, narrator HTML stripped) as evidence of "what I already wrote":

```189:211:app/Http/Controllers/User/Game/PromptController.php
private function buildConversationHistory(Game $game): array
{
    $history = [];

    $prompts = $game->prompts()
        ->latest()
        ->limit(6)
        ->get()
        ->reverse();

    foreach ($prompts as $p) {
        if ($p->response) {
            $history[] = ['role' => 'narrator', 'text' => strip_tags($p->response)];
        }
        if ($p->prompt && $p->prompt !== '__continue__') {
            $history[] = ['role' => 'player', 'text' => $p->prompt];
        } elseif ($p->prompt === '__continue__') {
            $history[] = ['role' => 'player', 'text' => 'Continue forward'];
        }
    }

    return $history;
}
```

At temperature `0.85` (`NarrationAgent` line 17), with the screenplay text always present and only flat strip-tagged history as a counterweight, drift back into rehashing is the expected failure mode. The "story moves backward" experience is the model re-narrating the screenplay setup because the screenplay setup is *literally in its current-turn instructions*.

### 1.3 Secondary contributor: session-boundary cold-open re-narration

When the next event crosses a session boundary, `applySessionTransitionCut` rewrites the next event id to the adaptation's `start_event_id`:

```89:112:app/Http/Controllers/User/Game/PromptController.php
private function applySessionTransitionCut(Event $nextEvent, Game $game): Event
{
    $nextSessionAdaptation = SessionAdaptation::query()
        ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $game->story_id))
        ->where('session_number', $nextEvent->session_number)
        ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
        ->first();

    $startEventId = $nextSessionAdaptation?->entry_point_diagnosis['start_event_id'] ?? null;

    if ($startEventId === null) {
        return $nextEvent;
    }

    $cutAdjusted = Event::find($startEventId);

    if ($cutAdjusted
        && $cutAdjusted->session_number === $nextEvent->session_number
        && $cutAdjusted->chapter->story_id === $game->story_id) {
        return $cutAdjusted;
    }

    return $nextEvent;
}
```

If two adjacent sessions' designed entry points overlap canonically (this is the documented Alice S1↔S2 issue — see `adaptation-analysis.md` §4.2: "S2 cold open replays S1's fall"), then on the S1→S2 transition the runtime hands the narrator a session that starts on material the player already played. Combined with `isSessionStart = true` re-injecting the cold-open guidance block, the narrator will re-dramatize the fall.

```233:248:resources/views/ai/agents/narration/system-prompt.blade.php
@if(!empty($isSessionStart) && !empty($sessionAdaptation->entry_point_diagnosis))
@php $entryPoint = $sessionAdaptation->entry_point_diagnosis; @endphp
--- SESSION COLD OPEN GUIDANCE ---
This is the OPENING of this session. The following cold open defines the tone, sensory texture, and emotional direction for your first response. Use it as your creative brief --- match its energy, pacing, and atmospheric intent --- but generate your own narration in your voice and HTML format. Do not copy it verbatim.

COLD OPEN DIRECTION:
{{ $entryPoint['cold_open'] ?? '' }}

EMOTIONAL PROMISE: {{ $entryPoint['emotional_promise'] ?? '' }}
```

So Curt's "moved backward" can be either of two distinct mechanisms:
1. Same event, narrator re-narrating screenplay text it already covered (most likely for hedge/hole).
2. Session boundary, adaptation cold-open replays material already played (Alice S1→S2 specifically).

### 1.4 Where to inspect

- Read `prompts` rows for the affected game in chronological order. Watch `event_id` per row. If it's stable across the "rewind", root cause is §1.2. If it changes to a `start_event_id` from a different session, root cause is §1.3.
- For each row, render the system prompt the agent would have seen and verify `currentEvent.content` is the same as the previous turn — that confirms §1.2.

---

## 2. "AI rehashes already-covered events"

**Curt symptom:** Responses repeat things already covered.

This is the same root cause as §1, restated from a different angle.

### 2.1 The information the model has about "what's already happened"

Two channels, neither sufficient:

1. **`previousEvents` block** — only `position`, `title`, `objectives` of up to 3 prior events. **Never the actual narrated text.** No record of *how* prior scenes were dramatized.

```219:251:app/Http/Controllers/User/Game/PromptController.php
private function getPreviousEvents(Event $currentEvent, int $take = 3): array
{
    $events = Event::query()
        ->where('chapter_id', $currentEvent->chapter_id)
        ->where('position', '<', $currentEvent->position)
        ->orderByDesc('position')
        ->take($take)
        ->get();

    if ($events->count() < $take) {
        $remaining = $take - $events->count();

        $prevChapter = Chapter::query()
            ->where('story_id', $currentEvent->chapter->story_id)
            ->where('position', '<', $currentEvent->chapter->position)
            ->orderByDesc('position')
            ->first();

        if ($prevChapter) {
            $events = $events->merge(
                $prevChapter->events()->orderByDesc('position')->take($remaining)->get()
            );
        }
    }

    return $events->sortBy('position')
        ->map(fn (Event $event): array => [
            'position' => $event->position,
            'title' => $event->title,
            'objectives' => $event->objectives,
        ])
        ->values()
        ->all();
}
```

2. **Last 6 prompts in `prompts.response`** — narrator HTML stripped to plain text. If the player has been on the same event for 4 turns, the "history" is essentially the model's own previous narration about *this same event* — which is fine for not literally copy-pasting itself, but doesn't inform "what beats have already been covered" once the event boundary is crossed (because previous-event narration drops out of the 6-turn window).

### 2.2 The screenplay never rotates out

The system prompt sends the **complete `currentEvent.content`** every turn. For Alice's "Alice notices the White Rabbit" event, that includes everything from the bank-side scene through the rabbit-hole edge. Each turn is essentially a fresh prompt with the screenplay material intact, and the rules say "narrate ONLY ONCE" — but the model has no reliable evidence (other than 6 stripped chat turns) of having narrated it. With 4 turns inside an event before force-advance kicks in, the rehash window is wide:

```55:59:app/Http/Controllers/User/Game/PromptController.php
$shouldAdvance = $aiResult['advance_event'];

if (! $shouldAdvance && $turnCount >= 5) {
    $shouldAdvance = true;
}
```

### 2.3 The "first turn" marker is implicit

The system prompt's first-response rule is: "The FIRST response in an event may narrate the screenplay; AFTER, STOP". The runtime exposes `turnCount` (count of `prompts` already on this event):

```216:225:resources/views/ai/agents/narration/system-prompt.blade.php
@if(!empty($turnCount))
This is turn {{ $turnCount }} in this event.
@if($turnCount == 2)
PACING: ...
```

But the FIRST turn's `turnCount` is `0` (or omitted — the `@if(!empty($turnCount))` guard never fires for `0`). The model doesn't get an explicit "this is turn 1, you may narrate the screenplay" or "this is turn 2+, stop narrating the screenplay". It must infer that boundary from the conversation history's emptiness or fullness.

### 2.4 Where to inspect

- Compare turn N's response against turn N-1's response, both stripped. If they share screenplay-derived sentences, this is §2.2.
- Verify whether the system prompt for turn N+1 still includes the original `event.content` despite history showing the scene was already dramatized.

---

## 3. "Player progress not preserved"

**Curt symptom:** Objects acquired, actions taken, progress achieved are forgotten.

This is the most consequential structural finding. **Nothing in runtime persists beyond the chat log.**

### 3.1 The state columns exist but are never written

`games` table columns (created by `database/migrations/2026_04_15_000004_add_adaptation_state_to_games_table.php`):

| Column | Cast | Purpose (per design docs) |
|---|---|---|
| `current_session_number` | int | Cache of current session |
| `current_beat_type` | string | Setup/escalation/breath/twist/resolution |
| `branching_choices_taken` | json | choice_id → A/B/C |
| `tracked_dimensions` | json | dimension_name → current path |
| `branch_resolution_log` | json | every input classification record |

Eloquent casts confirmed:

```42:46:app/Models/Game.php
return [
    'branching_choices_taken' => 'json',
    'tracked_dimensions' => 'json',
    'branch_resolution_log' => 'json',
];
```

Repository search for any write to these columns:

```
$ rg "branching_choices_taken\s*=>|tracked_dimensions\s*=>|branch_resolution_log\s*=>|current_beat_type\s*=>" --type=php
```

Returns **only**:
- The migration that created them.
- The model's `casts()` declaration.
- `GameController::reset()` setting all four to `null`.

```76:83:app/Http/Controllers/User/GameController.php
$game->update([
    'current_event_id' => $startEvent->id,
    'current_session_number' => null,
    'current_beat_type' => null,
    'branching_choices_taken' => null,
    'tracked_dimensions' => null,
    'branch_resolution_log' => null,
]);
```

`current_session_number` is the **only** state field the runtime updates outside of reset, and only on session transitions:

```67:74:app/Http/Controllers/User/Game/PromptController.php
if ($nextEvent->session_number !== null
    && $nextEvent->session_number !== $currentEvent->session_number) {
    $nextEvent = $this->applySessionTransitionCut($nextEvent, $game);
    $gameUpdate['current_event_id'] = $nextEvent->id;
    $gameUpdate['current_session_number'] = $nextEvent->session_number;
}

$game->update($gameUpdate);
```

**Net effect:** all four player-state columns sit at `null` for the entire game. The runtime has no notion of inventory, of which authored A/B/C the player chose, of any tracked dimension, of any emergent action that should echo later.

### 3.2 The narrator's schema has no slots for state

```43:69:app/Ai/Agents/NarrationAgent.php
return [
    'response' => $schema->string()->required()...,
    'choices' => $schema->array()->required()...,
    'advance_event' => $schema->boolean()->required()...,
];
```

There is no field for `input_classification` (expressive / branch_aligned / emergent / unsupported), no `mapped_choice_id`, no `tracked_dimension`, no `state_delta` (objects acquired, conditions changed). The `system-prompt.blade.php` instructs the model to perform that classification (`§ ADAPTATION BEHAVIORAL RULES item 4`), but since the schema can't carry it back, **the runtime cannot persist any of it**.

### 3.3 What the player "remembers" today

The only memory of player progress is:
- `prompts.prompt` rows (raw text history, used in the next 6 turns of conversation).
- `prompts.response` rows (the narrator's own descriptions, HTML-stripped into the conversation history).

Once a turn falls outside the last-6 window, the runtime has no record of it that is visible to the next narrator call. Inventory, conditions, social standing, dimension trajectory — all of it disappears past 6 turns.

This is exactly Curt's symptom. Not a bug in a feature, but **the absence of the feature**.

### 3.4 What's missing at minimum

To deliver the experience the design docs describe (`Adaptation layer/change-logic.md` §"Runtime state on games"), the runtime needs:

1. Schema additions on `NarrationAgent` for at least: `input_classification`, `mapped_choice_id` (nullable), `state_delta` (object).
2. `PromptController::store` to persist the agent's classification + delta into `branching_choices_taken`, `tracked_dimensions`, and `branch_resolution_log`.
3. The system prompt to surface current state on each turn (currently it doesn't — see `runtime-logic.md` §4.1, `tracked_dimensions` is *not* in the variables passed to the view).

This is a feature build, not a bug fix. Worth quoting separately in the work plan.

### 3.5 Where to inspect

```sql
SELECT id, current_session_number, current_beat_type,
       branching_choices_taken, tracked_dimensions, branch_resolution_log
FROM games
WHERE story_id = (SELECT id FROM stories WHERE slug='alices-adventures-in-wonderland')
ORDER BY updated_at DESC
LIMIT 5;
```
Expectation today: every state column except `current_session_number` is `null` for every row.

---

## 4. "Player cannot move story forward"

**Curt symptom:** Even direct commands feel like they don't progress.

### 4.1 Forward motion is gated entirely on one boolean

`advance_event` is the sole forward gate:

```55:76:app/Http/Controllers/User/Game/PromptController.php
$shouldAdvance = $aiResult['advance_event'];

if (! $shouldAdvance && $turnCount >= 5) {
    $shouldAdvance = true;
}

if ($shouldAdvance) {
    $nextEvent = $this->findNextEvent($currentEvent, $game->story_id);

    if ($nextEvent) {
        $gameUpdate = ['current_event_id' => $nextEvent->id];

        if ($nextEvent->session_number !== null
            && $nextEvent->session_number !== $currentEvent->session_number) {
            $nextEvent = $this->applySessionTransitionCut($nextEvent, $game);
            $gameUpdate['current_event_id'] = $nextEvent->id;
            $gameUpdate['current_session_number'] = $nextEvent->session_number;
        }

        $game->update($gameUpdate);
    }
}
```

The system prompt's instructions for when to set it false:

```208:214:resources/views/ai/agents/narration/system-prompt.blade.php
Set advance_event = FALSE only when:
- This is the FIRST response in the event (the opening narration).
- The player is actively mid-conversation with a character or examining something specific.
- A critical scene objective has not been started at all (not merely "unfinished" — unstarted).

Do NOT keep advance_event = FALSE just because optional or secondary beats remain unexplored.
Once the player has engaged with the scene's core purpose, lean toward advancement.
```

So the model holds `false` on opening, on mid-conversation, or on unstarted critical objectives. That's a wide set of plausible holds — and at temperature `0.85`, it's easy for "the player is examining something specific" to apply to almost any freeform input.

### 4.2 No mechanical link from "player input" to "this advanced a beat"

The runtime has no notion of:
- Which authored A/B/C the player picked (no comparison of `prompts.prompt` against `sessionAdaptation.session_choice_design.branching_choice_*.option_*.text`).
- Which tracked dimension this turn affects.
- Whether this is a "branch_aligned" input (advance) vs an "expressive" input (no advance) vs an "emergent" input (advance + log).

The narrator agent is asked in the system prompt to classify — but as noted in §3.2, the schema can't return the classification, so the runtime can never act on it. **The classification is purely advisory in-prompt.**

### 4.3 Force-advance is the only safety valve

`turnCount >= 5` forces `advance_event = true`. With first-turn at `turnCount=0`, that allows up to 4–5 turns of stalling per event before the runtime overrides the model. From a player's perspective, that means up to 4 turns of "you stand at the edge of the hedge" before they finally break through — which exactly matches Curt's "even direct commands feel like they don't progress".

### 4.4 The Continue button compounds it

Pressing "Continue" sends `__continue__`, which is rewritten to the literal string `"Continue forward"`:

```28:32:app/Http/Controllers/User/Game/PromptController.php
$prompt = $request->string('prompt')->toString();
$isContinue = $prompt === '__continue__';

$game->prompts()->latest()->first()?->update([
    'prompt' => $isContinue ? '__continue__' : $prompt,
]);
```

The model receives `PLAYER'S ACTION: Continue forward`. With no scene-state delta from the previous turn, this looks indistinguishable from a passive "wait" input — and the system prompt explicitly de-emphasizes wait-style options. So Continue *can* trigger more narration without forward motion, especially when the model has chosen to hold for a "specific examination".

### 4.5 Where to inspect

- Per-turn log of `advance_event` for a real game. If it's mostly `false` until turn 5 forces `true`, this is the pattern.
- Sample the model's narration to check whether it actually classified the player's freeform input — the system prompt asks for it but it's not returned anywhere.

---

## 5. "Choices feel leading"

**Curt symptom:** Last lines / prompts suggest a course of action.

This has **two compounding sources** and one runtime-level enabler.

### 5.1 Per-turn choices are authored to be ordered by forward momentum

```162:175:resources/views/ai/agents/narration/system-prompt.blade.php
=== CHOICES (DESIGN + ANTI-DUPLICATION) ===
Choices exist to keep the scene playable and guide momentum back to canon.

Rules:
- Each choice must be a SINGLE, concrete, machine-detectable intent (one action).
- Begin each choice with a strong verb.
- Do NOT repeat choices within the same event.
- Do NOT offer the same intent with different wording.
- Avoid passive options like "wait", "think", "observe" (especially after the first turn).

Convergence gradient (no spoilers):
- <1> Most forward-moving toward the next beat (within current scene terms).
- <2> Moderately forward-moving.
- <3> Least forward-moving but MUST still change the scene state (no stalling).
```

This explicitly orders choices by forward-momentum-toward-canon. The player reads "the first choice is the right one" because **structurally the first choice IS the one most aligned with where the scene wants to go**. By design.

```177:180:resources/views/ai/agents/narration/system-prompt.blade.php
If the Player is off-track, choices must gently steer back to canon:
- Offer at least one choice that directly re-engages the core scene objective.
- Never mention "canon", "event", "next event", or rules.
```

When the player is "off-track", choices steer back. So during freeform exploration, one of three choices will explicitly redirect to the canonical path. Curt's "suggesting a course of action" is the expected behavior of this rule.

### 5.2 Adaptation Phase 5 choices have a built-in "wait and observe"

```11:18:resources/views/ai/agents/adaptation/choice-design/system-prompt.blade.php
CHOICE WRITING RULES (apply to every choice):
1. Three options per choice. Always A, B, C.
2. Each option reflects a genuine human value — not a personality type, not a difficulty setting.
3. One option per choice must be the "wait and observe" path.
4. Written in second-person present tense.
5. Each option is one sentence. Declarative. No filler.
6. For BRANCHING choices: write the immediate downstream effect as one italic sentence after each option.
7. For EXPRESSIVE choices: write the narration/tonal effect after each option.
8. Branching Choice #2 (moral-weight) must have no objectively correct answer.
```

Rule 3 — "One option must be the wait-and-observe path" — guarantees that out of three options, one is structurally passive. The other two then look like *the* choices. This is the exact "leading" feel.

### 5.3 The canonical "third option" admits forced convergence

From `adaptation-analysis.md` §4.1, Session 1 Choice 2 (DRINK ME):
> Path C explicitly says *"finally does drink (or is forced into change)"* — collapses all three options back to the canonical shrink → key-unreachable beat.

Even when the AI generates three "branching" options, the runtime's editorial verification (Q4) caught all three completed Alice sessions failing this. The Phase 5 prompt allows narration *tone* to differ between options without forcing *mechanical state* to differ. So the player picks A, B, or C and ends up at the same place — which is the most rigorous definition of "leading".

### 5.4 Runtime cannot detect which authored option was picked

Even if Phase 5 wrote three genuinely divergent options, the runtime currently:
- Stores the player's selection as freeform text in `prompts.prompt`.
- Does not match it against `session_choice_design.branching_choice_*.option_*.text`.
- Does not record `choice_id` + selected letter in `branching_choices_taken`.
- Cannot trigger the per-option `consequence_map_choice_*` echoes (Phase 6) because the link between selection and consequence is never established.

So even the divergent options that *do* exist in the adaptation layer never produce divergent downstream consequences. From the player's perspective, every choice resolves to "more of the same" because the runtime has no machinery to make them resolve otherwise. Compound effect: **all choices feel leading because effectively only one path is real.**

### 5.5 Where to inspect

- Compare a captured `session_choice_design.branching_choice_1` against the `prompts.choices` rendered at the corresponding game moment. Are they the authored A/B/C, or generated-on-the-fly substitutes? (Likely the latter, because the system prompt only forces authored options "When the scene reaches a branching choice slot" — and the runtime never tells the model when it has reached a slot.)
- Inspect `branching_choices_taken` for any game (will be `null` per §3.1).

---

## 6. "Preamble feels stylistically wrong"

**Curt symptom:** Opening/preamble is disjointed, not Carroll-like, too narrow, too long, too leading.

### 6.1 The actual defect: the cold open isn't being used

The opening narration is a **two-section preamble** by design — Section 1 is the universal Lorespinner Interactive welcome (this is product branding and is intended to be near-identical across stories), and Section 2 is the story-specific cold open. The Section 1 / Section 2 split is correct intent.

The defect is in **how Section 2 (the story-specific intro) is sourced**:

```43:53:app/Jobs/Story/StoryOpeningGeneratorJob.php
$response = (new OpeningNarrationAgent)->prompt(
    view('ai.agents.opening-narration.prompt', [
        'title' => $this->story->title,
        'teaser' => $this->story->teaser,
        'characterName' => $storyData['character_name'] ?? null,
        'toneAndStyle' => $storyData['tone_and_style'] ?? null,
        'worldRules' => $storyData['world_rules'] ?? [],
        'firstChapterTitle' => $firstChapter?->title,
        'firstEventContent' => $firstEvent?->content,
    ])->render()
);
```

`StoryOpeningGeneratorJob` is dispatched **at story-creation time**, before the adaptation pipeline runs. At that moment there is no `SessionAdaptation` and no `entry_point_diagnosis.cold_open`. So the agent invents a Section 2 from `teaser` + `firstEventContent` + bullet hints — and the result is the disjointed, off-tone preamble Curt felt.

But the adaptation pipeline DOES author a story-specific cold open later. From `database/exports/adapptation-third-try.json` for Alice, Session 1:

```
"cold_open": "Heat shimmers off the river stones, and your stockings stick to the back of your knees as you lean over the grass, half-listening to your sister's page-turning.

Then a White Rabbit flashes past so close you catch the clean, sharp scent of crushed clover—and it mutters, plainly, like a person: 'Oh dear! Oh dear! I shall be late!'

You don't freeze. You lunge up, skirt snagging on a thistle, because the Rabbit has a waistcoat-pocket. Because it pulls out a watch and checks it with frantic dignity, as if being late could matter to a rabbit. ..."

"emotional_promise": "The emotional promise of this cold open is: pursuit. A user arrives feeling restless and wanting to chase the impossible before it vanishes."
```

This **is** the Carroll voice. Sensory detail, second-person, in-the-moment. It's exactly what Section 2 of the preamble should be sourced from — and it's already authored, sitting in `session_adaptations[session_number=1].entry_point_diagnosis.cold_open`. The opening narration just never reads from it.

Inside the in-game system prompt, the runtime DOES read the cold open (when `isSessionStart=true`) and uses it as creative direction:

```238:241:resources/views/ai/agents/narration/system-prompt.blade.php
COLD OPEN DIRECTION:
{{ $entryPoint['cold_open'] ?? '' }}

EMOTIONAL PROMISE: {{ $entryPoint['emotional_promise'] ?? '' }}
```

So the cold open is wired into the per-turn narrator (`GameController::generateFirstNarration` and `PromptController::renderSystemPrompt`), but **not** into the story-creation-time `StoryOpeningGeneratorJob`. That's the wiring gap.

### 6.2 Why the gap exists today

`StoryOpeningGeneratorJob` runs once per story, on creation. The adaptation pipeline runs separately and asynchronously, and may complete much later (or not at all). There's currently no callback / hook from "adaptation pipeline finished Session 1" → "regenerate opening using the new cold open". Result:

- New story → opening generated with no cold-open input → templated, generic Section 2.
- Adaptation completes later → cold open exists in DB but `stories.opening` is stale.
- Player launches the game → sees the stale, generic opening, then the in-game narrator (which DOES use the cold open) immediately delivers a Carroll-voiced first response. Tonal whiplash.

This matches Curt's "disjointed" precisely: the preamble doesn't sound like the first scene the game then narrates, because they were generated from different sources at different times.

### 6.3 Line-by-line reveal and width are downstream cosmetic concerns

The frontend treats the opening as a series of `<br>`-split segments:

```16:21:resources/js/components/GameOpeningNarration.vue
const segments = computed(() => {
    return props.opening
        .split(/<br\s*\/?>/gi)
        .map((s) => s.trim())
        .filter((s) => s.length > 0);
});
```

Each segment fades in one at a time:

```28:48:resources/js/components/GameOpeningNarration.vue
const revealNext = () => {
    if (visibleCount.value < segments.value.length) {
        visibleCount.value++;

        nextTick(() => {
            const container = document.querySelector('.opening-scroll-container');
            if (container) {
                container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
            }
        });

        const current = segments.value[visibleCount.value - 1] || '';
        const isEmpty = current === '';
        const delay = isEmpty ? 300 : Math.min(80 + current.length * 2, 250);
        timer = setTimeout(revealNext, delay);
    } else {
        setTimeout(() => {
            isComplete.value = true;
        }, 600);
    }
};
```

Combined with a 30-40 segment count and 5-15 word lines on a `max-w-2xl md:max-w-4xl` container, the visual experience is a tall ladder of short lines — consistent with "too narrow down the page". When Section 2 is sourced from the cold open (which is paragraph-prose rather than `<br>`-separated punchy lines), this scaffolding will need a small render adjustment so the cold open's paragraphs read as paragraphs, not as 30 atomic line-fades. Tracked as a follow-up in the fix plan; not the primary defect.

### 6.4 Where to inspect

```sql
SELECT id, slug, opening
FROM stories
WHERE slug = 'alices-adventures-in-wonderland';
```

```sql
SELECT sa.session_number,
       sa.entry_point_diagnosis->>'cold_open' AS cold_open,
       sa.entry_point_diagnosis->>'emotional_promise' AS emotional_promise
FROM session_adaptations sa
JOIN story_adaptations s ON s.id = sa.story_adaptation_id
WHERE s.story_id = (SELECT id FROM stories WHERE slug='alices-adventures-in-wonderland')
  AND sa.session_number = 1;
```

Expectation today: `stories.opening` is the generic Lorespinner welcome with an invented Section 2; `session_adaptations.entry_point_diagnosis.cold_open` exists and carries the Carroll-voiced opening that *should* be feeding Section 2. Two channels, one consumer.

---

## 7. "TTS icon/function broken" — disposition: NOT A DEFECT

**Verified working.** ElevenLabs TTS is functional in the current environment: backend `TextToSpeechController` returns `audio/mpeg` correctly, and the frontend `useTextToSpeech` composable plays it through `GameplayMediaPlayer.vue`. Curt's specific complaint is treated as a **local-environment / one-off incident** — possibly a transient ElevenLabs API hiccup, a stale browser audio cache, or a missed user-gesture autoplay block on his test device.

### 7.1 No code change needed

The wiring (controller → ElevenLabs API → cached `tts/{prompt}.mp3` → frontend `<Audio>`) is correct. We're keeping the existing implementation as-is.

### 7.2 Optional polish (not in scope of this fix cycle)

If TTS issues resurface in future playtests, two cheap improvements would help:
- Surface a toast on `audio.error` with a descriptive message (today the frontend silently sets `isPlaying = false` on error — `useTextToSpeech.ts` lines 53-57).
- Add a backend feature probe (or a `voiceAvailable` boolean on `GameResource`) so the Play icon can be hidden when `ELEVENLABS_API_KEY` is unset.

Neither is required to address Curt's playtest. Filed as future polish.

---

## 8. "Alice surrealism masks failures"

This is methodological, not a bug. Curt's right that for *playtesting feel* the surrealism makes drift hard to perceive. The diagnostic implication is: **for debugging, instrument explicitly rather than relying on the player's read.**

### 8.1 Recommended instrumentation (none of which exists today)

For each player turn, log:

| Field | Where to source | Use |
|---|---|---|
| `event_id_before` | `Game::currentEvent->id` pre-call | Detect rewind |
| `event_id_after` | `Game::currentEvent->id` post-call | Confirm advance |
| `advance_event_returned` | Agent response | Compare to actual advance (force-advance vs natural) |
| `force_advanced` | `turnCount >= 5` flag | Quantify stall rate |
| `turn_count` | Pre-call `prompts.where(event_id=current).count()` | Distribution of turns-per-event |
| `prompt_input` | `request->prompt` | Audit trail |
| `narrator_response_first_50_chars` | Agent response | Quick rehash detection |
| `system_prompt_hash` | `md5($systemPrompt)` | Detect identical-prompt repeats |
| `current_session_number` | `Game.current_session_number` | Confirm session transitions are clean |

A simple `Log::info('narration.turn', [...])` channel + a `php artisan game:trace {gameId}` command that prints the table chronologically would surface every issue in §1–§5 in seconds.

### 8.2 Hard validation rules

To Curt's point: stricter than player feeling. Per turn:

1. `event_id_after >= event_id_before` (no rewind).
2. If same `event_id`, narrator response shares < 30% n-gram overlap with prior response (no rehash).
3. If `advance_event = true` was returned, `event_id_after !== event_id_before`.
4. If session transition occurred, `current_session_number_after === nextEvent.session_number`.
5. If next event is a `start_event_id` from a session adaptation, that adaptation's `session_number` matches the new session.
6. Generated `choices` differ from the immediately previous turn's choices.

These should be assertions in test, not just lints. Even a single failing assertion in a smoke test would catch §1, §2 in CI.

---

## 9. Priority debug order — re-ordered by ROI

Curt proposed an order; this version re-prioritizes by impact-per-effort given the structural findings above.

| Priority | Item | Why first |
|---|---|---|
| **P0** | **Build state persistence** (§3) — schema fields on `NarrationAgent`, write-back into `branching_choices_taken` / `tracked_dimensions` / `branch_resolution_log` in `PromptController::store`. | Unblocks #3, #4, half of #5. Without this, every other "make choices matter" effort is cosmetic. |
| **P0** | **Add per-turn turn-trace logging** (§8). | Makes #1, #2, #4 instantly diagnosable. Cheap, high signal. |
| **P0** | **Mark turn-1 explicitly in the system prompt.** Pass an `isFirstTurnInEvent` boolean and a hard rule: turn 1 may narrate the screenplay; turn 2+ must not. Today the model infers this and gets it wrong. | Direct fix for #2's root cause. |
| **P1** | **Stop sending the full `currentEvent.content` after turn 1.** Replace with a structured "scene state" summary the model has been maintaining. | Direct fix for #1, #2. Bigger lift than P0 because it requires the model to emit a state delta. |
| **P1** | **Detect authored-choice selection** in `PromptController::store`. Compare `prompt` against `sessionAdaptation.session_choice_design.branching_choice_*.option_*.text`; record `choice_id` + selected letter. | Enables Phase 6 consequence delivery; directly addresses #5's "choices don't matter" feel. |
| **P1** | **Resolve Q4 alignment + branch-dimensions pollution** (`adaptation-3-analysis.md` §5.1, `branch-dimensions-pollution-bug.md`). | Required for #3, #4, #5 to deliver real divergence. Otherwise we wire up consequence delivery to broken design data. |
| **P1** | **Fix S1↔S2 cold-open overlap** (`adaptation-analysis.md` §4.2). Either advance S2's `start_event_position` past the fall, or treat S2 cold open as recap and advance immediately. | Direct fix for the secondary mechanism in #1 (§1.3). |
| **P1** | **Wire `entry_point_diagnosis.cold_open` into the opening narration** (§6). Pass Session 1's cold open + emotional promise into `StoryOpeningGeneratorJob`, and regenerate `stories.opening` whenever the adaptation pipeline completes (post-reconciliation hook). Section 1 (Lorespinner welcome) stays; Section 2 sources from the cold open verbatim or as direction. | Direct fix for #6. |
| **P2** | **Tighten `Phase 5` choice prompt**: forbid passive "wait and observe" as a mandatory option; require three mechanically distinct downstream effects. | Direct fix for #5's bias. |

Note the P0 items don't include any adaptation-pipeline work. The pipeline is currently producing usable artifacts (per `adaptation-3-analysis.md`); the bottleneck is on the **runtime side**, where designed material is silently ignored.

---

## 10. What this diagnosis is NOT claiming

For honesty about uncertainty:

- **Defect A** (§1, §2) is high confidence — the prompt + history wiring is unambiguous, and the symptom matches the mechanism exactly. But "the model occasionally rehashes despite the prompt rule" is also possible at temp 0.85. Need turn-trace logging to confirm vs. exclude.
- **Defect B** (§3, §4) is **certain**: code search proves no writes to those columns. No further investigation needed. **Empirically confirmed against Curt's actual game log** — see `curt-game-log-review.md` (sibling), which shows every state column on `games` is `null` after Curt's full session.
- **Defect C — opening** (§6): certain. `StoryOpeningGeneratorJob` does not pass `entry_point_diagnosis.cold_open` into the agent; verified by reading the job's view payload. The cold open is authored and stored, just unconsumed at story-creation time.
- **Defect C — choices** (§5): high confidence on the structural bias; lower confidence on whether *Curt's specific session* hit it. Could also be that Phase 5 produced genuinely divergent options for Alice and the runtime narrator just didn't render them — that's a runtime-layer mismatch, separate from prompt bias. Turn-trace logging will distinguish these.
- **Defect §1.3 (session-boundary cold-open)**: medium confidence for Alice specifically. Needs to be triggered by S1→S2 transition; if Curt didn't reach that transition, this isn't his actual symptom but is a latent bug worth fixing anyway.
- **TTS** (§7): verified working; treated as a non-defect for this fix cycle.

### 10.1 Defect surfaced *after* this diagnosis was written

`curt-game-log-review.md` (sibling) was a deeper pass on Curt's actual game log JSON, written **after** this diagnosis. It validated every defect above empirically *and* surfaced a new one that this diagnosis did not catch:

- **Defect D — `Game::prompts()` ordering collision (highest impact, fix planned as WS-0 in `curt-feedback-fix.md`).** The `Game::prompts()` relation chained `->oldest()`. Call sites then chained `->latest()->first()` and `->latest()->limit(6)`. Eloquent stacks ORDER BY clauses (it does not replace), so the SQL became `ORDER BY created_at ASC, created_at DESC` and the DB honored the first clause — every `latest()` call was returning the **oldest** row. Effect: the player's input was being written onto turn-1's prompt every turn (overwriting itself), and the narrator's "last 6 prompts" conversation history window never advanced past the first 6 prompts of the entire game. This single defect is sufficient to explain a large share of Curt's #1, #2, #3, and #4 symptoms on its own, independently of Defects A and B. The fix is applied locally on this branch and verified at the SQL level; see `curt-feedback-fix.md` §0.5 (WS-0) for the audit, edits, and SQL evidence.

This addition does not invalidate any earlier finding; Defects A, B, C are still real. But because Defect D corrupts the data window every other defect is diagnosed against, it must ship first — otherwise the trace logs proposed for diagnosing Defect A will themselves be sampled against poisoned conversation history.

---

## 11. Appendix — files referenced

| Concern | Path |
|---|---|
| First-turn narration | `app/Http/Controllers/User/GameController.php` |
| Per-turn narration & advance logic | `app/Http/Controllers/User/Game/PromptController.php` |
| Game state model | `app/Models/Game.php` |
| Game state migration | `database/migrations/2026_04_15_000004_add_adaptation_state_to_games_table.php` |
| Narrator agent | `app/Ai/Agents/NarrationAgent.php` |
| Narrator system prompt | `resources/views/ai/agents/narration/system-prompt.blade.php` |
| Narrator user-message prompt | `resources/views/ai/agents/narration/prompt.blade.php` |
| Opening narration job | `app/Jobs/Story/StoryOpeningGeneratorJob.php` |
| Opening narration agent | `app/Ai/Agents/OpeningNarrationAgent.php` |
| Opening narration prompts | `resources/views/ai/agents/opening-narration/system-prompt.blade.php`, `.../prompt.blade.php` |
| Opening narration UI | `resources/js/components/GameOpeningNarration.vue` |
| Gameplay UI | `resources/js/pages/User/Games/Show.vue`, `resources/js/layouts/GameplayLayout.vue`, `resources/js/components/GameplayChatCard.vue` |
| TTS backend | `app/Http/Controllers/User/Game/TextToSpeechController.php` |
| TTS frontend | `resources/js/composables/useTextToSpeech.ts`, `resources/js/components/GameplayMediaPlayer.vue` |
| Choice-design pipeline | `app/Jobs/Adaptation/ChoiceDesignJob.php` |
| Choice-design prompt | `resources/views/ai/agents/adaptation/choice-design/system-prompt.blade.php` |
| Session transition cut helper | `app/Http/Controllers/User/Game/PromptController.php` (`applySessionTransitionCut`) |
| Session adaptation model | `app/Models/SessionAdaptation.php` |

---

*End of diagnosis. Cross-link this doc back to `runtime-logic.md` (sibling of `Adaptation layer/`) for the per-turn data flow it builds on.*
