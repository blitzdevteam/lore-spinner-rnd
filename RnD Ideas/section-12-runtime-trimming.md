# Section 12 Runtime Trimming — Eliminating History/Source Overlap

## The Problem

Section 12 is the full session source script — all events for the session with their complete body text, baked into the cached `runtime_narrator_prompt` at assembly time. It's static. It never changes across turns.

As a session progresses, the context window looks like this by turn 15:

```
[Section 12 — Events 11–18, full body]        ← STATIC, all 8 events always present
[Turn 1 narration: Event 11 delivered]
[Turn 2 player choice + bridge narration]
[Turn 3 narration: Event 12 delivered]
...
[Turn 15 narration: Event 14 in progress]
```

By turn 15, Events 11–13 exist in **two places**: Section 12 (source text) and the conversation history (narrated version). This is pure overlap. The AI is paying attention to three completed events it no longer needs in Section 12, while that space could hold more upcoming source material — or just not be there at all.

**Concrete scale:** For Anima S1, that's up to 15 events at full body (~50k chars of screenplay) that become progressively irrelevant as the session advances.

---

## Does the AI Currently Know Which Event It's At?

**No.** Nothing in the current system tracks this.

What exists:
- `session_memory_update` — one sentence per turn summarising the turn (legacy log, not event-indexed)
- `symbolic_memory_update` — cumulative interior-weather paragraph, not event-indexed
- `state_delta` — world state (location, items, NPC dispositions, flags) — not event-indexed
- Conversation history — raw turn dialogue, not keyed to source events

What does not exist:
- Any field that says "the agent is currently narrating Event ID 47" or "Events 11–13 are complete"

The agent has the Section 12 event text in context, so it implicitly knows which event it's narrating — but it never emits that knowledge back to the backend.

---

## Best Absolute Approach

A two-phase implementation: a schema signal first, then dynamic injection using it.

### Phase 1 — Teach the Agent to Report Its Position (minimal schema change)

Add one nullable integer field to `ChaosNarrationSchema`:

```php
'current_event_position' => $schema
    ->integer()
    ->nullable()
    ->title('Current Event Position')
    ->description(
        'The story-wide position number of the source event from Section 12 that this ' .
        'turn is currently narrating or has just completed. Use the position number shown ' .
        'in the Section 12 header (CHAPTER X / EVENT Y). Output null if between events, ' .
        'in freeform narration not tied to a specific source event, or on turns where ' .
        'the source event is ambiguous.'
    ),
```

Why `current_event_position` not `current_event_id`:
- The agent sees the Section 12 header as `--- CHAPTER 2 / EVENT 11: Title ---`
- Event position is directly visible in the prompt — no DB lookup needed by the agent
- The backend can resolve position → event DB id using the same `orderedIds` query already in `RuntimeNarratorTemplateBuilder`

Store it in `chaos_sessions`:
- Option A: add `current_event_position` column to `chaos_sessions` table — cleanest
- Option B: store in `world_state` JSON as `_system.current_event_position` — no migration needed, slightly messier

On each turn, `ChaosEngineService::callAgent()` already returns the structured response. Persist `current_event_position` there alongside `session_memory_update`, `symbolic_memory_update`, etc.

---

### Phase 2 — Dynamic Section 12 Trimming at Render Time (no template change)

The key insight: **don't touch the cached prompt or the blade template**. Instead, trim Section 12 as a post-processing pass inside `renderSystemPrompt()` before injection.

Section 12 events render with a predictable format:
```
--- CHAPTER 2 / EVENT 11: Nora enters the sync initiation suite ---
OBJECTIVE: ...
{event body text}
```

The backend knows:
- Which event position is current (`current_event_position` from world state)
- The story-global ordering of events (the same `orderedIds` query)
- Therefore: which events in the cached Section 12 block are already narrated

`renderSystemPrompt()` calls a new private method `trimNarratedEventsFromSection12()` that:
1. Finds Section 12 in the cached prompt by its heading (`### SECTION 12:`)
2. Parses each `--- CHAPTER X / EVENT Y: ---` block header
3. Drops blocks where story position < `current_event_position`
4. Reassembles the trimmed Section 12 back into the prompt string
5. Proceeds with normal injection

