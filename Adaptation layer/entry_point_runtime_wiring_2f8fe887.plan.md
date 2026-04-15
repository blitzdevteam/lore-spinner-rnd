---
name: Entry Point Runtime Wiring
overview: Wire the Phase 3 Entry Point Diagnosis into runtime so games structurally start at the right dramatic event, not event 1. The core fix is resolving and storing a concrete start_event_id during Phase 3, then using it in CreateGameAction and generateFirstNarration() to override the default flow.
todos:
  - id: schema-and-prompt
    content: Add start_event_position to EntryPointDiagnosisAgent schema. Update Phase 3 prompt to include the session's events list so the AI can reference concrete positions.
    status: completed
  - id: job-resolve-id
    content: "In EntryPointDiagnosisJob: after AI returns start_event_position, resolve it to an actual start_event_id by querying events for this session. Validate it falls within the session range. Store both start_event_position and start_event_id in the entry_point_diagnosis JSON."
    status: completed
  - id: create-game-override
    content: "Modify CreateGameAction: when a completed SessionAdaptation for Session 1 exists with a start_event_id, use that event instead of the first event. Null-safe fallback to current behavior."
    status: completed
  - id: generate-first-narration
    content: "Modify GameController::generateFirstNarration(): when sessionAdaptation has entry_point_diagnosis, pass cold_open + must_reintroduce + emotional_promise + isSessionStart=true to the Blade template."
    status: completed
  - id: narrator-cold-open-block
    content: Add cold open injection block to narration system-prompt.blade.php inside the adaptation gate. Fires when isSessionStart is true. Injects cold_open as narrative foundation, emotional_promise, and must_reintroduce guidance.
    status: completed
  - id: session-transitions
    content: "In PromptController::store(): when findNextEvent() returns an event in a different session, look up the next session's start_event_id and skip to it. Set isSessionStart for the next narrator call."
    status: completed
  - id: process-log
    content: Update PROCESS_LOG.md to document the entry point runtime wiring.
    status: completed
isProject: false
---

# Entry Point Runtime Wiring

## The Problem

The adaptation pipeline computes a per-session Entry Point Diagnosis (Phase 3) that identifies where the dramatic energy begins and what passive setup to cut. But the runtime ignores it completely:

- `[CreateGameAction](app/Actions/Game/CreateGameAction.php)` always sets `current_event_id` to the very first event of chapter 1
- `[GameController::begin()](app/Http/Controllers/User/GameController.php)` narrates that first event with no awareness of the cut
- The [narration system prompt](resources/views/ai/agents/narration/system-prompt.blade.php) injects beat map / choices / consequences from adaptation, but never injects entry_point_diagnosis data (cold open, must-reintroduce, emotional promise)
- When the game crosses from Session N to Session N+1, no cut logic applies --- the next sequential event is used

Without a resolved event ID, the adaptation is just narrative guidance. With it, the system becomes structurally correct --- the game physically starts at the designed dramatic moment.

## Root Cause

The `entry_point_diagnosis` artifact stores a freeform string `cut_point` (e.g. "Chapter 2, paragraph 3") that cannot be programmatically resolved to an event. There is no numeric event position or event ID in the schema. Without a machine-readable start marker, no runtime code can act on the cut.

## The Fix: Resolve and store `start_event_id` during Phase 3

The core change is small but structurally critical: **during Phase 3 (or immediately after the AI returns), resolve the cut point to a concrete `start_event_id` and persist it inside `session_adaptations.entry_point_diagnosis`**. Everything else flows from this.

### Source of Truth Rule

`**start_event_id` is the runtime source of truth. `start_event_position` is an AI-facing helper and debug field only.** Every runtime code path (CreateGameAction, generateFirstNarration, PromptController session transitions) must trust the resolved ID directly via `Event::find()`. No runtime path should re-derive the cut from the textual `cut_point` or re-query by position. The position exists so the AI can reason about events during Phase 3 and so engineers can debug artifacts --- it is never consulted at runtime.

## Solution: Three Layers

### Layer 1 --- Schema + Pipeline (resolve the cut point to an event ID)

**Add `start_event_position` to the EntryPointDiagnosisAgent schema** so the AI outputs a concrete integer event position alongside the textual diagnosis.

- File: `[app/Ai/Agents/Adaptation/EntryPointDiagnosisAgent.php](app/Ai/Agents/Adaptation/EntryPointDiagnosisAgent.php)`
- Add to the schema array:

```php
'start_event_position' => $schema
    ->integer()
    ->required()
    ->title('Start Event Position')
    ->description('The integer event position number where this session should begin. All events before this position within the session are cut.'),
```

**Enhance the Phase 3 prompt** to include the session's event list so the AI can reference concrete positions.

- File: `[resources/views/ai/agents/adaptation/entry-point-diagnosis/prompt.blade.php](resources/views/ai/agents/adaptation/entry-point-diagnosis/prompt.blade.php)`
- Add events for this session with positions, titles, and objectives (same data StorySessionMapJob already loads)

