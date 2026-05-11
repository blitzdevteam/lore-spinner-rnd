# Writer Lab — Development Plan

**Date:** 2026-05-11
**Depends on:** writer-lab-design.md

---

## Existing Schema Reference (from migrations — no DB command needed)

| Table | Key columns |
|---|---|
| `stories` | id, slug, title, system_prompt (json) |
| `chapters` | id, story_id, position, title, content, status |
| `events` | id, chapter_id, position, title, content, objectives, attributes (json), session_number |
| `story_adaptations` | id, story_id, adaptation_status, format_detection, ip_audit, story_session_map |
| `session_adaptations` | id, story_adaptation_id, session_number, session_status, entry_point_diagnosis, session_architecture, session_choice_design, choice_consequence_map, session_close_design, editorial_verification |
| `managers` | id, email, password (existing admin guard) |

New tables to create: `writer_lab_drafts`, `writer_lab_versions`

---

## Auth Boundary

Writer Lab lives under the `manager` guard (existing `managers` table, existing auth). No new auth system needed. Routes go under `manager/writer-lab/...`.

---

## Phase 1 — Read-Only Inspector

**Goal:** Managers can browse stories → chapters → events alongside adaptation data. Zero writes. Proves the data layer before building anything interactive.

### 1.1 Migrations

None for Phase 1. Read-only.

### 1.2 Routes

```
GET  manager/writer-lab                              writer-lab.index        (story list)
GET  manager/writer-lab/{story}                      writer-lab.show         (chapter list for story)
GET  manager/writer-lab/{story}/chapters/{chapter}   writer-lab.chapter      (two-panel event inspector)
```

Grouped under `auth:manager` middleware.

### 1.3 Controller: `Manager\WriterLab\WriterLabController`

Methods:
- `index()` — list stories with adaptation status
- `show(Story $story)` — list chapters, show adaptation overview (`story_session_map`, `ip_audit` summary)
- `chapter(Story $story, Chapter $chapter)` — load chapter events + matching session adaptations; pass to view

The `chapter()` method loads:
```php
$events = Event::where('chapter_id', $chapter->id)->orderBy('position')->get();

$sessionNumbers = $events->pluck('session_number')->unique()->filter();

$sessionAdaptations = SessionAdaptation::whereHas('storyAdaptation', fn($q) => $q->where('story_id', $story->id))
    ->whereIn('session_number', $sessionNumbers)
    ->get()
    ->keyBy('session_number');
```

### 1.4 View: `resources/views/manager/writer-lab/chapter.blade.php`

Two-panel layout (Tailwind, no JS framework required in Phase 1):

**Left panel — Source Events**
- Each event card shows: position, title, session_number, objectives, first 200 chars of content
- Read-only. Labelled "Source"

**Right panel — Adaptation Data**
- Session selector tabs (if multiple sessions in chapter)
- For each session: cold_open excerpt, beat_map, authored choices (A/B/C text)
- Read-only. Labelled "Adaptation"

Chapter-by-chapter nav at top: `← Previous Chapter | Chapter 3 of 12 | Next Chapter →`

**No writes. No JS interactivity yet.**

---

## Phase 2 — Draft Layer + AI Combine

**Goal:** Writers can select events in a chapter, trigger AI combine, and see the rewritten result in a draft. Still no activation — drafts are safe staging.

### 2.1 Migrations

**`writer_lab_drafts` table**

```php
Schema::create('writer_lab_drafts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('story_id')->constrained()->cascadeOnDelete();
    $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('session_number')->nullable();
    $table->json('source_event_ids');           // array of event IDs combined
    $table->longText('rewritten_content');
    $table->text('derived_objectives')->nullable();
    $table->json('derived_attributes')->nullable();
    $table->string('beat_type')->nullable();
    $table->boolean('requires_choice')->default(true);
    $table->json('canonical_anchors')->nullable();
    $table->json('previous_state');             // snapshot of original event rows
    $table->string('status')->default('draft'); // draft | ai_written | writer_approved | activated
    $table->timestamp('activated_at')->nullable();
    $table->timestamps();
});
```

### 2.2 AI Combine Agent: `App\Ai\Agents\WriterLab\EventCombinerAgent`

Pattern mirrors existing adaptation agents (system-prompt.blade.php + prompt.blade.php).

