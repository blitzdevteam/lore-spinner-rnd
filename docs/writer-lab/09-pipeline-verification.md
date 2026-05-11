# Writer Lab — Pipeline Verification

A checklist for sanity-checking the Analyse → Save → Activate → Version
chain after any change to the lab.

## Pre-flight

```bash
php artisan migrate:status | grep writer_lab
# Expected: all four lab migrations Run.
```

Migrations to confirm:

- `2026_05_11_000001_create_writers_table`
- `2026_05_11_000002_add_requires_choice_to_events_table`
- `2026_05_11_000003_add_is_preview_to_games_table`
- `2026_05_11_000004_create_writer_lab_drafts_table`
- `2026_05_11_000005_create_writer_lab_versions_table`

## 1. Focus an event

Open `/writer/writer-lab/{story}/chapters/{chapter}` and click an event.

| Check | Expected |
|-------|----------|
| Script textarea shows event content | ✓ |
| Beat type select shows the matched beat | "from beat map" hint visible |
| Objectives textarea pre-filled | matches `events.objectives` |
| Attributes shown as tags | matches `events.attributes` |
| Choice design cards visible | one card per branching_choice_{1,2,3} pre-filled |
| Analyse button | NOT visible (script not changed) |
| Network tab | zero requests |

## 2. Edit objectives only

Type a new objective. Click Save Draft.

```sql
SELECT id, type, source_event_ids, derived_objectives, status
FROM writer_lab_drafts
WHERE chapter_id = ? AND type = 'edit'
ORDER BY id DESC LIMIT 1;
```

Expected: one row, `derived_objectives` = your new text, `status='writer_approved'`.

Save twice more without editing further. The DB row count for this event's
edit drafts should remain **1** (upsert behaviour).

## 3. Edit choice design slot 2 only

Type a new option_b text. Click Save Draft.

Expected on the same draft row:
- `derived_objectives` unchanged (still set from step 2)
- `adaptation_patch` is `{"session_choice_design": {"branching_choice_2": {"option_b": {"text": "..."}}}}`

## 4. Rewrite the script

Replace the script content with something materially different. The
**✦ Analyse script changes** button appears.

Click it.

Expected:
- The button auto-saves first (same draft id is reused, not a new one)
- Network tab shows POST to `/drafts/{id}/analyse-impact`
- Returns `{ impact: { severity, summary, ... } }`
- The form fields the AI flagged update inline with amber outline
- Manual-action warnings (yellow/red banners) appear if consequence_map or
  cross_session flags are set

Click Save Draft again. Same draft id, payload now includes the AI-suggested
fields.

## 5. Activate from Draft.vue

Open `Draft.vue` via the **full draft →** link. Click Activate.

```sql
-- Before:
SELECT id, status FROM writer_lab_drafts WHERE id = ?;          -- writer_approved

-- After:
SELECT id, status, activated_at FROM writer_lab_drafts WHERE id = ?;
-- status = activated, activated_at = now

SELECT id, version_number, session_number, note
FROM writer_lab_versions
WHERE story_id = ?
ORDER BY id DESC LIMIT 1;
-- A new version exists with note "Activated draft #{id} (edit)"

SELECT content, objectives, attributes, requires_choice
FROM events WHERE id = ?;
-- Reflects the activated draft

SELECT entry_point_diagnosis, session_choice_design, session_close_design
FROM session_adaptations WHERE id = ?;
-- Any adaptation_patch keys present on the draft are merged into the JSON columns.
-- Sibling keys not in the patch are preserved.
```

## 6. Verify merge preserved siblings

Specifically check that activating a `{cold_open: '...'}` patch did NOT wipe
`start_event_id` from `entry_point_diagnosis`:

```sql
SELECT JSON_EXTRACT(entry_point_diagnosis, '$.start_event_id') AS start_event_id,
       JSON_EXTRACT(entry_point_diagnosis, '$.cold_open')      AS cold_open
FROM session_adaptations WHERE id = ?;
```

Both should be populated.

## 7. Restore

Open `/writer/writer-lab/{story}/versions`. Click Restore on the snapshot
created in step 5.

```sql
SELECT content, objectives FROM events WHERE id = ?;
-- Back to pre-step-5 state

SELECT is_active FROM writer_lab_versions WHERE id = ?;     -- true

SELECT COUNT(*) FROM writer_lab_versions
WHERE story_id = ? AND session_number = ? AND is_active = true;
-- Exactly 1
```

## Common failure modes and where they live

| Symptom                                  | Likely cause                                        | Fix in                                 |
|------------------------------------------|------------------------------------------------------|----------------------------------------|
| `start_event_id` gone after cold-open save | Patch loop was doing column replace instead of merge | `DraftController::activate()`          |
| Beat type select shows "—" always         | beat_map not at `session_architecture['beat_map']`   | `WriterLabController::chapter()`       |
| `Analyse` button missing after edit       | `originalContent` not captured at focus              | `Chapter.vue` `resetEditState`         |
| Draft count keeps growing on save         | `createEdit` not upserting                           | `DraftController::createEdit`          |
| Activate fails for adaptation-only edit   | `activateEdit` errored on null source_event_ids      | `DraftController::activateEdit` (returns early on null) |
| Restore re-inserts wrong attributes       | JSON column not re-encoded                           | `VersionController::restore` (uses `json_encode`) |