**Resolve `start_event_id` in EntryPointDiagnosisJob** --- this is the critical step that makes the system structural, not just advisory.

- File: `[app/Jobs/Adaptation/EntryPointDiagnosisJob.php](app/Jobs/Adaptation/EntryPointDiagnosisJob.php)`
- After the AI returns `start_event_position`, query the events table to find the actual event:

```php
$result = $response->toArray();

// Resolve against story + session_number to prevent cross-story contamination
$sessionEvents = $this->story->events()
    ->where('events.session_number', $this->sessionNumber)
    ->orderBy('events.position')
    ->get(['events.id', 'events.position']);

if ($sessionEvents->isEmpty()) {
    throw new \RuntimeException(
        "No events found for story {$this->story->id} session {$this->sessionNumber}"
    );
}

$startPos = $result['start_event_position'] ?? null;
$startEvent = $sessionEvents->firstWhere('position', $startPos)
    ?? $sessionEvents->first();

$result['start_event_id'] = $startEvent->id;
$result['start_event_position'] = $startEvent->position;

$session->update(['entry_point_diagnosis' => $result]);
```

- Guards against empty session events before writing `start_event_id` --- if the session has no events (broken state), the job fails loudly rather than writing a null ID
- Resolves against `story + session_number` (via `$this->story->events()`), not just `session_number` alone --- prevents cross-story event contamination
- Stores a resolved `start_event_id` (database primary key) --- this is the runtime source of truth
- `start_event_position` is kept as a debug/AI-facing field only --- never consulted at runtime
- Falls back to the first event in the session if the AI's position is invalid
- No migration needed --- `entry_point_diagnosis` is already a JSON column

### Layer 2 --- Runtime Start (override the default flow)

**Modify `CreateGameAction`** --- when a completed adaptation exists, use the resolved `start_event_id` instead of the first event.

- File: `[app/Actions/Game/CreateGameAction.php](app/Actions/Game/CreateGameAction.php)`
- Current logic (line 15-18): always picks first chapter, first event

```php
$startEvent = $firstEvent; // default fallback

$adaptation = $story->adaptation;
if ($adaptation?->adaptation_status === AdaptationStatusEnum::COMPLETED) {
    $session1 = $adaptation->sessionAdaptations()
        ->where('session_number', 1)
        ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
        ->first();

    $startEventId = $session1?->entry_point_diagnosis['start_event_id'] ?? null;
    if ($startEventId !== null) {
        $resolved = Event::find($startEventId);

        if ($resolved
            && $resolved->chapter->story_id === $story->id
            && $resolved->session_number === 1) {
            $startEvent = $resolved;
        }
    }
}

return $user->games()->create([
    'story_id' => $story->id,
    'current_event_id' => $startEvent->id,
]);
```

Same guarded pattern used everywhere: resolve by ID, then verify it belongs to the expected story and session before trusting it. Falls back to `$firstEvent` if anything is wrong.

**Override `generateFirstNarration()` in GameController** --- when a completed SessionAdaptation has `entry_point_diagnosis`, pass the cold_open, must_reintroduce, emotional_promise, and `isSessionStart = true` to the Blade template.

- File: `[app/Http/Controllers/User/GameController.php](app/Http/Controllers/User/GameController.php)`
- The `$sessionAdaptation` query already exists in this method. Add the entry point data to the view variables:

```php
$systemPrompt = view('ai.agents.narration.system-prompt', [
    // ... existing variables ...
    'sessionAdaptation' => $sessionAdaptation,
    'isSessionStart' => true,
])->render();
```

### Layer 3 --- Narrator Awareness (cold open + cut material + session transitions)

**Add entry point injection block to the narration system prompt.**

- File: `[resources/views/ai/agents/narration/system-prompt.blade.php](resources/views/ai/agents/narration/system-prompt.blade.php)`
- Inside the existing adaptation gate (`@if(!empty($sessionAdaptation) && ...)`), add a conditional block that fires only on session start:

```blade
@if(!empty($isSessionStart) && !empty($sessionAdaptation->entry_point_diagnosis))
@php $entryPoint = $sessionAdaptation->entry_point_diagnosis; @endphp
--- SESSION COLD OPEN GUIDANCE ---
This is the OPENING of this session. The following cold open defines the tone, sensory texture, and emotional direction for your first response. Use it as your creative brief --- match its energy, pacing, and atmospheric intent --- but generate your own narration in your voice and HTML format. Do not copy it verbatim.

COLD OPEN DIRECTION:
{{ $entryPoint['cold_open'] ?? '' }}

EMOTIONAL PROMISE: {{ $entryPoint['emotional_promise'] ?? '' }}

@if(!empty($entryPoint['format_specific_cut']['must_reintroduce']))
CUT MATERIAL TO REINTRODUCE:
The following information was cut from before this starting point but is essential context. Weave it naturally into your narration through action, dialogue, or environmental detail --- never as exposition dump:
{{ $entryPoint['format_specific_cut']['must_reintroduce'] }}
@endif
@endif
```

