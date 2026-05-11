# Writer Lab — Design Document

**Date:** 2026-05-11
**Updated:** 2026-05-11 — Editing scope is chapter-by-chapter (cross-chapter combines blocked in v1); no renumbering required on activate since runtime uses range comparisons (`position > X`), not sequential index lookups.
**Status:** Design phase — no implementation yet

---

## What Writer Lab Is

Writer Lab is an editorial workspace for curating the playable runtime version of a story.

It sits between the AI adaptation pipeline and the live player experience. It gives a writer — human or AI-assisted — the ability to reshape how the story is experienced interactively, while always being able to see the original source material alongside.

Writer Lab is **not** a metadata editor. It behaves like a narrative editing suite: sequence curation, pacing control, runtime shaping, and live preview — all in one place.

---

## The Chain of Truth

```
Script file (media, immutable — the original published text)
        ↓  extracted by pipeline (ChapterExtractorJob → EventExtractorJob)
Events table (processed layer — the current playable cut)
        ↓  read by runtime
PromptController → NarrationAgent → Player
```

The `events` table is already one interpretation of the source. It was created by the extraction pipeline, not copied verbatim from the script. Writer Lab makes a better interpretation of that layer.

The **script file** (stored in `story` media, readable via `Story::getScriptContent()`) is the permanent, untouched record of the original text. Even after Writer Lab edits the events table, the screenplay is never lost.

---

## Core Design Principle

> **Writer Lab edits the data the runtime reads. The runtime logic never changes.**

There is no new runtime concept, no new pointer, no new table the runtime queries. When a writer activates a version, it runs a **DB write operation directly against the `events` table and `session_adaptations` table**. The runtime wakes up the next turn and reads different data through exactly the same queries it has always used.

---

## What Writers Can Do

### Event Operations

| Operation | What it does |
|---|---|
| **Inspect** | Read the original screenplay events alongside extracted adaptation data |
| **Combine** | Select a range of events → run AI combine → produces one compact rewrite that replaces the range |
| **Split** | Divide one event into multiple runtime moments at natural break points |
| **Reorder** | Drag events to change `position` in draft |
| **Compress setup** | Mark early events for a faster-paced combined rewrite |
| **Extend dramatic moments** | Split one event into several to give a pivotal scene more breathing room |

### Choice Control

| Operation | What it does |
|---|---|
| **Remove choice point** | Mark an event as no-choice (narration flows naturally, no player input pause) |
| **Add choice point** | Force a player decision at a specific moment |
| **Modify choice options** | Edit `session_choice_design` — the authored A/B/C text |
| **Control pacing** | Adjust beat type on an event via `session_architecture.beat_map` |

### Adaptation Data

| Field | Where it lives | What writer can edit |
|---|---|---|
| Cold open text | `session_adaptations.entry_point_diagnosis.cold_open` | Yes — rewrite or approve AI version |
| Beat map | `session_adaptations.session_architecture.beat_map` | Yes — reassign beat types, timing |
| Authored choices | `session_adaptations.session_choice_design` | Yes — edit option text, choice question |
| Consequence map | `session_adaptations.choice_consequence_map` | Yes — adjust cross-session payoffs |
| Session close hook | `session_adaptations.session_close_design` | Yes — rewrite the ending hook |

---

## The Draft Layer

Writer Lab never touches the live events table directly during editing. All work happens in a **draft layer** — a staging area — until the writer explicitly activates.

```
Live events table (what runtime reads now)
        ↑  untouched during editing
Writer Lab Draft (writer_lab_drafts table)
  - source_event_ids[]     the original events this draft covers
  - rewritten_content      the compact rewrite (AI-generated, writer-approved)
  - derived_objectives     what canonically happened in this combined block
  - derived_attributes     objects, characters present
  - beat_type              editorial beat classification
  - requires_choice        bool — does this event pause for player input?
  - canonical_anchors[]    facts that must survive the rewrite (safety net)
  - previous_state         snapshot of original event rows (for rollback)
  - status                 draft | ai_written | writer_approved | activated
```

