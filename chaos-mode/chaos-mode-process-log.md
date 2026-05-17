# Chaos Mode — Process Log

_**On disk:** `chaos-mode/chaos-mode-process-log.md`_

**Created:** 2026-05-16
**Branch baseline (core revert — pre–Chaos Mode):** `e141b61` — *HeroBanner: frosted glass card on UI block, tighter left alignment*

**Last revised:** 2026-05-17
**Status:** v2 — full-session context architecture
**v2 baseline commit (pre-v2 implementation):** `c36b2fe` — *fix(chaos): 419 CSRF — use XSRF-TOKEN cookie; redesign UI to match app design system*
**v1 baseline commit (initial Chaos Mode landing):** `6623978` — *feat: add Chaos Mode — experimental open runtime for Alice in Wonderland*

`e141b61` is the **core revert** SHA: `git checkout e141b61 -- routes/web.php resources/js/pages/Index.vue` restores the repo to before Chaos Mode existed, after you delete the chaos-mode code paths (see **Option C** below).

---

## Implementation Journal

### 2026-05-17 — v2: full-session context

Reworked Chaos Mode from the v1 stateless prompt-only architecture into a full-session context runtime. The trigger: feedback that v1 was hardcoding Alice's session 1 instead of curating it from the adaptation export and DB events, and that runtime was being given control that should belong to the narrator.

**Decisions captured in v2:**

1. Pass the **full Session 1 source script** (DB events 1–23) every turn, alongside the adaptation packet from `database/exports/adapptation-third-try.json`. This mimics Curt's Claude setup where the model is given the entire playable container.
2. Remove runtime-side movement validation. No `advance_event`, `scene_note`, `suggested_playhead`, or playhead arbitration. The narrator owns pacing and movement inside the active session.
3. Introduce **`session_complete: true`** as the only AI→runtime signal about boundary. Runtime listens for it and prepares Session 2; AI does not narrate the transition.
4. Persist conversation history, world state, and session memory **server-side** in a new `chaos_sessions` table. Client only holds `session_id`.
5. Use a story-agnostic state shape: `conditions`, `items`, `location`, `relationships`, `knowledge`, `notes`. The v1 `size_condition` field was Alice-specific and is gone.
6. Add an explicit **Freedom Contract** section in the system prompt so the full session script reads as gravity, not a rail.
7. The authored **cold open** is rendered once on `start()` only. On subsequent turns the conversation history carries it.
8. **No fallbacks.** The DB is sound on Laravel Cloud. If a migration needs to run, ask first.

---

## Summary

Chaos Mode is a completely isolated experimental runtime that recreates the "Curt's Claude test" experience directly in the LoreSpinner app, but with the LoreSpinner adaptation layer as the dramatic spine.

**Core principle (v2):**

> The AI controls narration, pacing, and movement INSIDE the active session.
> The runtime controls only which session is loaded, persistent state, conversation log, and the technical boundary between sessions.

**What that means in practice:**

- The narrator receives the **full Session 1 source script** (events 1–23 from the DB) every turn — like Claude does when Curt attaches the full session material.
- The narrator receives the **session adaptation packet** above the script: dramatic question, emotional promise, beat map, authored choices, session destination, next-session seed. This is what makes it LoreSpinner, not just generic RP.
- The narrator decides when the session has reached its natural close and returns `session_complete: true`. Runtime then prepares Session 2 (Session 2 loading is a placeholder for the demo).
- There is **no** `advance_event`, `scene_note`, `suggested_playhead`, or runtime-side movement validation. Those were rails. Removed.

---

## v1 → v2 Changes

| Concern | v1 | v2 |
|---|---|---|
| Session context | World packet only (hardcoded in prompt) | **Full session script from DB + adaptation packet from JSON** |
| Cold open | Hardcoded "bottom of the rabbit-hole" fallback | **Authored cold open from `adapptation-third-try.json` Session 1 entry diagnosis**, rendered once on `start()` |
| AI movement signal | `advance_scene` boolean, `scene_note` string | **Removed** — AI moves freely inside the session |
| Session boundary | n/a | New `session_complete` boolean — AI signals end, runtime owns transition |
| State shape | `size_condition`, `items`, `location`, `notes` (Alice-specific) | **`conditions`, `items`, `location`, `relationships`, `knowledge`, `notes`** (story-agnostic) |
| Persistence | Stateless (client owned history + state) | **`chaos_sessions` table** — server-owned, conversation logged for every turn |
| Memory | None | New `session_memory_update` per turn, accumulated in `chaos_sessions.session_memory` |
| Freedom Contract | Implicit | **Explicit section** in system prompt — script is gravity, not a cage |

---

## Files

