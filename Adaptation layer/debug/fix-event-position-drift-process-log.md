# Fix: Event Position Drift — Process Log

**Commit:** `30eaf2075f988fdc69f5a17aa22ee687d6cf613b`
**Branch:** `main`
**Date:** 2026-04-19
**Plan:** `fix-event-position-drift` (see `.cursor/plans/fix-event-position-drift_6d0e1e1c.plan.md`)
**Upstream analysis:** `adaptation-analysis.md` (sibling file)

---

## 1. Problem

New games for "Alice's Adventures in Wonderland" were opening on event `id=63` "Duchess links arms and walks with Alice" — a Chapter 6 scene — instead of the adaptation's intended Session 1 cold open (Rabbit-with-watch, Chapter 1).

### Root cause

`events.position` is 1-based **per-chapter** (set by `EventExtractorJob`). The adaptation pipeline was treating it as **story-global** when parsing `session_allocation.event_range` strings like `"1-8"`, `"48-58"`, `"59-69"`, and when looking up `start_event_id` from `start_event_position`.

The AI agent emits these range/position values **globally** because the prompt shows it the total event count. The code was filtering locally. This mismatch had two concrete symptoms:

1. **`StorySessionMapJob`** filtered `events` by `position BETWEEN range[0] AND range[1]` using per-chapter positions. Range `"59-69"` matched zero events (no chapter has 69 events), triggering an equal-split fallback that silently redistributed all events into sessions in (per-chapter-only) order — corrupting `events.session_number`.
2. **`EntryPointDiagnosisJob`** then inherited the broken `session_number` assignments and looked up `start_event_id` via `firstWhere('position', $startPos)` — which, for a pollution-seeded Session 1, resolved to whichever event happened to be sitting there. For Alice: event id=63.

The equal-split fallback was a second defect on top of the filter bug: it converted every failure mode into a silent data corruption instead of surfacing a loud error.

---

## 2. Decision

**On-the-fly story-global ordinal.** No migration, no new column. Compute a 1..N story-global index by ordering events by `(chapters.position, events.position)` and use that index as the shared contract between:
- the agent prompt (what the AI sees when choosing `event_range` / `start_event_position`),
- the session-allocation filter (how we assign `events.session_number`),
- the entry-point lookup (how we resolve `start_event_id`).

`events.position` semantics stay unchanged (per-chapter). The game runtime (`CreateGameAction`, `GameController::generateFirstNarration`) continues to use per-chapter `events.position` correctly — the fix is contained to the adaptation layer.

The equal-split fallback was **removed entirely**, not merely patched. After the global-ordinal fix, any session that still resolves to zero events indicates either AI hallucination in `event_range` or a real data gap; both should fail loud, not be silently papered over.

---

## 3. Changes shipped

### `app/Jobs/Adaptation/StorySessionMapJob.php`

- Added private helper `loadEventsWithStoryPosition()` that queries `Event::query()->join('chapters', ...)`, orders by `(chapters.position, events.position)`, and stamps a 1-based `story_position` attribute on each row via `->values()->map(fn($ev,$i)=>...$i+1)`.
- Passes `story_position` + `totalEvents` into the prompt view.
- Session-allocation filter now does `$ev->story_position >= $range[0] && $ev->story_position <= $range[1]` (story-global).
- **Deleted the equal-split fallback block** (old lines 112-131). Replaced with a `RuntimeException` thrown inside the DB transaction when any session resolves to zero events — which unwinds the transaction and trips the outer `try/catch` at lines 164-170, marking `adaptation_status = FAILED`.

### `app/Jobs/Adaptation/EntryPointDiagnosisJob.php`