**System prompt key instructions:**
- You are a narrative editor, not a narrator
- Compress the source events into a single cohesive scene at the original author's voice/style
- Every item in `canonical_anchors` must survive in the output — nothing canonical can be lost silently
- Target: 1–3 paragraphs maximum
- Do not add new plot points not present in the source events

**Structured output schema:**
```json
{
  "rewritten_content": "string",
  "derived_objectives": "string",
  "derived_attributes": ["string"],
  "beat_type": "string",
  "requires_choice": "boolean",
  "canonical_anchors": ["string"]
}
```

The `style_profile` (tone_and_style, character_name, world_rules) is pulled from `story.system_prompt` JSON, which already holds this per-story. No new config.

### 2.3 Controller: `Manager\WriterLab\DraftController`

```
POST  manager/writer-lab/{story}/chapters/{chapter}/drafts         drafts.combine
GET   manager/writer-lab/{story}/chapters/{chapter}/drafts/{draft} drafts.show
PATCH manager/writer-lab/{story}/chapters/{chapter}/drafts/{draft} drafts.update   (writer edits text)
POST  manager/writer-lab/{story}/chapters/{chapter}/drafts/{draft}/approve  drafts.approve
```

`drafts.combine` POST body:
```json
{ "event_ids": [3, 4, 5] }
```

Validation: all event_ids must belong to this `chapter_id`. No cross-chapter. If any event_id is in a different chapter → 422.

`combine()` method:
1. Load events by ids, verify all in same chapter
2. Build `canonical_anchors` from merged `objectives` + `attributes` of all source events
3. Snapshot `previous_state` from event rows
4. Call `EventCombinerAgent`
5. Store result in `writer_lab_drafts` with status `ai_written`
6. Return draft to view

### 2.4 View: `resources/views/manager/writer-lab/draft.blade.php`

Side-by-side:

**Left (read-only):** Original source events (the 3–5 that were selected), with `canonical_anchors` listed at bottom as "Must survive"

**Right (editable):** AI rewrite in a `<textarea>`. Writer can edit, then click "Approve Draft" button.

Canonical anchor checklist under right panel: each anchor is checked/unchecked against the current textarea content. Visual safety net.

---

## Phase 3 — Versioning + Activate

**Goal:** Writer approves draft → creates a version snapshot → activates to live events table.

### 3.1 Migrations

**`writer_lab_versions` table**

```php
Schema::create('writer_lab_versions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('story_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('session_number');
    $table->unsignedInteger('version_number');
    $table->json('snapshot_events');        // full event rows as they were before activate
    $table->json('snapshot_adaptation');    // full session_adaptation as it was before activate
    $table->boolean('is_active')->default(false);
    $table->string('note')->nullable();
    $table->timestamps();
});
```

### 3.2 Controller additions

```
POST  manager/writer-lab/{story}/chapters/{chapter}/drafts/{draft}/activate  drafts.activate
GET   manager/writer-lab/{story}/versions                                     versions.index
POST  manager/writer-lab/{story}/versions/{version}/restore                   versions.restore
```

**`activate()` method — the DB write path:**

```php
DB::transaction(function () use ($draft, $chapter, $story) {
    // 1. Snapshot current state → writer_lab_versions
    $affectedEvents = Event::whereIn('id', $draft->source_event_ids)->get();
    $sessionAdaptation = ...; // load relevant SessionAdaptation

    WriterLabVersion::create([
        'story_id'            => $story->id,
        'session_number'      => $draft->session_number,
        'version_number'      => $this->nextVersionNumber($story),
        'snapshot_events'     => $affectedEvents->toArray(),
        'snapshot_adaptation' => $sessionAdaptation?->toArray(),
        'is_active'           => false,
    ]);

    // 2. Overwrite survivor event (lowest position in source_event_ids)
    $survivor = $affectedEvents->sortBy('position')->first();
    $absorbed = $affectedEvents->where('id', '!=', $survivor->id);

    $survivor->update([
        'content'    => $draft->rewritten_content,
        'objectives' => $draft->derived_objectives,
        'attributes' => $draft->derived_attributes,
    ]);

    // 3. Delete absorbed events
    Event::whereIn('id', $absorbed->pluck('id'))->delete();

    // 4. Update adaptation if writer edited cold_open / beat_map / choices
    // (fields stored on draft if writer_approved those changes)
    if ($draft->adaptation_patch) {
        $sessionAdaptation?->update($draft->adaptation_patch);
    }

    // 5. Mark draft as activated
    $draft->update(['status' => 'activated', 'activated_at' => now()]);

    // 6. Mark new version as active, deactivate previous
    WriterLabVersion::where('story_id', $story->id)
        ->where('session_number', $draft->session_number)
        ->update(['is_active' => false]);

    WriterLabVersion::latest()->first()->update(['is_active' => true]);
});
```