| File | Purpose |
|------|---------|
| `chaos-mode/chaos-mode-process-log.md` | This document (formerly `process-log.md`) |

### New

| File | Purpose |
|------|---------|
| `database/migrations/2026_05_17_000001_create_chaos_sessions_table.php` | Persistence table for chaos mode playthroughs |
| `app/Models/ChaosSession.php` | Eloquent model for chaos sessions |

### Rewritten (v2)

| File | Change |
|------|--------|
| `app/Http/Controllers/ChaosMode/ChaosModeController.php` | New `loadCurrentSessionContext()` reads adaptation JSON + DB events. Creates `ChaosSession` on start. Persists history/state/memory on every turn. Cold open passed once. |
| `resources/views/ai/agents/chaos/system-prompt.blade.php` | New `SESSION PACKET`, `FULL CURRENT SESSION SCRIPT`, `FREEDOM CONTRACT`, `SESSION-COMPLETE SIGNAL` sections. Removed hardcoded rabbit-hole fallback. `WORLD STATE` expanded to 6-field shape. |
| `app/Ai/Agents/Chaos/ChaosNarrationAgent.php` | Structured schema rewritten — `session_complete`, `state_delta` (6 fields), `session_memory_update`. Removed `advance_scene`, `scene_note`, `world_update`. |
| `resources/views/ai/agents/chaos/turn-prompt.blade.php` | Removed hardcoded "rabbit-hole" cold-open instruction. Now references `CURRENT OPENING SCENE` from system prompt. |
| `resources/js/pages/ChaosMode.vue` | Tracks `session_id` instead of local history. Server owns conversation. New 6-field `WorldState`. Session-complete banner with disabled "Continue to Session 2" button. |

### Unchanged

| File | Notes |
|------|-------|
| `app/Ai/Agents/Chaos/ChaosNarrationAgent*.php` (variants) | They inherit `chaosSchema()` from the base agent — schema change propagates automatically. |
| `routes/web.php` | Routes (`/chaos-mode`, `/chaos-mode/start`, `/chaos-mode/turn`) untouched. |
| `resources/js/pages/Index.vue` | Chaos Mode entry banner untouched. |

---

## Architecture

### Context stack passed to the narrator every turn

```text
1. Carroll voice + Alice's World (premise, physics, characters, full 7-chapter arc)
2. SESSION PACKET (adaptation spine)
   - dramatic_question
   - emotional_promise
   - emotional_register
   - beat_map (10 compressed beats)
   - authored_choices (3 branching choices with A/B/C)
   - session_destination
   - next_session_seed
3. FULL CURRENT SESSION SCRIPT (DB events 1–23 with title, objective, content)
4. HOW TO NARRATE + Carroll's Prose Style
5. FREEDOM CONTRACT (script is gravity, not a cage)
6. WORLD STATE (current persistent state — conditions, items, location, relationships, knowledge, notes)
7. CURRENT OPENING SCENE (cold open — only on start(), never on turn())
8. SESSION-COMPLETE SIGNAL (when to flip session_complete:true)
```

### Why this is closer to Claude