The writer can create unlimited drafts, run AI rewrites, compare versions, and preview live — without touching the events table.

---

## The AI Combine Button

When a writer selects an event range and triggers a combine, a **specialized LLM call fires — not the narrator**.

The narrator is a **performer**. The combiner is an **editor**. These are different jobs with different prompt designs.

### Combiner Input

```
source_events:      raw content + objectives + attributes of each event in range
style_profile:      from story.system_prompt (tone_and_style, character_name, world_rules)
canonical_anchors:  facts that must survive (derived from event.objectives + event.attributes)
compression_target: "1–3 paragraph compact scene at the pacing of the original author's voice"
```

The `canonical_anchors` field is the safety net. Before the combiner writes anything, it must declare which facts from the source events survive the compression. If the golden key is introduced in event 4, it will be in canonical anchors, and the rewrite is verified against it. Nothing gets lost silently.

### Combiner Output

```json
{
  "rewritten_content": "..compact prose scene..",
  "derived_objectives": "..what canonically happened in this combined block..",
  "derived_attributes": ["golden key", "White Rabbit", "small door"],
  "beat_type": "rising_action",
  "requires_choice": false,
  "canonical_anchors": ["Alice finds the golden key", "The bottle makes her smaller"],
  "absorbed_event_ids": [3, 4, 5]
}
```

This output mirrors what the existing adaptation pipeline produces per-event via `EventObjectiveAndAttributeExtractor`. The combiner is a variant of that job scoped to a range with compression intent added. No new AI infrastructure — a new prompt on the same pattern.

### After the Combiner Runs

- Writer sees a side-by-side view: **LEFT** = original 5 events (read-only source), **RIGHT** = AI compact rewrite (editable)
- Writer can edit the rewrite manually
- Writer approves it → draft status changes to `writer_approved`
- The narrator does not know 5 events were merged. It reads the compact rewrite as if it were a normal single event.

---

## The Activate Operation

When the writer activates a version, it runs a **DB write directly against the live tables**:

### Events table writes

1. Event 3's `content`, `objectives`, `attributes` → overwritten with the compact rewrite
2. Events 4 and 5 (absorbed) → deleted from the table
3. Events 6, 7, 8… → positions decremented to fill the gap
4. `session_number` on affected events updated if session boundaries changed

### Session adaptations writes

- `entry_point_diagnosis.cold_open` → updated if writer edited it
- `session_architecture.beat_map` → updated if beat types were reassigned
- `session_choice_design` → updated if choice options were modified
- `choice_consequence_map` → updated if consequence hooks were changed

### After activate

The runtime queries the events table with `findNextEvent()` (the exact same `WHERE position > current ORDER BY position` query it has always used) and gets the correct next event — because positions are now correct integers in the DB.

**Zero runtime logic changes. Ever.**

---

## Ordering Safety

**Writer Lab edits one chapter at a time.** The chapter is the unit of editorial work. The UI must reflect this — the chapter selector is the top-level navigation control, and all event operations (combine, split, reorder) are scoped strictly within the selected chapter. Cross-chapter operations are not supported in v1 and should be blocked in the UI.

The writer sees two columns:

| Left column | Right column |
|---|---|
| Source events in original screenplay order | Runtime events in playable order |
| Fixed, read-only, always matches script file | Editable via drag in Writer Lab draft |
| Source of truth for what Carroll wrote | Source of truth for what the player experiences |

Reordering in the right column changes `position` in the draft only. On activate, positions are written as contiguous integers (1, 2, 3…) with no gaps. `findNextEvent()` produces the correct sequence automatically.

---

## Rollback

Before any activate, the draft records the `previous_state` — a snapshot of the exact `content`, `objectives`, `attributes`, `position`, and `session_number` of every event that will be touched.

To restore: write `previous_state` back to the events table row by row. Re-insert deleted events. Re-increment positions.

Every version snapshot is kept permanently in the draft layer for history and audit.

---

## Live Preview

The same `NarrationAgent` that powers gameplay is used for preview in Writer Lab. No separate engine.

