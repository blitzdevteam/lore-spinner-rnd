# Adding a Story to Chaos Mode

Runbook for enabling an existing LoreSpinner story in **Chaos Mode** (`/chaos-mode`).

Chaos Mode is separate from the main Story Guard runtime. A story can be fully published in the main app and still appear as **coming soon** in Chaos until you complete the steps below.

For the base story pipeline (PDF → script → events → adaptation), start with the repo root runbook:

**[ADDING_A_STORY.md](../../ADDING_A_STORY.md)**

---

## How Chaos Mode picks stories

```text
ChaosStoryConfig (code whitelist)
        +
Story in DB + StoryAdaptation + SessionAdaptation rows
        +
Events stamped with session_number (from adaptation pipeline)
        =
Story shows as "available" and can start
```

| Layer | What it controls |
|--------|------------------|
| **Database** | Script, events, adaptation spine, cold opens, beat maps, `events.session_number` |
| **`ChaosStoryConfig`** | Slug whitelist, UI title, protagonist name, voice partial path, tagline, TTS voice |
| **Voice partial** | Narrator voice, world rules, characters, prose style (creative only) |
| **Shared templates** | `system-prompt.blade.php`, `turn-prompt.blade.php`, AI agents — no per-story edits needed |

The landing page builds the story list from `ChaosStoryConfig::all()` and marks each entry **available** when:

- a `stories` row exists with the same `slug`
- `story_adaptations` exists for that story
- at least one `session_adaptations` row exists

If DB data is missing, the story still appears in the dropdown but is disabled (**coming soon**).

---

## Prerequisites

- Story already seeded and published (or ready to publish) via `AddSingleStorySeeder`
- Adaptation pipeline completed: `php artisan stories:run-adaptation your-story-slug`
- Events have `session_number` set (done automatically by `StorySessionMapJob` during adaptation)
- Laravel Cloud CLI access for verification commands (optional locally if DB is synced)

---

## Step 1 — Confirm the story exists in the database

On Laravel Cloud (or local with production DB):

```bash
php artisan tinker --execute="
\$s = App\Models\Story::where('slug','your-story-slug')->firstOrFail();
\$a = \$s->adaptation()->with('sessionAdaptations')->first();
echo 'Story: ' . \$s->title . PHP_EOL;
echo 'Adaptation: ' . (\$a ? 'yes' : 'NO') . PHP_EOL;
echo 'Session adaptations: ' . (\$a?->sessionAdaptations->count() ?? 0) . PHP_EOL;
echo 'Events with session_number: ' . \$s->events()->whereNotNull('session_number')->count() . PHP_EOL;
"
```

You need:

- **Adaptation: yes**
- **Session adaptations: ≥ 1**
- **Events with session_number: > 0** (ideally all playable events)

If adaptation is missing, run (from [ADDING_A_STORY.md](../../ADDING_A_STORY.md)):

```bash
php artisan stories:run-adaptation your-story-slug
```

Re-run from scratch if needed:

```bash
php artisan stories:run-adaptation your-story-slug --force
```

**Important:** Chaos loads the full session script by filtering events on `events.session_number`, not by hardcoded position ranges. If `session_number` is null on events, start will fail with *"This story has no events to narrate yet."*

---

## Step 2 — Register the story in `ChaosStoryConfig`

Edit:

`app/ChaosMode/ChaosStoryConfig.php`

Add one entry to the `all()` array:

```php
[
    'slug'          => 'your-story-slug',           // MUST match stories.slug in DB
    'title'         => 'Your Story Title',          // Chaos landing + in-game header
    'protagonist'   => 'Frodo',                     // Player label + agency handoff
    'tagline'       => 'Author — one-line hook for the selector.',
    'tts_voice_id'  => null,                        // null = .env ELEVENLABS default
],
```

> **V2 note (2026-05-24):** the legacy `voice_partial` key has been removed.
> Voice now comes from the Voice Lock + Runtime Narrator Template pipeline
> output. Adding a story to Chaos Mode now requires running
> `php artisan stories:run-adaptation <slug>` and confirming every
> `session_adaptations.runtime_narrator_prompt` is non-null before the story
> becomes playable.