Claude (in Curt's tests) was given the full dramatic material for the playable stretch. It had the whole container in context: tone, pacing, character behavior, scene rhythm, direction. We mimic this by passing the full Session 1 events as source material, but we add the adaptation packet on top so the model knows *what the session means*, not only *what happens*.

The events stay as storage. The session becomes the playable context. The adaptation becomes the spine.

### Runtime vs AI responsibilities

| Concern | Owner |
|---|---|
| Which session is loaded | Runtime (`ChaosModeController`) |
| Loading next session | Runtime |
| Conversation history persistence | Runtime |
| World state persistence | Runtime |
| Session memory persistence | Runtime |
| When the session is narratively complete | **AI** (`session_complete: true`) |
| Movement inside the session | **AI** |
| Pacing inside the session | **AI** |
| Authored-choice spirit at choice moments | **AI** |
| Tone-faithful improv inside the session | **AI** |

### Why no fallbacks

The app is deployed on Laravel Cloud. The DB is sound. Events for Alice exist in the seeded environment (positions 1–23 are guaranteed by the adaptation). No `if (DB empty) → read txt` fallback. If migrations need to run, ask before running.

---

## Database

### `chaos_sessions`

```text
id                     ULID primary
story_id               nullable FK → stories (nullOnDelete)
user_id                nullable FK → users   (nullOnDelete)
story_session_number   unsigned int (default 1)
model                  varchar(64)
conversation_history   json (array of {role, text})
world_state            json (6-field state shape)
session_memory         text (newline-separated session_memory_update entries)
session_complete       boolean (default false)
turn_count             unsigned int (default 0)
ip_address             varchar(45) nullable
created_at / updated_at
indexes: [story_id, story_session_number], [created_at]
```

No foreign keys depend on this table from elsewhere — dropping it is fully reversible.

### Migration command (run on Laravel Cloud)

```bash
php artisan migrate --path=database/migrations/2026_05_17_000001_create_chaos_sessions_table.php
```

Or simply:

```bash
php artisan migrate
```

---

## Session 1 specifics (hardcoded for demo)

| Detail | Value | Source |
|---|---|---|
| Story slug | `alices-adventures-in-wonderland` | constant in controller |
| Event range | positions 1–23 | `adapptation-third-try.json` → `story_wide.story_session_map.session_allocation[0].event_range` ("1-23") |
| Adaptation source | `database/exports/adapptation-third-try.json` → `sessions[]` where `session_number == 1` | constant in controller |

When Session 2 is wired in, `loadCurrentSessionContext()` will take a session-number argument and the range will be parsed dynamically from the same JSON.

---

## How to test

1. Run the migration (or `php artisan migrate`).
2. Navigate to `/chaos-mode`.
3. Pick a model. Click "Begin the Adventure".
4. The opening should feel like the **riverbank cold open** (heat, sister's page-turning, White Rabbit with the watch) — not the bottom-of-the-hole fallback from v1.
5. Inspect a `chaos_sessions` row: it should contain the full conversation history and merged world_state.
6. Play through a few turns. Try off-script actions. Verify the narrator stays in Carroll's voice and the world state accumulates.
7. When the AI returns `session_complete: true`, the "Session 1 — Complete" banner should appear and input should be disabled.

### Quick DB inspection

```bash
php artisan tinker --execute="dump(App\Models\ChaosSession::latest()->first()?->toArray());"
```

---

## How to revert

### Option A — Roll back v2, keep v1

Restore the v1 prompt + schema while keeping the route group and Vue page. Useful if v2 misbehaves but you want to keep the entry surface.

```bash
# Drop the v2 table
php artisan migrate:rollback --path=database/migrations/2026_05_17_000001_create_chaos_sessions_table.php

# Delete v2-only files
rm "app/Models/ChaosSession.php"
rm "database/migrations/2026_05_17_000001_create_chaos_sessions_table.php"

# Restore v1 files
git checkout c36b2fe -- \
  app/Http/Controllers/ChaosMode/ChaosModeController.php \
  app/Ai/Agents/Chaos/ChaosNarrationAgent.php \
  resources/views/ai/agents/chaos/system-prompt.blade.php \
  resources/views/ai/agents/chaos/turn-prompt.blade.php \
  resources/js/pages/ChaosMode.vue
```

### Option B — Soft revert (keep table, disable mode)

Comment out the chaos-mode route block in `routes/web.php`. Data is preserved. Re-enable by uncommenting.

### Option C — Full removal (v1 + v2 both gone; **core revert** to `e141b61`)

This matches the original **branch baseline** from 2026-05-16: strip Chaos Mode entirely and restore `routes/web.php` + `Index.vue` to the pre-chaos HeroBanner state.

```bash
# Drop the v2 table first
php artisan migrate:rollback --path=database/migrations/2026_05_17_000001_create_chaos_sessions_table.php

# Delete every chaos file
rm -rf "app/Http/Controllers/ChaosMode"
rm -rf "app/Ai/Agents/Chaos"
rm -rf "resources/views/ai/agents/chaos"
rm "resources/js/pages/ChaosMode.vue"
rm "app/Models/ChaosSession.php"
rm "database/migrations/2026_05_17_000001_create_chaos_sessions_table.php"
rm -rf "chaos-mode/"

# Core revert: pre–Chaos Mode (HeroBanner only)
git checkout e141b61 -- routes/web.php resources/js/pages/Index.vue
```

No other tables or production code paths reference chaos mode. The runtime is fully isolated.

### Laravel Cloud deploy notes

After pulling v2 on Laravel Cloud, run via the cloud UI shell:

```bash
php artisan migrate --force
```

The `--force` flag is required in production environments. Confirm the migration succeeded:

```bash
php artisan migrate:status | grep chaos_sessions
```

---

## What's next (post-demo)

- Session 2 loading: parse `event_range` from the adaptation JSON for any session number, generalize `loadCurrentSessionContext(int $sessionNumber)`.
- Persist session transitions: when `session_complete: true`, runtime creates a new `chaos_sessions` row for Session 2 and seeds it with the prior `world_state` + `session_memory`.
- Optional auth gate / rate limiting if cost becomes a concern.
- Move adaptation source from the JSON export to the actual `session_adaptations` DB table once they're seeded in this environment.