The writer selects a draft event (or combined block), and a **preview game** is created (a `Game` record flagged `is_preview: true`, with no real user session). It runs through `GameController::begin()` and `PromptController::store()` exactly as a player would.

The preview game reads from the **draft's `rewritten_content`** temporarily (passed directly to `renderSystemPrompt` as the `currentEvent.content` override). The live events table is not touched.

This means writers can test pacing, interaction placement, and narrative rhythm before committing to an activate.

---

## Versioning

Every writer-approved save creates a **version snapshot**:

```
writer_lab_versions
  - story_id
  - session_number
  - version_number
  - snapshot_events      json — full state of affected event rows at save time
  - snapshot_adaptation  json — full session_adaptation at save time
  - is_active
  - note
  - created_at
```

Writers can:
- Inspect previous versions
- Compare versions side by side
- Restore a previous version (runs the same activate write path with the snapshot data)

One version per session is `is_active = true`. That is the current runtime source.

---

## What Writer Lab Does NOT Change

| Component | Status |
|---|---|
| `PromptController::store()` | Unchanged |
| `PromptController::findNextEvent()` | Unchanged |
| `NarrationAgent` | Unchanged |
| `system-prompt.blade.php` | Unchanged |
| `GameController::begin()` | Unchanged |
| `world_state` persistence | Unchanged |
| `branching_choices_taken` | Unchanged |
| `game:trace` command | Unchanged |
| Narration log channel | Unchanged |

The entire runtime stack is read-only from Writer Lab's perspective. Only the data it reads changes.

---

## Data Flow Summary

```
Script file (media, immutable)
        ↓  pipeline extraction
events table + session_adaptations (current playable cut)
        ↓  Writer Lab reads this as source reference
Writer Lab Draft Layer (writer_lab_drafts, writer_lab_versions)
        ↓  AI combine button (specialized LLM, not narrator)
Compact rewrite + derived adaptation data
        ↓  writer reviews, edits, approves
        ↓  Activate: DB write to events + session_adaptations
events table + session_adaptations (updated playable cut)
        ↓  runtime reads this, unchanged logic
PromptController → NarrationAgent → Player
```

---

## New Tables Required

### `writer_lab_drafts`

```sql
id
story_id                    FK → stories
session_number              int
source_event_ids            json    -- array of event IDs this draft covers
rewritten_content           longtext
derived_objectives          text nullable
derived_attributes          json nullable
beat_type                   string nullable
requires_choice             bool default true
canonical_anchors           json nullable
previous_state              json    -- snapshot of original event rows for rollback
status                      enum: draft | ai_written | writer_approved | activated
activated_at                timestamp nullable
created_at, updated_at
```

### `writer_lab_versions`

```sql
id
story_id                    FK → stories
session_number              int
version_number              int
snapshot_events             json    -- full event rows as they were when saved
snapshot_adaptation         json    -- full session_adaptation as it was when saved
is_active                   bool default false
note                        string nullable
created_at
```

---

## Build Order

1. **Read-only view** — surface events + session adaptation JSON in a two-panel layout. No writes yet. This alone is useful for understanding the structure before editing.
2. **AI combine call** — specialized LLM produces `rewritten_content + derived_objectives + canonical_anchors`. Stored in `writer_lab_drafts`. Still zero runtime impact.
3. **Writer edit + approve** — editable right panel, approve button changes draft status.
4. **Live preview** — fire the same narrator against the draft's `rewritten_content` in a preview game.
5. **Versioning** — snapshot the approved draft set before each activate.
6. **Activate** — DB write to events + session_adaptations. The one step that materializes editorial work into the live runtime. Tested in isolation.

---

## Key Insight

> Writer Lab is a **DB editor for the events table** with a draft/staging layer and AI assist — not a runtime overlay. The runtime never changes because the data it reads changes instead.

The separation is clean:
- **Source of record for the original text:** script file (never touched)
- **Source of record for the playable cut:** events table (editable via Writer Lab activate)
- **Source of record for editorial work in progress:** writer_lab_drafts (staging only)
- **Source of record for play history:** prompts table, games table (never touched by Writer Lab)
