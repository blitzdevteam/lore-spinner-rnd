# Chaos Engine as Default Production Mode

**Date:** 2026-05-30  
**Status:** Implemented  
**Revert commit:** `6ff6bdf` — "chore: snapshot before chaos-engine-as-default migration"

---

## What this is

The Chaos V2 AI engine is now the production game engine. Users who click any story card on the homepage go through the standard `/user/games/{id}` flow and `User/Games/Show.vue` — but the AI, system prompt, state tracking, and schema powering every turn are the Chaos V2 engine.

The experimental `/chaos-mode` route and `ChaosMode.vue` remain untouched for internal testing.

---

## What changed

### New files

| File | Purpose |
|------|---------|
| `app/Services/ChaosEngineService.php` | Shared AI engine. Both `ChaosModeController` (experimental) and `GameController`/`PromptController` (production) inject this service. Single source of truth for all Chaos logic. |
| `database/migrations/2026_05_30_000001_upgrade_games_table_for_chaos_engine.php` | Drops Story Guard event-navigation columns; adds Chaos V2 state columns. |
| `database/migrations/2026_05_30_000002_upgrade_prompts_table_for_chaos_engine.php` | Drops `event_id` FK; adds `session_number`. |

### Modified PHP files

| File | Change |
|------|--------|
| `app/Models/Game.php` | Removed `currentEvent()` relation + dead event columns. Added Chaos V2 columns. |
| `app/Models/Prompt.php` | Removed `event()` relation + `event_id`. Added `session_number`. |
| `app/Http/Resources/GameResource.php` | Removed `current_event_id`, `currentEvent`. Added `current_session_number`, `current_session_complete`, `model`. |
| `app/Http/Resources/PromptResource.php` | Removed `event_id`, `event`. Added `session_number`. |
| `app/Actions/Game/CreateGameAction.php` | Stripped of all event-resolution logic. Now creates game with `current_session_number=1`, `model=claude-haiku-4-5`, empty alignment scaffold. |
| `app/Http/Controllers/User/GameController.php` | `begin()` fully rewritten with Chaos engine. `reset()` resets Chaos state. `nextSession()` added — advances to next story session carrying world state + symbolic memory. |
| `app/Http/Controllers/User/Game/PromptController.php` | Entire inner body replaced. 500+ lines of Story Guard narration logic removed. Now delegates to `ChaosEngineService`. |
| `app/Http/Controllers/ChaosMode/ChaosModeController.php` | All engine private methods removed. Injects and delegates to `ChaosEngineService`. HTTP/JSON layer only. |
| `app/Http/Controllers/IndexController.php` | Removed `currentEvent` and `currentEvent.chapter` eager loads from `lastGame` query. |
| `routes/routes/user.php` | Added `POST games/{game}/next-session` route. |

### Modified frontend files

| File | Change |
|------|--------|
| `resources/js/pages/User/Games/Show.vue` | Removed event-based journal and character tracking. Added session-complete UI with "Continue to next chapter" button. Session number shown in header. |
| `resources/js/components/ContinueStories.vue` | Replaced chapter/event labels with session number. |
| `resources/js/types/index.d.ts` | `GameInterface` and `PromptInterface` updated to match new DB columns. |

### Deleted files

| File | Reason |
|------|--------|
| `resources/js/components/GameplaySidebarJournalEventCard.vue` | Derived from `prompt.event` which no longer exists. |

---

## DB changes

### `games` table

**Dropped:**
- `current_event_id` — event navigation pointer (Story Guard only)
- `current_beat_type` — beat-map tracker
- `branching_choices_taken` — authored-branch audit log
- `tracked_dimensions` — dimension tracker
- `branch_resolution_log` — turn audit log

**Added:**
- `model VARCHAR(64) default 'claude-haiku-4-5'`
- `symbolic_memory LONGTEXT nullable`
- `alignment_scaffold JSON nullable` — `{chaotic, lawful, neutral}` tally
- `defining_choice_id VARCHAR(128) nullable`
- `defining_choice_line TEXT nullable`
- `is_climactic_choice BOOLEAN default false`
- `current_session_complete BOOLEAN default false`

**Unchanged:** `id`, `story_id`, `user_id`, `current_session_number`, `world_state`, `is_preview`

### `prompts` table

**Dropped:** `event_id` (FK to events)  
**Added:** `session_number SMALLINT UNSIGNED nullable`

---

## Engine — what stayed the same

- Routes: `/user/games/*` unchanged
- URL structure unchanged
- `User/Games/Show.vue` layout, `GameplayLayout`, `GameplayChatCard`, TTS, typewriter — unchanged
- `games` and `prompts` tables — same tables, restructured columns
- `NarrationAgent.php` and narration blade templates — kept (Writer Lab uses them)
- `chaos_sessions` table — kept for experimental page
- `ChaosMode.vue` — kept for internal testing

---

## Continue button

The continue button sends `__continue__` as the player prompt. `PromptController` converts it to the string `"Continue the story forward."` before passing it to the Chaos agent. The agent treats this as an autopilot signal and advances the story as if the player took a passive path forward.

---

## Session advancement

When `ChaosNarrationSchema` returns `session_complete: true`:
1. `games.current_session_complete` is set to `true`
2. `User/Games/Show.vue` shows a "Continue to next chapter" button
3. Player clicks → `POST /user/games/{game}/next-session`
4. `GameController::nextSession()` loads the next session's V2 system prompt, carries `world_state`, `symbolic_memory`, and `alignment_scaffold` forward, creates the opening narration turn, increments `current_session_number`

---

## Default model

`claude-haiku-4-5` — set in `ChaosEngineService::DEFAULT_MODEL` and used by `CreateGameAction` when creating a new game.

---

## Revert instructions

```bash
git revert HEAD  # or git reset --hard 6ff6bdf
php artisan migrate:rollback --step=2
```

The revert commit `6ff6bdf` is the exact state before this migration. Rolling back restores Story Guard in full. No data is lost in `chaos_sessions` (untouched table).

---

## What remains legacy (not deleted, not reachable from UI)

- `GameController::show()` / `store()` — still work, still linked from routes
- Old `games` and `prompts` rows — historical data preserved, tables still exist
- `SimulateGameStartCommand` — uses old `NarrationAgent`, will fail if run (debug tool only)
- Writer Lab `PlaygroundController` + `DraftController` — still use `NarrationAgent` + narration blade templates; these are writer tools and remain on the Story Guard schema intentionally
