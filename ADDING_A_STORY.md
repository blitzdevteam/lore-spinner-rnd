# Adding a New Story — Runbook

Documented from the Sherlock Holmes onboarding (May 2026).

---

## Overview

Three pipelines run in sequence:

1. **PDF → TXT** — locally, committed to git
2. **Extraction pipeline** — chapters, events, system prompt, opening (Laravel Cloud CLI)
3. **Adaptation pipeline** — full session architecture (Laravel Cloud CLI, queue-based)

Image generation runs separately after extraction.

---

## Step 1 — Convert PDF to TXT (local)

Place the source PDF under `database/stories/RnD/`.

Run locally via tinker:

```bash
php artisan tinker --execute="
\$parser = new \Smalot\PdfParser\Parser;
\$pdf = \$parser->parseFile(database_path('stories/RnD/YOUR FILE.pdf'));
\$text = \$pdf->getText();
\$text = str_replace([\"\\r\\n\", \"\\r\"], \"\\n\", \$text);
\$text = preg_replace('/^\d+\.?\s*$/m', '', \$text);
\$text = preg_replace(\"/\\n{4,}/\", \"\\n\\n\\n\", \$text);
\$text = implode(\"\\n\", array_map('rtrim', explode(\"\\n\", \$text)));
\$text = mb_trim(\$text) . \"\\n\";
\$out = database_path('stories/YOUR FILE_script.txt');
\Illuminate\Support\Facades\File::put(\$out, \$text);
echo 'Done: ' . basename(\$out) . ' (' . mb_strlen(\$text) . ' bytes)' . PHP_EOL;
"
```

The output TXT file must be named `<TITLE>_script.txt` and saved directly under `database/stories/` (not in a subfolder).

Commit and push the TXT:

```bash
git add "database/stories/YOUR FILE_script.txt"
git commit -m "Add <Title> script TXT (converted from PDF locally)"
git push
```

---

## Step 2 — Configure the Seeder

Edit `database/seeders/AddSingleStorySeeder.php` → `getStoryConfig()`:

```php
return [
    'title'      => 'Your Story Title',
    'slug'       => 'your-story-title',          // Str::slug of title
    'category'   => 'Mystery & Detective',        // must match or create a Category
    'script'     => 'YOUR FILE_script.txt',       // relative to database/stories/
    'source_pdf' => 'RnD/YOUR FILE.pdf',          // only needed as fallback if TXT missing
    'teaser'     => 'One-sentence hook shown on the story card.',
    'rating'     => StoryRatingEnum::TEEN->value, // EVERYONE | TEEN | MATURE
    'opening'    => null,                         // null = AI generates it; or pass HTML string
    'creator'    => [
        'first_name' => 'The Classics, Unbound',
        'last_name'  => '',
        'username'   => 'theclassicsunbound',
        'email'      => 'classics@lorespinner.com',
        'bio'        => "Enter the world's most iconic classic stories...",
        'avatar'     => 'THE CLASSICS, UNBOUND - PROFILE PIC.jpg',
    ],
];
```

Commit and push:

```bash
git add database/seeders/AddSingleStorySeeder.php
git commit -m "Configure AddSingleStorySeeder for <Title>"
git push
```

---

## Step 3 — Run Extraction Pipeline (Laravel Cloud CLI)

```bash
php artisan db:seed --class="Database\Seeders\AddSingleStorySeeder" --force
```

This runs **synchronously** and prints progress for each step:

- PDF → TXT (skipped if TXT already exists)
- Chapter extraction
- Event extraction per chapter
- System prompt generation
- Cinematic opening generation (skipped if `opening` is set in config)
- Story published

Expect 5–20 minutes depending on chapter/event count.

---

## Step 4 — Run Adaptation Pipeline (Laravel Cloud CLI)

Run after extraction prints `Published!`:

```bash
php artisan stories:run-adaptation your-story-slug
```

This dispatches `RunAdaptationPipelineJob` to the `adaptation` queue. The full chain:

```
FormatDetection → IpAudit → StorySessionMap
  └─ per session: EntryPointDiagnosis → SessionArchitecture → ChoiceDesign
                  → ConsequenceMapping → SessionClose → EditorialVerification
AdaptationStatusReconciliationJob
```

