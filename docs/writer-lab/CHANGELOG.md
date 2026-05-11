# Writer Lab — Changelog

The Writer Lab was built and iteratively refined in response to live UX
feedback. This file documents the meaningful turning points so future
contributors understand *why* the system has the shape it does.

## Phase 1 — Baseline build

- Writer guard + `writers` table.
- `writer_lab_drafts` + `writer_lab_versions` migrations.
- Controllers: WriterLab, Draft, Version.
- AI agent: `EventCombinerAgent`.
- Vue pages: Index, Show, Chapter, Draft, Versions, Login, Register.
- Activate writes to live `events` and `session_adaptations`. No runtime
  changes.

## Phase 2 — Surface fixes

- Linked Writer Lab login/register from the homepage Account dropdown so
  the route is discoverable.
- Added `events.requires_choice` migration + system-prompt Flow-Moment
  block so non-interactive events render cinematically without choices.

## Phase 3 — Editing as a first-class flow

Feedback: editing one event was hidden behind Combine/Split. JSON dumps
were not user-friendly.

- Made the Chapter editor open an inline event editor on click — direct
  editing of one event no longer needs Combine.
- Replaced JSON code blocks in the right panel with structured UI cards
  for cold open and choice design.
- Added `createEdit` and `createAdaptationEdit` endpoints.
- Added `activateEdit` for type='edit' drafts.

## Phase 4 — Comprehensive impact analysis

Feedback: editing the script doesn't just affect choices; it can stale
objectives, attributes, beat map, consequence map, and cross-session seeds.

- New agent: `ScriptChangeImpactAgent`. Analyses every adaptation layer at
  once and returns structured suggestions.
- Surfaced consequence-map and cross-session concerns as read-only
  warnings (the lab does not auto-rewrite those).

## Phase 5 — Always-visible adaptation data

Feedback: writers need to see ALL extracted data without clicking through
an AI flow. The AI should fill in suggestions, not be required to reveal
the data.

- Pre-populated objectives, attributes, and all three choice slots from
  the live tables on focus.
- `createEdit` accepts objectives + attributes + adaptation_patch in one
  call so the whole form saves atomically.
- AI fills the same visible fields with amber-outlined suggestions.

## Phase 6 — Cost discipline

Feedback: don't run AI unless asked.

- "✦ Analyse script changes" only appears when the script content has
  actually been rewritten (compared to a snapshot taken on focus).
- AI fills only the SINGLE choice slot whose source_moment anchors to the
  edited event; never the other two.
- Agent prompt hardened to return `severity: clean` for prose-polish edits
  and `choice_slot_affected: 'none'` when no confident slot match exists.

## Phase 7 — Query correctness

Audit pass on every query that touches the lab:

- Fixed wrong access path for `beat_map` and `next_session_awareness` —
  both live inside the `session_architecture` JSON column.
- Fixed adaptation_patch application to use `array_replace_recursive` so
  partial patches (e.g. just `{cold_open: ...}`) don't wipe sibling keys
  in the same JSON column.
- Fixed `Event` model `@property` docblock (`$name` → `$title`, added
  missing properties with correct nullability).
- Made `createEdit` upsert per (chapter, event) — saves no longer accumulate
  orphan draft rows.
- Made `createAdaptationEdit` upsert per (chapter, session_number) and
  merge incoming patches into the existing draft.

## Phase 8 — Beat type pre-fill + session close UI

- Server-side: best-effort token-overlap match between an event's
  title+content and each `beat_map[].moment` populates the event's
  `beat_type` and `beat_moment` in the Vue payload. Multiple events may
  share a beat — that's correct.
- Session Close tab now exposes structured editable fields for
  `resolution_prose`, `hook_transition`, the session-end choice (question,
  three options each with `next_session_opens`, `final_line`) and renders
  `stickiness_audit` as read-only verdict badges.

## Phase 9 — Documentation

This `docs/writer-lab/` directory consolidates design, dev plan, execution
plan, architecture, data model, UX flows, agents, runbook, and verification
checklist. Older debug docs were moved here from `Adaptation layer/debug/`
and `.cursor/plans/`.