**This requires:**
- No template change
- No assembly change
- No re-adapt
- One new field in `ChaosNarrationSchema`
- One new column in `chaos_sessions` (or one new key in world_state JSON)
- One new private method in `ChaosEngineService`
- One additional call in `renderSystemPrompt()`

---

## What This Looks Like in Practice

For Anima S1, start_event_position=18 (Ch2/Ev11 = story position 18):

| Turn | current_event_position (agent emits) | Section 12 shown to AI |
|------|--------------------------------------|------------------------|
| 1 | null (opening, not yet narrating) | Events 11–18 (all 8, full body) |
| 3 | 18 (Event 11 delivered) | Events 12–18 (7 events) |
| 6 | 19 (Event 12 delivered) | Events 13–18 (6 events) |
| 10 | 21 | Events 14–18 (5 events) |
| ... | ... | shrinks each time an event completes |
| N | 25 (Event 18, session close) | Event 18 only |

By the end of the session, Section 12 is one event instead of eight. The growing conversation history is offset by the shrinking Section 12. Total context stays relatively flat instead of growing unbounded.

---

## Minimal Work Alternative (No Schema Change)

If adding `current_event_position` to the narration schema feels too risky for a given sprint, there's a zero-schema-change fallback:

**Session memory title matching:**

The `session_memory_update` field emits a one-sentence summary each turn. Event titles are short and distinctive (e.g. "Nora enters the sync initiation suite"). A lightweight fuzzy match between accumulated session memory text and event titles can estimate which events have been narrated.

```php
// Pseudo-logic in renderSystemPrompt():
$narratedEventIds = $this->estimateNarratedEvents(
    $sessionContext['session_memory_updates'], // accumulated text
    $sessionContext['full_session_events']     // event id+title pairs
);
// Strip narrated events from Section 12 cache
$prompt = $this->trimNarratedEventsFromSection12($prompt, $narratedEventIds);
```

Pros: Zero schema change, works immediately  
Cons: Fuzzy — can mismatch if the session memory summary doesn't echo event titles. Safe to implement conservatively (only trim an event when confidence is high — e.g. title appears verbatim in accumulated memory).

---

## Implementation Order

| Step | What | Effort | Gain |
|------|------|--------|------|
| 1 | Add `current_event_position` to `ChaosNarrationSchema` + persist it | Small — one field, one DB write | Establishes the position signal |
| 2 | Add `trimNarratedEventsFromSection12()` to `ChaosEngineService` | Medium — string parsing of Section 12 blocks | Prompt grows flat instead of unbounded |
| 3 | Wire step 2 into `renderSystemPrompt()` using signal from step 1 | Small — one conditional call | Feature is live |
| 4 | Add `chaos_sessions.current_event_position` migration | Small | Clean persistence vs JSON hack |

Total: one sprint. Steps 1–3 can share a PR.

---

## Why Not Move Section 12 to a Full Runtime Injection Point

The alternative approach — removing Section 12 from the cached prompt entirely and injecting it as a `[SESSION_EVENTS_INJECTION_POINT]` token at runtime — would work but has a significant cost:

- Every session for every story needs **re-assembly** (`RuntimeNarratorAssemblyJob`) to remove Section 12 from the cached blob and add the token
- The template blade needs updating
- Assembly validation needs updating
- It's a bigger blast radius for the same functional result

The string-trimming approach achieves the same runtime effect with no re-assembly, no template change, and no migration for existing cached prompts. Keep the cached prompt as the source of truth; trim it dynamically.

---

## Open Questions

1. **What counts as "event complete"?** An event in free-form narration doesn't end with a hard signal. The safest rule: trim event N when the agent has reported `current_event_position >= N+1`. Never trim the current event — only trim fully-past ones.

2. **What if the agent skips an event?** D10 entry point cuts may mean the player skips directly into a late event. `current_event_position` handles this correctly — only report the position you're actually narrating.

3. **What about freeform turns that don't map to any source event?** The field is nullable. `null` means "don't advance the trim pointer this turn." The last confirmed position is retained.

4. **What about the state scope haystack?** `scopePersistentState()` uses event content to decide which NPCs/objects are "active." It should always run on the full session event list, not the trimmed display list. This is already architected correctly — `$allSessionEvents` (full list) is passed separately to `render()` for scope purposes.
