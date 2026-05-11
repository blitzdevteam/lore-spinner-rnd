# Writer Lab — Operating Runbook

## Register / log in

1. Open `https://<host>/`. The "Account" button in the top-right is now a
   dropdown for unauthenticated users.
2. Choose **Writer Lab → Apply** for a new account, **Writer Lab → Login**
   for an existing account.
3. After login you land on `/writer/writer-lab` — the story list.

## Open a chapter

1. Click a story. The chapter list opens.
2. Click a chapter. The two-panel editor opens.

## Inspect an event without editing

1. Click an event in the left panel.
2. The right panel shows everything:
   - Script (textarea, pre-populated)
   - `requires_choice` toggle, beat type select (with "from beat map" hint)
   - Objectives, attributes
   - All three branching-choice slots for the event's session

If nothing about the event is dirty, no AI call has occurred and no draft
has been created. You can switch events freely.

## Edit just objectives or attributes

1. Click the event.
2. Edit the objectives textarea OR add/remove an attribute tag.
3. Click **Save Draft**. A `WriterLabDraft` of type `edit` is upserted.
4. No AI was called.
5. To apply: go to **full draft →** then click **Activate**.

## Edit the script content

1. Click the event.
2. Edit the script textarea.
3. **✦ Analyse script changes** now appears in the action bar.
4. Either:
   - Skip the AI and just **Save Draft** — your script becomes the new content
     and the existing extracted metadata stays.
   - Click **✦ Analyse script changes**. The button auto-saves, then runs
     `ScriptChangeImpactAgent`. Affected fields in the form fill in with
     amber outline. Tweak any of them. Click **Save Draft** to capture your
     final form.
5. **▶ Preview** to see how the narrator delivers it.
6. **full draft →** then **Activate** to apply.

## Edit a session's cold open / choice design / session close

1. Make sure no event is focused (the right panel shows the session tabs).
   Or click the session number tab strip.
2. Pick **Cold Open**, **Choice Design**, or **Session Close**.
3. Edit the fields.
4. **Save Cold Open as Draft** / **Save Choice Design as Draft** /
   **Save Session Close as Draft**. All three save through
   `createAdaptationEdit`, which upserts one adaptation draft per session.
   Editing multiple tabs results in one merged draft, not three.
5. Activate from `Draft.vue`.

## Combine 2+ events

1. Hover over events, click the checkboxes to select two or more.
2. Click **⊕ Combine (N)** in the toolbar.
3. You land on `Draft.vue` with the AI-generated combined content.
4. Edit if needed → Activate.

## Split an event

1. Check exactly one event.
2. Click **⊘ Split**.
3. On `Draft.vue`, add parts to `split_parts`.
4. Activate. The original event is overwritten by part 1; remaining parts
   are inserted with shifted positions.

## Reorder events

1. Click **⇅ Reorder**.
2. Drag events into the new order.
3. **Save Reorder**. Positions are written immediately (no separate
   activate step for reorder).

## Restore a previous state

1. Top-right of Chapter page → **Versions**.
2. Each row shows session number, version number, event count, note.
3. Click **Restore**. Events and SessionAdaptation revert.

## Common mistakes

- **Saving over a draft you forgot was there:** the upsert merges your save
  into the existing active draft. If you want a clean slate, Activate the
  current draft first (or delete via DB — there is no UI delete yet).
- **Editing the wrong session's choices:** the session-mode editor uses
  the session number, not the focused event. Watch the active session tab.
- **Re-running adaptation pipeline thinking it'll respect your edits:**
  the pipeline is read-only of your edits. It regenerates from raw story
  source. Your lab edits live downstream of the pipeline.
