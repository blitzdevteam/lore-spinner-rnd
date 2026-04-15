# Adding a Single Story with Adaptation

## Prerequisites

- Database migrated (`php artisan migrate`)
- Queue workers running (see step 3)
- `.env` configured with OpenAI/AI credentials

---

## Step-by-step

### 1. Run migrations (if not already done)

```bash
php artisan migrate
```

This creates the `story_adaptations`, `session_adaptations` tables and adds `session_number` to `events` and runtime state columns to `games`.

### 2. (Optional) Wipe existing stories

Only run this if you want a clean slate. It requires explicit confirmation.

```bash
php artisan stories:wipe
```

Or skip the confirmation prompt:

```bash
php artisan stories:wipe --force
```

This removes all stories and their related data (games, prompts, chapters, events, adaptations, media).

### 3. Seed the story

```bash
php artisan db:seed --class=AddSingleStorySeeder --force
```

This adds a new story and runs the canon extraction pipeline synchronously:
- Chapter extraction
- Event extraction (per chapter)
- System prompt generation
- Cinematic opening generation
- Cover attachment

The story is published at the end.

### 4. Start queue workers

You need **two queue workers** — one for the default queue and one for the adaptation queue:

```bash
# Terminal 1 — default queue (existing jobs)
php artisan queue:listen --tries=1

# Terminal 2 — adaptation queue
php artisan queue:work --queue=adaptation --tries=1 --timeout=300
```

Or run both in one command:

```bash
npx concurrently \
  "php artisan queue:listen --tries=1" \
  "php artisan queue:work --queue=adaptation --tries=1 --timeout=300" \
  --names=default,adaptation --kill-others
```

### 5. Trigger the adaptation pipeline

#### Option A: From Filament UI

1. Go to the Creator panel → Stories → View the story
2. Click **"Run Adaptation"** in the header actions
3. Watch the status update from PENDING → FORMAT_DETECTION → ... → COMPLETED

#### Option B: From Tinker

```bash
php artisan tinker
```

```php
use App\Models\Story;
use App\Jobs\Adaptation\RunAdaptationPipelineJob;

$story = Story::latest()->first();
RunAdaptationPipelineJob::dispatch($story);
```

#### Option C: Already automatic on story creation

If the story was created via the Filament **Create Story** form with a script upload, the adaptation pipeline is dispatched automatically with a 2-minute delay.

The seeder does **not** auto-dispatch adaptation (it runs synchronously and bypasses Filament).

### 6. Monitor progress

#### Filament UI

Stories → View Story → **Adaptation** section shows:
- Overall status badge
- Per-session progress

#### Queue output

Watch the adaptation worker terminal for phase progression:

```
Processing: App\Jobs\Adaptation\FormatDetectionJob
Processing: App\Jobs\Adaptation\IpAuditJob
Processing: App\Jobs\Adaptation\StorySessionMapJob
Processing: App\Jobs\Adaptation\EntryPointDiagnosisJob  (×N sessions)
...
Processing: App\Jobs\Adaptation\AdaptationStatusReconciliationJob
```

#### Tinker check

```php
use App\Models\Story;

$story = Story::latest()->first();
$story->adaptation?->adaptation_status;           // AdaptationStatusEnum
$story->adaptation?->sessionAdaptations->pluck('session_status', 'session_number');
```

### 7. Re-run adaptation (if needed)

#### Filament UI

Click **"Re-run Adaptation"** on the story view page. This forces a full idempotent reset and re-run.

#### Tinker

```php
use App\Models\Story;
use App\Jobs\Adaptation\RunAdaptationPipelineJob;

$story = Story::latest()->first();
RunAdaptationPipelineJob::dispatch($story, force: true);
```

The `force: true` flag atomically clears all existing adaptation data before re-running.

---

## Full single-command flow (fresh start)

```bash
# 1. Migrate
php artisan migrate

# 2. (Optional) Wipe existing stories
php artisan stories:wipe --force

# 3. Seed the story (sync — canon extraction)
php artisan db:seed --class=AddSingleStorySeeder --force

# 4. Start workers (background)
php artisan queue:work --queue=adaptation --tries=1 --timeout=300 &

# 5. Dispatch adaptation
php artisan tinker --execute="
  use App\Models\Story;
  use App\Jobs\Adaptation\RunAdaptationPipelineJob;
  RunAdaptationPipelineJob::dispatch(Story::latest()->first());
"

# 6. Check result after a few minutes
php artisan tinker --execute="
  use App\Models\Story;
  \$s = Story::latest()->first();
  echo \$s->adaptation?->adaptation_status->value ?? 'no adaptation';
"
```

---

## Exporting results

### CLI (saves to `database/exports/` — git-trackable, survives deploys)

```bash
# Export latest story (CSV + JSON to database/exports/)
php artisan adaptation:export

# Export by story ID
php artisan adaptation:export 1

# Export by slug
php artisan adaptation:export alices-adventures-in-wonderland

# Custom output directory
php artisan adaptation:export --path=/tmp/adaptation-exports
```

This produces two timestamped files:

| File | Contents |
|---|---|
| `adaptation-{slug}-{timestamp}.csv` | Overview: story status + per-session phase completion grid |
| `adaptation-{slug}-{timestamp}.json` | Full dump: every phase output as structured JSON |

The JSON file contains the complete adaptation artifacts — format detection, IP audit, session map, branch dimensions, beat maps, choice designs, consequence maps, etc. You can diff it across runs to see what changed.

On Laravel Cloud, run this from the console and then pull the exports locally via git or download from the Cloud file browser.

### Filament Dashboard (browser download)

On the story view page:
- **Adaptation** section shows overall status and session progress
- **Story-Wide Phases** section (collapsed) shows Phase 0-2 outputs as formatted JSON
- **Per-Session Phases** section (collapsed) shows Phase 3-8 outputs per session
- **Export JSON** button — downloads the full adaptation dump directly to your browser
- **Export CSV** button — downloads the status overview grid directly to your browser

These are real browser downloads — no server-side file storage needed.

### Laravel log

All phase transitions and errors are logged to `storage/logs/laravel.log`. Use Laravel Pail for live tailing:

```bash
php artisan pail --filter="Adaptation"
```

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Adaptation stays PENDING | Adaptation worker not running | Start `queue:work --queue=adaptation` |
| Story has no chapters/events | Seeder failed mid-extraction | Re-run seeder: `db:seed --class=AddSingleStorySeeder --force` |
| Session stuck at a phase | AI agent timeout or API error | Check `failed_jobs` table, then re-run with `force: true` |
| Runtime ignores adaptation | Session not COMPLETED | Check `session_adaptations.session_status` — only COMPLETED sessions feed into runtime |
| PARTIAL_COMPLETION status | Some sessions failed | Check which sessions failed, fix or re-run full pipeline with force |