- Added private helper `loadSessionEventsWithStoryPosition()` that computes `story_position` across the **full** story (not just this session's events), so the ordinal matches what `StorySessionMapJob` showed the agent. Then filters down to `session_number === $this->sessionNumber`.
- Passes `story_position`, `chapter_position`, and `position` (local) into the prompt view for each session event.
- `start_event_id` lookup changed from `firstWhere('position', $startPos)` → `firstWhere('story_position', $startPos)`.
- Persists additional traceability fields in `entry_point_diagnosis`:
  - `start_event_position` → now story-global ordinal (was per-chapter).
  - `start_event_chapter_position` → the resolved event's chapter position.
  - `start_event_local_position` → the resolved event's per-chapter position (unchanged semantics).

### `resources/views/ai/agents/adaptation/story-session-map/prompt.blade.php`

- Events now rendered as `Event {story_position} of {totalEvents} (Chapter {chapter_position}, local pos {position}): {title}`.
- Added an "EVENT NUMBERING CONVENTION" block above the event list that tells the agent:
  > Events below are numbered 1..N across the entire story (story-global ordinal). All `event_range` values AND `event_position` values you return MUST reference the story-global Event number shown below. Do NOT use per-chapter positions. Do NOT emit ranges that exceed N.

### `resources/views/ai/agents/adaptation/entry-point-diagnosis/prompt.blade.php`

- Events rendered as `Event {story_position} (Chapter {chapter_position}, local pos {position}): {title}`.
- Added the same numbering-convention instruction so `start_event_position` from this agent is also story-global.

### `app/Console/Commands/SimulateGameStartCommand.php`

- `reportStartEventResolution` now prints `chapter_id` for both the first-chapter event and the resolved start event.
- When the two events are in **different chapters**, emits a loud `warn()`:
  > WARNING: resolved start event is in a DIFFERENT chapter than chapter-1. first-event chapter_id=X (chapter pos=A) start-event chapter_id=Y (chapter pos=B). This usually signals event_position drift between the adaptation layer and events table.
- This makes the simulator self-diagnosing for any future regression of the same class of bug.

---

## 4. What is NOT changed

- **No migration.** `events.position` keeps its per-chapter semantics. No new column.
- **No change to game runtime.** `CreateGameAction::resolveStartEvent` and `GameController::generateFirstNarration` already use per-chapter `events.position` correctly — the fix is adaptation-layer only.
- **No change to `EventExtractorJob`.** It still emits per-chapter positions, which is fine because they're now explicitly labeled as such in the adaptation prompts ("local pos Y").

---

## 5. Verification plan (Laravel Cloud, Alice-only)

Deployment is already live — commit pushed to `main` at `30eaf20`, Laravel Cloud auto-deploys.

### Step 1: Force re-adapt Alice

```bash
php artisan tinker --execute="App\Jobs\Adaptation\RunAdaptationPipelineJob::dispatchSync(App\Models\Story::where('slug','alices-adventures-in-wonderland')->first(), true);"
```

The `true` flag handles the reset in-place — deletes `session_adaptations`, nulls all `events.session_number`, wipes `format_detection`/`ip_audit`/`story_session_map` on the `story_adaptation` row, and re-runs the pipeline chain on the `adaptation` queue.

**No manual delete needed.** If a session resolves to zero events, you will see a `RuntimeException: Session X resolved to zero events for range "..." ...` and the adaptation will be marked `failed` — that is the removed-fallback guard firing, not a regression.

### Step 2: Check integrity

```bash
php artisan tinker --execute="\$s=App\Models\Story::where('slug','alices-adventures-in-wonderland')->first(); echo 'null session_number: '.\$s->events()->whereNull('session_number')->count().PHP_EOL; echo 'adaptation_status: '.\$s->adaptation?->adaptation_status->value.PHP_EOL;"
```

Expected: `null session_number: 0`, `adaptation_status: completed`.

### Step 3: Check Session 1 entry point

```bash
php artisan tinker --execute="\$sa=App\Models\SessionAdaptation::whereHas('storyAdaptation.story',fn(\$q)=>\$q->where('slug','alices-adventures-in-wonderland'))->where('session_number',1)->first(); \$ev=App\Models\Event::find(\$sa->entry_point_diagnosis['start_event_id']); echo 'start event #'.\$ev->id.': '.\$ev->title.' (chapter pos '.\$ev->chapter->position.', local pos '.\$ev->position.')'.PHP_EOL;"
```

Expected: a Chapter 1 event whose title matches the adaptation's cold open direction (not event id=63 "Duchess links arms...").

### Step 4: Confirm game runtime picks up same event

```bash
php artisan game:simulate-start alices-adventures-in-wonderland
```

Expected:
- Resolved start event matches Step 3's event id.
- **No "WARNING: resolved start event is in a DIFFERENT chapter"** from the patched simulator.
- LLM response narrates a Chapter 1 Rabbit-with-watch scene.

---

## 6. Rollback

If verification fails:

```bash
git revert 30eaf207 && git push origin main
```

Laravel Cloud auto-redeploys the reverted state. Re-running `RunAdaptationPipelineJob` against Alice rewrites `events.session_number` and recreates `session_adaptations` rows under the old pipeline — no irreversible data loss, no migration to undo.

---

## 7. Follow-ups (out of scope for this fix)

- **`events.story_position` as a persisted column.** Currently computed on the fly every time `StorySessionMapJob` and `EntryPointDiagnosisJob` run. Persisting it via migration would let game runtime, analytics, and the narration agent reference it directly. Defer until one of those consumers actually needs it.
- **Cross-validate `start_event_id` vs `start_event_position`.** Harden `CreateGameAction::resolveStartEvent` to assert that the resolved event's `story_position` matches the agent-returned `start_event_position`. Would catch drift at the game-start boundary rather than relying on the simulator warning.
- **AI re-verify session boundaries semantically.** Even with the ordinal contract correct, the agent could still emit boundaries that split a scene awkwardly. Post-adaptation editorial verification already exists; revisit whether it flags allocation issues or only per-session content issues.

---

## 8. Files touched

- `app/Jobs/Adaptation/StorySessionMapJob.php` (modified)
- `app/Jobs/Adaptation/EntryPointDiagnosisJob.php` (modified)
- `resources/views/ai/agents/adaptation/story-session-map/prompt.blade.php` (rewritten)
- `resources/views/ai/agents/adaptation/entry-point-diagnosis/prompt.blade.php` (rewritten)
- `app/Console/Commands/SimulateGameStartCommand.php` (modified — `reportStartEventResolution` only)

**5 files, 118 insertions(+), 47 deletions(-).**
