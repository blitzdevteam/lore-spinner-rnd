# Writer Lab — Editorial Workspace

The Writer Lab is an editorial workspace that exposes the original story events,
the adaptation pipeline's extracted structure, and the runtime narrator's flow,
so a writer (not a developer) can curate the interactive experience without
re-running the adaptation pipeline.

**Runtime invariant — read this first:**

> Nothing about the runtime narrator changes. The Writer Lab works by writing
> drafts that, on activate, mutate the live `events` and `session_adaptations`
> rows. The narrator continues to read those rows the same way it always did.

## Contents of this directory

| File                                     | Purpose                                                           |
|------------------------------------------|-------------------------------------------------------------------|
| `01-design.md`                           | Original design rationale and feature requirements                |
| `02-dev-plan.md`                         | Initial dev plan (predecessor to execution plan)                  |
| `03-execution-plan.md`                   | Detailed execution plan that the implementation followed          |
| `04-architecture.md`                     | System architecture: models, controllers, AI agents, data flow    |
| `05-data-model.md`                       | DB schema, JSON column structure, query patterns                  |
| `06-ux-flows.md`                         | The Chapter editor flow, editorial actions, AI gating, UX rules   |
| `07-ai-agents.md`                        | The three Writer Lab AI agents and when each fires                |
| `08-runbook.md`                          | Operating the lab: register, edit, analyse, save, activate, restore |
| `09-pipeline-verification.md`            | Test plan + checklist for verifying Analyse→Save→Activate→Version |

## TL;DR

A writer logs in to `/writer/authentication/login` (separate `writer` guard,
separate `writers` table). They open a story, pick a chapter, and see a
two-panel editor:

- **Left:** chapter's events in order. Each event shows position, title, a content preview, session number, and a `flow` badge if `requires_choice = false`.
- **Right:** context-sensitive panel. Clicking an event opens an inline editor
  with **all** extracted adaptation data already populated — content,
  objectives, attributes, beat type (pulled from `session_architecture.beat_map`
  via text-overlap match), and the session's three branching-choice slots.
  Selecting no event but a session tab shows the Cold Open / Choice Design /
  Session Close editors for that session.

The writer can:
- **Combine** N events into one rewritten event (AI: `EventCombinerAgent`)
- **Split** one event into N runtime moments (writer-authored content)
- **Reorder** events via drag and drop
- **Edit** a single event — the primary path; AI cost is opt-in
- **Edit adaptation** — cold open, choice design, session close

Every operation produces a `WriterLabDraft` row. The draft is **upserted** —
saving the same event twice updates the same draft, it does not accumulate
drafts. On activate, a `WriterLabVersion` snapshot is taken first, then the
live rows are mutated; restoring a version replays the snapshot back.

The "✦ Analyse script changes" button only appears **after the script
itself was rewritten** — never just because a label or tag changed — and it
is the only place an LLM is called for the edit flow.

## Recent change log

See `docs/writer-lab/CHANGELOG.md` for the iterative refinements that
produced the final shape of the lab.