Note: no `position` renumbering. The runtime uses `position > X` queries — gaps from deleted events are harmless.

**`restore()` method:**

Reads `snapshot_events` from the target version. For each event in the snapshot:
- If the row still exists (by id) → update it back to snapshot values
- If the row was deleted (absorbed) → re-insert it with the original id and position

This is safe because positions are preserved in the snapshot.

---

## Phase 4 — Live Preview

**Goal:** Writers can fire the same NarrationAgent against a draft event to test pacing before activating.

### 4.1 Preview game record

A `Game` record is created with a `is_preview` flag (new nullable boolean column on `games`). The game's `current_event_id` is set to the draft's survivor event. The game's `story_id` is set normally.

`GameController::begin()` is called on the preview game, but the `currentEvent->content` is overridden with `$draft->rewritten_content` before being passed to `renderSystemPrompt`. This override is passed as an extra argument — the controller already accepts arbitrary `currentEvent` data via the `renderSystemPrompt` view binding. No logic change in `PromptController`.

The preview UI shows the narrator response inline in Writer Lab, not in the main game view.

Preview games are ephemeral — deleted after 24h via a scheduled command or when the writer closes the preview.

### 4.2 Routes

```
POST  manager/writer-lab/{story}/chapters/{chapter}/drafts/{draft}/preview   drafts.preview
```

Returns the narrator's `response` text + `choices` array as JSON for inline display.

---

## File Structure

```
app/
  Http/Controllers/
    Manager/
      WriterLab/
        WriterLabController.php       (index, show, chapter)
        DraftController.php           (combine, show, update, approve, activate, preview)
        VersionController.php         (index, restore)
  Ai/Agents/WriterLab/
    EventCombinerAgent.php
  Models/
    WriterLabDraft.php
    WriterLabVersion.php
  Jobs/WriterLab/
    (no background jobs in v1 — combine call is synchronous, short timeout)

database/migrations/
  YYYY_MM_DD_000001_create_writer_lab_drafts_table.php
  YYYY_MM_DD_000002_create_writer_lab_versions_table.php
  YYYY_MM_DD_000003_add_is_preview_to_games_table.php

resources/views/manager/writer-lab/
  index.blade.php          (story list)
  show.blade.php           (chapter list)
  chapter.blade.php        (two-panel inspector)
  draft.blade.php          (side-by-side combine review)
  versions.blade.php       (version history + restore)

resources/views/ai/agents/writer-lab/
  event-combiner/
    system-prompt.blade.php
    prompt.blade.php

routes/routes/manager.php  (new file — mirrors user.php pattern)
```

---

## Build Order (strict)

| Phase | Deliverable | Prerequisite |
|---|---|---|
| 1 | Read-only inspector | None — start here |
| 2a | `writer_lab_drafts` migration + model | Phase 1 done |
| 2b | `EventCombinerAgent` + prompt views | Phase 1 done (parallel with 2a) |
| 2c | `DraftController::combine()` | 2a + 2b done |
| 2d | Draft review view | 2c done |
| 3a | `writer_lab_versions` migration + model | Phase 2 done |
| 3b | `activate()` + `restore()` | 3a done |
| 3c | Version history view | 3b done |
| 4 | Live preview | Phase 3 done |

---

## What Does NOT Change

The following files are not touched in any phase of Writer Lab:

- `PromptController.php`
- `NarrationAgent.php`
- `system-prompt.blade.php`
- `prompt.blade.php` (narration)
- `GameController.php` (except adding `is_preview` check in Phase 4, which is an additive guard)
- All adaptation pipeline jobs
- All existing migrations
- `game:trace` command
- `narration` log channel

---

## Open Questions Before Phase 1 Starts

1. **Manager route file** — does `routes/routes/manager.php` already exist, or does it need to be created and required from `web.php`?
2. **Manager auth middleware** — is `auth:manager` already configured in `config/auth.php`, or only implied by the `managers` table?
3. **UI framework for Writer Lab** — Blade + Alpine (already used for Voice Lab) or Inertia? Recommend Blade + Alpine for consistency with the rest of the app.

These three answers unblock Phase 1.
