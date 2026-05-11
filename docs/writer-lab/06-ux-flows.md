# Writer Lab — UX flows

## The Chapter editor

`pages/WriterLab/Chapter.vue` is a two-panel workspace:

- **Left:** event list. Hover to reveal a multi-select checkbox. Click an event
  body to focus it.
- **Right:** context-sensitive. One of three modes —
  - **event** mode when an event is focused
  - **session** mode when no event is focused but a session tab is active
  - **empty** mode by default

## Event mode (the primary editorial path)

When an event is focused, the right panel renders three stacked sections,
all pre-populated from existing data — **no action required to see it**:

1. **Event Script** — the live content textarea, the `requires_choice`
   toggle, the beat-type select. The beat type is pre-filled from
   `session_architecture.beat_map` via the best text-overlap match against
   the event's title + content (the "from beat map" hint shows the match).
2. **Event Metadata** — objectives textarea, attributes as removable tags
   with an "Add attribute" input. Both pre-fill from the live `events` row,
   or from the latest active edit draft if one exists.
3. **Session Choice Design** — all three branching_choice slots for the
   event's session, with editable fields for tracked dimension, question,
   and three options. Pre-fills from `session_choice_design`.

The action bar shows:

| Action | When | What it does |
|--------|------|--------------|
| **Save Draft** | When any field is dirty | Upserts the event's edit draft (`WriterLabDraft::type='edit'`). The same draft is updated on subsequent saves — no accumulation. |
| **▶ Preview** | When a draft exists | Calls `NarrationAgent` with the live runtime system prompt + the draft content and renders the narrator output inline |
| **✦ Analyse script changes** | Only when the script content differs from the snapshot taken at focus time | Auto-saves first, then calls `ScriptChangeImpactAgent` and fills the same form fields with AI suggestions highlighted in amber |
| **full draft →** | When a draft exists | Jumps to the dedicated Draft.vue page (used for activate + full preview) |

### Cost discipline

The lab never fires an LLM unless the writer asked for it.

- Editing objectives, attributes, beat type, or choice slots → no AI call
- Re-saving the same content many times → no AI call
- Switching events → no AI call
- Clicking "✦ Analyse" without rewriting the script → button isn't rendered

The only LLM in the inline-edit path is the on-demand
`ScriptChangeImpactAgent` call.

## AI fill behaviour

When the writer clicks "✦ Analyse", the agent returns suggestions for every
adaptation layer that is stale. The Vue layer applies those suggestions
directly to the visible form fields and adds an amber outline to each
touched field. The writer can:

- Accept by simply keeping what's there and clicking Save
- Tweak by typing — the amber outline clears as soon as the field changes
- Reject by reverting the field manually (or by switching to a different
  event — the next focus re-loads from the saved draft)

There is **no** separate accept/reject panel. The form is the interface.

### What gets filled

| Field             | Filled when AI says                            |
|-------------------|------------------------------------------------|
| Objectives        | `objectives_needs_update = true`               |
| Attributes        | `attributes_needs_update = true`               |
| Beat type         | `beat_map_needs_update = true`                 |
| Choice slot N     | `choice_design_needs_update = true` AND `choice_slot_affected = 'branching_choice_N'` |

**Only one** choice slot is ever filled by AI — the one whose `source_moment`
text anchors to the focused event. If no slot's source_moment maps to this
event, the agent returns `choice_slot_affected: 'none'` and no choice card is
touched.

### What gets surfaced but not auto-filled

| Layer                | When                                  | Surfaced as                              |
|----------------------|---------------------------------------|------------------------------------------|
| Consequence map      | `consequence_map_needs_review = true` | Yellow banner with the AI's rationale     |
| Cross-session anchor | `cross_session_concern = true`        | Red banner pointing at the downstream session |

The writer handles those manually because they touch downstream sessions or
structural calibrations the lab doesn't auto-rewrite.

## Session mode

Clicking the session tab strip (when no event is focused) opens three
sub-tabs:

- **Cold Open** — textarea + Save. Patches `entry_point_diagnosis.cold_open`.
- **Choice Design** — three structured cards (same shape as in event mode).
  Patches `session_choice_design`.
- **Session Close** — structured editor with separate fields for
  `resolution_prose`, `hook_transition`, the session-end choice question,
  three options each with `text` + `next_session_opens`, and the closing
  `final_line`. The `stickiness_audit` is read-only.

All three save through `createAdaptationEdit`, which upserts **one
adaptation draft per (chapter, session_number)** and recursively merges
incoming patch keys into the existing draft. So a writer can edit cold open,
then choices, then close, and all three end up in one atomic draft.

## Combine / Split / Reorder

These are the batch operations:

- **Combine ⊕** appears when 2+ events are checked. Calls
  `EventCombinerAgent` to merge them into one. Lands on `Draft.vue` for
  review.
- **Split ⊘** appears when exactly 1 event is checked. Lands on `Draft.vue`
  with an empty `split_parts` editor.
- **⇅ Reorder** opens a drag-handles overlay. Save submits a position map.

## Activate

`Draft.vue` is where activate lives. Activate is a transactional dance:

1. Pull the SessionAdaptation row (for the snapshot).
2. Snapshot the affected events to `writer_lab_versions.snapshot_events`.
3. Snapshot the SessionAdaptation to `writer_lab_versions.snapshot_adaptation`.
4. Dispatch on draft type → `activateCombine`, `activateSplit`,
   `activateReorder`, or `activateEdit`.
5. For every key in `adaptation_patch` that maps to an allowed JSON column
   on SessionAdaptation, do `array_replace_recursive(existing, patch)` and
   write back.
6. Mark the draft `activated`, set `activated_at = now()`.

## Restore

`/writer/writer-lab/{story}/versions/{version}/restore`:

1. Re-upsert every event from `snapshot_events`. If an event id doesn't
   exist (it was absorbed by a Combine), insert it back with its original
   id and position.
2. Re-apply the SessionAdaptation snapshot.
3. Mark the restored version `is_active = true`, deactivate sibling versions.

## What does NOT happen here

- No runtime narrator code is altered.
- No prompt is forked or duplicated. Preview uses the exact same view
  (`ai.agents.narration.system-prompt`) the runtime uses.
- No game record is created for preview.
- No re-run of the adaptation pipeline. The lab edits the pipeline's output,
  not the pipeline itself.