**Handle session transitions in `PromptController::store()`:**

When `findNextEvent()` returns an event in a new session, look up the next session's resolved `start_event_id` and skip to it:

- File: `[app/Http/Controllers/User/Game/PromptController.php](app/Http/Controllers/User/Game/PromptController.php)`

```php
if ($nextEvent && $nextEvent->session_number !== null
    && $nextEvent->session_number !== $currentEvent->session_number) {

    $nextSessionAdaptation = SessionAdaptation::query()
        ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $game->story_id))
        ->where('session_number', $nextEvent->session_number)
        ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
        ->first();

    $startEventId = $nextSessionAdaptation?->entry_point_diagnosis['start_event_id'] ?? null;
    if ($startEventId !== null) {
        $cutAdjusted = Event::find($startEventId);

        // Verify the resolved event actually belongs to this story and target session
        if ($cutAdjusted
            && $cutAdjusted->session_number === $nextEvent->session_number
            && $cutAdjusted->chapter->story_id === $game->story_id) {
            $nextEvent = $cutAdjusted;
        }
    }

    // Update current_session_number in the same write path so runtime state
    // stays aligned with the cut-adjusted event. Consequence delivery in
    // Phase 6 depends on this being correct --- if it lags behind, tracked
    // dimensions and branching_choices_taken target the wrong session.
    $game->current_session_number = $nextEvent->session_number;
}
```

**Rule: `current_session_number` must be updated in the same write path as `current_event_id` whenever a session transition occurs.** The existing `$game->update(['current_event_id' => $nextEvent->id])` call that follows this block should include `current_session_number` in the same update. This ensures consequence delivery (Phase 6 maps keyed by session) and branch resolution (dimension tracking scoped to session) never operate against a stale session number.

Also determine `$isSessionStart` in `renderSystemPrompt()`. The check should compare the current event's ID against the session's `entry_point_diagnosis.start_event_id` --- not rely on turn count alone (turn count can be 0 for any new event, not just session starts):

```php
$isSessionStart = false;
if ($sessionAdaptation?->entry_point_diagnosis) {
    $isSessionStart = $currentEvent->id === ($sessionAdaptation->entry_point_diagnosis['start_event_id'] ?? null)
        && $turnCount === 0;
}
```

Both conditions must be true: the event is the session's designed start AND no turns have been played on it yet. Pass `$isSessionStart` to the Blade template. Default is `false`; cold open block is skipped when false.

## Session Transition Safety

When skipping from Session N's last event to Session N+1's `start_event_id`, the code must not bypass any persistence step tied to the old sequential boundary event. In the current codebase, `PromptController::store()` performs two persistence operations: (1) `$game->update(['current_event_id' => $nextEvent->id])` and (2) `$game->prompts()->create(...)` with the new event ID. Both of these happen AFTER `$nextEvent` is resolved. The cut-adjustment code replaces `$nextEvent` before either write, so no persistence step is skipped --- both writes target the correct (cut-adjusted) event.

If future work adds any side effect tied to visiting individual events (analytics, achievement tracking, progress logging), it must be aware that events between the session boundary and the `start_event_id` are never visited. The skipped events are architecturally cut --- they do not exist from the runtime's perspective.

## Backward Compatibility

- If `entry_point_diagnosis` is null or missing `start_event_id`: every code path falls back to current behavior (first event, no cold open, no session transition cut)
- Existing adapted stories without the new field: unaffected until re-adapted
- Unadapted stories: completely unaffected (no adaptation row, null-safe chain)
- `$isSessionStart` defaults to `false`; the cold open block is skipped when false

## Re-adaptation Required

Stories already adapted need to be re-run through the pipeline to populate `start_event_position` and `start_event_id`. The existing "Re-run Adaptation" button in Filament handles this. No special migration path needed.

## Files Changed Summary

- **Modified (pipeline --- resolve cut point to event ID):**
  - `app/Ai/Agents/Adaptation/EntryPointDiagnosisAgent.php` --- add `start_event_position` to schema
  - `resources/views/ai/agents/adaptation/entry-point-diagnosis/prompt.blade.php` --- include session events list
  - `app/Jobs/Adaptation/EntryPointDiagnosisJob.php` --- resolve `start_event_id` from position, validate, store both
- **Modified (runtime --- use the resolved event ID):**
  - `app/Actions/Game/CreateGameAction.php` --- override starting event via `start_event_id`
  - `app/Http/Controllers/User/GameController.php` --- pass cold_open + `isSessionStart` to narrator in `generateFirstNarration()`
  - `app/Http/Controllers/User/Game/PromptController.php` --- apply session transition cut via `start_event_id`, track `isSessionStart`
  - `resources/views/ai/agents/narration/system-prompt.blade.php` --- add cold open injection block
- **No new files, no migrations**

