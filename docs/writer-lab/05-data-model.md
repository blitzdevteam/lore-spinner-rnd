# Writer Lab — Data Model

## Tables added by the Writer Lab

### `writers`
Dedicated authenticatable users for the lab.
```
id  name  email (unique)  email_verified_at  password
remember_token  timestamps
```

### `writer_lab_drafts`
The staging area. Every editorial operation produces or updates a row here.
On activate, the row's payload is applied to the live tables.
```
id
story_id        FK stories          cascade on delete
chapter_id      FK chapters         cascade on delete
session_number  nullable

type            combine | split | reorder | edit
status          draft | ai_written | writer_approved | activated
activated_at    nullable timestamp

source_event_ids  json array | null   the events being operated on
rewritten_content longText  | null    combine + edit  →  new content
derived_objectives text     | null    combine + edit  →  new objectives
derived_attributes json     | null    combine + edit  →  new attributes
beat_type         string   | null    combine + edit  →  draft-level only
requires_choice   bool      default true
canonical_anchors json     | null    combine safety net

split_parts       json     | null    split → [{title, content, objectives, …}]
event_order       json     | null    reorder → [{event_id, new_position}]

previous_state    json     | null    snapshot of pre-edit event rows for rollback
adaptation_patch  json     | null    partial patch for session_adaptations columns

timestamps
```

### `writer_lab_versions`
Immutable snapshots taken before each activate.
```
id
story_id          FK stories
session_number    int
version_number    int

snapshot_events       json   array of full event rows at snapshot time
snapshot_adaptation   json   nullable; full SessionAdaptation toArray()

is_active   bool
note        string nullable
created_at  timestamp

UNIQUE (story_id, session_number, version_number)
```

## Schema mutations to existing tables

### `events.requires_choice`
Migration `2026_05_11_000002_add_requires_choice_to_events_table.php`.
```
$table->boolean('requires_choice')->default(true);
```
When `false`, the narration system prompt switches into Flow-Moment mode and
the narrator advances the event cinematically without holding for player
agency.

### `games.is_preview`
Migration `2026_05_11_000003_add_is_preview_to_games_table.php`. Reserved
for ephemeral preview games. The current implementation prefers an
in-memory preview (no game row), but the column exists for future use.

## JSON column structure (cheat sheet)

`session_adaptations` is the JSON-heavy table the lab edits. The most
relevant nested paths:

| Column                  | Nested key                              | What it is |
|-------------------------|-----------------------------------------|------------|
| `entry_point_diagnosis` | `cold_open`                             | First paragraph the narrator delivers verbatim |
| `entry_point_diagnosis` | `start_event_id`                        | Which event the session opens on               |
| `session_architecture`  | `beat_map[].moment`                     | One-sentence editorial description per beat   |
| `session_architecture`  | `beat_map[].beat_type`                  | SETUP / ESCALATION / BREATH / TWIST / RESOLUTION |
| `session_architecture`  | `beat_map[].choice_arrives`             | Which choice (if any) fires at this beat       |
| `session_architecture`  | `next_session_awareness.seed_for_next_session` | What this session plants for the next |
| `session_choice_design` | `branching_choice_{1,2,3}.source_moment`| Story text that anchors this choice slot to an event |
| `session_choice_design` | `branching_choice_{1,2,3}.choice_question` | The player-facing question |
| `session_choice_design` | `branching_choice_{1,2,3}.option_{a,b,c}.text` | The three options |
| `session_choice_design` | `branching_choice_{1,2,3}.what_this_choice_tracks` | The emotional/behavioural axis |
| `choice_consequence_map`| `branching_choice_{1,2,3}.option_{a,b,c}` | World-state effects per path; structural, rarely written |
| `session_close_design`  | `resolution_prose`                      | Closing prose                                 |
| `session_close_design`  | `hook_transition`                       | Bridge into the session-end choice            |
| `session_close_design`  | `session_end_choice.choice_question`    | Retention question                            |
| `session_close_design`  | `session_end_choice.option_{a,b,c}.text`| Option text                                   |
| `session_close_design`  | `session_end_choice.option_{a,b,c}.next_session_opens` | What that path carries into next session |
| `session_close_design`  | `session_end_choice.final_line`         | Closing bumper line                           |
| `session_close_design`  | `stickiness_audit`                      | PASS / REVISE verdicts from pipeline (read-only) |

## Query patterns

### Resolve a session adaptation for a story + session number
```php
SessionAdaptation::query()
    ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $story->id))
    ->where('session_number', $sessionNumber)
    ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
    ->first();
```

### Resolve all session adaptations needed by a chapter
```php
SessionAdaptation::query()
    ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $story->id))
    ->whereIn('session_number', $sessionNumbersFromEvents)
    ->get()
    ->keyBy('session_number');
```

### Upsert an edit draft for a single event
```php
$existing = WriterLabDraft::where('chapter_id', $chapter->id)
    ->where('type', 'edit')
    ->whereNotIn('status', ['activated'])
    ->whereJsonContains('source_event_ids', $event->id)
    ->orderByDesc('id')
    ->first();

$existing ? $existing->update($payload) : WriterLabDraft::create($payload + [
    'previous_state' => $this->buildPreviousState(collect([$event])),
]);
```
We use `whereJsonContains` so MySQL/MariaDB matches the array element directly.

### Apply an adaptation patch on activate
Always merge — never replace — because the writer's patch is typically partial:
```php
$existing = $sessionAdaptation->$column;
$merged   = is_array($existing) ? array_replace_recursive($existing, $patchValue) : $patchValue;
$sessionAdaptation->update([$column => $merged]);
```
Without the merge, saving "just the cold open" would erase `start_event_id`,
`session_anchor_rationale`, and every other sibling key.

### Best-effort beat_map → event match
Beat-map entries are session-level summaries; there is no per-event link.
`WriterLabController::bestBeatMatch()` does a token-overlap score between an
event's `title + content` and each beat_map `moment`, using a stopword filter.
The highest-overlap entry's `beat_type` is what populates the event's beat
type select in the Chapter UI. Multiple events may legitimately share a beat.