### Field reference

| Field | Required | Notes |
|--------|----------|--------|
| `slug` | Yes | Exact match to `stories.slug`. Used by API validation and TTS lookup. |
| `title` | Yes | Display name on `/chaos-mode`. |
| `protagonist` | Yes | Shown on player turns; used in turn prompt (`[PROTAGONIST]: action`) and open questions ("What does Frodo do?"). |
| `tagline` | Yes | Italic subtitle under story selector. |
| `tts_voice_id` | No | ElevenLabs voice ID. `null` uses `config('services.elevenlabs.voice_id')`. Applies to **both** Chaos Mode TTS and main Story Guard TTS when this slug is registered. |

### TTS voice constants

To reuse a shared voice across several stories, add a private constant (see `VOICE_DECLAN_SAGE` in the same file) and reference it:

```php
private const VOICE_MY_NARRATOR = 'your-elevenlabs-voice-id';

// in the story entry:
'tts_voice_id' => self::VOICE_MY_NARRATOR,
```

Current convention in repo:

- **Alice, Sherlock, Tell-Tale Heart, Snow Queen** → `null` (default narrator from `.env`)
- **Nocturne, Anima Machina, Driftheart** → Declan Sage (`kqVT88a5QfII1HNAEPTJ`)

---

## Step 3 — Write the voice partial (creative)

Create:

`resources/views/ai/agents/chaos/partials/your-story.blade.php`

Copy structure from an existing partial, for example:

- `alice.blade.php` — literary third-person, whimsical logic
- `sherlock.blade.php` — Watson first-person, Victorian detective
- `telltale.blade.php` — Poe first-person, defensive crescendo
- `nocturne.blade.php` — clinical thriller, restrained
- `anima-machina.blade.php` — wet neon, HUD-adjacent texture
- `driftheart.blade.php` — kinetic space opera
- `snow-queen.blade.php` — Andersen fairy tale, direct address, Nordic cold

### What belongs in the partial

- Narrator identity and whose shoes the player wears
- World physics / rules (what can happen, what cannot)
- Major characters (names, roles, voice)
- Full story arc summary (**for model confidence only** — do not narrate future sessions early)
- **Prose style (binding)** section with hard bans (no AI filler: "delve", "tapestry", "resonates", etc.)

### What does NOT belong in the partial

- Cold open text (comes from `SessionAdaptation.entry_point_diagnosis.cold_open` in DB)
- Beat map, dramatic question, authored choices (comes from DB session packet)
- Event script bodies (comes from `events` table, filtered by `session_number`)

The dispatcher `resources/views/ai/agents/chaos/system-prompt.blade.php` includes your partial, then appends the dynamic session packet and full session script from the database.

---

## Step 4 — Commit and deploy

```bash
git add app/ChaosMode/ChaosStoryConfig.php resources/views/ai/agents/chaos/partials/your-story.blade.php
git commit -m "Enable Chaos Mode for <Title>"
git push
```

No new routes or migrations are required for a new chaos story (unless `chaos_sessions` table is not yet migrated on the environment).

---

## Step 5 — Verify on `/chaos-mode`

1. Open `/chaos-mode`.
2. Confirm the story appears in the **Story** dropdown and is **not** greyed out.
3. Select a narrator model and click **Begin the Adventure**.
4. Confirm opening narration returns and three choices appear.
5. Send a free-text action; confirm the turn completes.
6. Optional: tap **Listen** on a narrator turn and confirm TTS uses the expected voice.

### API smoke test (optional)

```bash
# Replace slug and ensure CSRF/session as needed for your environment
curl -X POST https://your-app.test/chaos-mode/start \
  -H "Content-Type: application/json" \
  -d '{"story_slug":"your-story-slug","model":"gpt-5.2"}'
```

---

## Multi-session stories

If the adaptation has multiple sessions (e.g. session 1, 2, 3):

- Chaos starts at **session 1** on **Begin the Adventure**.
- When the AI returns `session_complete: true`, the UI offers **Continue to Session N+1**.
- `POST /chaos-mode/continue` creates a new `chaos_sessions` row at `story_session_number + 1`, carrying `world_state` and `session_memory` forward.
- The opener uses `arc_progression[n].opens_with` from `story_session_map` when present; otherwise the next session's cold open.