Monitor progress by dumping the adaptation JSON:

```bash
php artisan tinker --execute="
\$s = App\Models\Story::where('slug','your-story-slug')->firstOrFail();
\$a = \$s->adaptation()->with('sessionAdaptations')->firstOrFail();
echo json_encode([
  'adaptation_status' => \$a->adaptation_status->value,
  'story_wide' => [
    'format_detection'  => \$a->format_detection,
    'ip_audit'          => \$a->ip_audit,
    'story_session_map' => \$a->story_session_map,
  ],
  'sessions' => \$a->sessionAdaptations->sortBy('session_number')->map(fn(\$s)=>[
    'session_number'        => \$s->session_number,
    'session_status'        => \$s->session_status->value,
    'entry_point_diagnosis' => \$s->entry_point_diagnosis,
    'session_architecture'  => \$s->session_architecture,
    'session_choice_design' => \$s->session_choice_design,
    'choice_consequence_map'=> \$s->choice_consequence_map,
    'session_close_design'  => \$s->session_close_design,
    'editorial_verification'=> \$s->editorial_verification,
  ])->values(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
"
```

To re-run adaptation from scratch (wipes and restarts):

```bash
php artisan stories:run-adaptation your-story-slug --force
```

---

## Step 5 — Image Generation (Laravel Cloud CLI)

Dispatch image jobs for the story and all its chapters:

```bash
php artisan tinker --execute="
\$story = App\Models\Story::where('slug','your-story-slug')->firstOrFail();
dispatch(new App\Jobs\Story\StoryCoverGeneratorJob(\$story))->delay(now());
dispatch(new App\Jobs\Story\StoryBannerGeneratorJob(\$story))->delay(now()->addSeconds(15));
\$story->chapters()->orderBy('position')->each(function(\$ch, \$i) {
  dispatch(new App\Jobs\Chapter\ChapterCoverGeneratorJob(\$ch))->delay(now()->addSeconds(30 + \$i * 15));
});
echo 'All image jobs dispatched for: ' . \$story->title . PHP_EOL;
"
```

Check how many are pending:

```bash
php artisan tinker --execute="
\$pending = DB::table('jobs')->where('payload', 'like', '%CoverGenerator%')->orWhere('payload', 'like', '%BannerGenerator%')->count();
echo 'Pending image jobs: ' . \$pending . PHP_EOL;
"
```

If the worker isn't running automatically, flush the queue manually:

```bash
php artisan queue:work --queue=default --stop-when-empty --tries=3
```

Verify all images attached:

```bash
php artisan tinker --execute="
\$story = App\Models\Story::where('slug','your-story-slug')->with('media','chapters.media')->firstOrFail();
echo 'STORY: ' . \$story->title . PHP_EOL;
echo '  cover:  ' . (\$story->getFirstMediaUrl('cover')  ?: 'MISSING') . PHP_EOL;
echo '  banner: ' . (\$story->getFirstMediaUrl('banner') ?: 'MISSING') . PHP_EOL;
echo PHP_EOL . 'CHAPTERS:' . PHP_EOL;
foreach (\$story->chapters()->orderBy('position')->get() as \$ch) {
  echo '  Ch' . \$ch->position . ' ' . \$ch->title . ': ' . (\$ch->getFirstMediaUrl('cover') ?: 'MISSING') . PHP_EOL;
}
"
```

---

## Wipe and Restart

To delete a story and all its data and start over:

```bash
php artisan stories:wipe --force
```

This wipes **all** stories. There is no per-story wipe command — re-seed selectively if needed.

---

## Key Files

| File | Purpose |
|------|---------|
| `database/seeders/AddSingleStorySeeder.php` | Story config + runs extraction pipeline |
| `app/Console/Commands/RunAdaptationCommand.php` | `stories:run-adaptation` command |
| `app/Console/Commands/GenerateMissingImagesCommand.php` | `images:generate-missing` command |
| `app/Jobs/Adaptation/RunAdaptationPipelineJob.php` | Adaptation pipeline entry point |
| `database/stories/<TITLE>_script.txt` | Converted plaintext script (committed to git) |