No extra config per session — only DB adaptation rows and event `session_number` values.

---

## Files you do not need to change

| File / area | Why |
|-------------|-----|
| `routes/web.php` | Same routes for all chaos stories |
| `resources/js/pages/ChaosMode.vue` | Story list from backend `stories` prop |
| `ChaosModeController` session loading | Generic DB-driven `loadSessionContext()` |
| `ChaosNarrationAgent*.php` | Shared structured output schema |
| `system-prompt.blade.php` | Shared dispatcher (unless changing global chaos rules) |

---

## Optional: homepage marketing

To mention the new title on the homepage chaos feature block, edit:

`resources/js/pages/Index.vue`

This is cosmetic only; playability does not depend on it.

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|----------------|-----|
| Story greyed out (**coming soon**) | No adaptation or no session adaptations | Run `stories:run-adaptation` |
| Start returns 422 *no events to narrate* | Events missing `session_number` | Re-run adaptation; verify `StorySessionMapJob` completed |
| Start returns 422 *no adaptation* | `story_adaptations` row missing | Run adaptation pipeline |
| Start returns 422 *Unknown story* | Slug not in `ChaosStoryConfig` or typo | Add/fix config entry; slug must match DB |
| Wrong narrator voice (TTS) | `tts_voice_id` or cached audio | Set voice in config; clear `storage/app/tts/` for that prompt if testing main mode |
| Narrator sounds generic / wrong tone | Weak or missing voice partial | Expand partial prose-style section; read script for voice reference |
| Session 2 does not continue | AI never set `session_complete: true` | Play until arc lands; or check adaptation `session_close_design` |
| Continue fails 410 | No more sessions in adaptation | Expected at story end; `total_sessions` exhausted |

### Inspect a chaos playthrough

```bash
php artisan tinker --execute="
dump(App\Models\ChaosSession::latest()->first()?->only([
  'id','story_id','story_session_number','session_complete','turn_count'
]));
"
```

---

## Quick checklist

- [ ] Story in DB with final `slug`
- [ ] `php artisan stories:run-adaptation <slug>` completed
- [ ] Events have `session_number` populated
- [ ] Entry added to `ChaosStoryConfig::all()`
- [ ] `resources/views/ai/agents/chaos/partials/<name>.blade.php` written
- [ ] `tts_voice_id` set (or `null` for default)
- [ ] Committed and deployed
- [ ] Verified on `/chaos-mode` (start + one turn + optional TTS)

---

## Registered stories

All stories currently enabled in `ChaosStoryConfig::all()`:

| Title | Slug | Protagonist | Chapters | Events | Sessions | Partial | TTS voice |
|-------|------|-------------|----------|--------|----------|---------|-----------|
| Alice's Adventures in Wonderland | `alices-adventures-in-wonderland` | Alice | 7 | 90 | 4 | `alice` | default |
| The Adventure of the Speckled Band | `the-adventure-of-the-speckled-band` | Watson | 5 | 32 | 2 | `sherlock` | default |
| The Tell-Tale Heart | `the-tell-tale-heart` | the Narrator | 4 | 13 | 1 | `telltale` | default |
| Driftheart | `driftheart` | Kataria | 5 | 33 | 2 | `driftheart` | Declan Sage |
| Nocturne | `nocturne` | Akira | 6 | 30 | 2 | `nocturne` | Declan Sage |
| Anima Machina | `anima-machina` | Nora | 7 | 44 | 3 | `anima-machina` | Declan Sage |
| The Snow Queen | `the-snow-queen` | Gerda | 6 | 61 | 4 | `snow-queen` | default |

---

## Related docs

| Document | Purpose |
|----------|---------|
| [ADDING_A_STORY.md](../../ADDING_A_STORY.md) | Full story ingest: PDF, seeder, events, adaptation, images |
| [../chaos-mode-process-log.md](../chaos-mode-process-log.md) | Chaos architecture, revert instructions, v2 design notes |
