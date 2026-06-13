# Chaos V2 Command List

Use these from the Laravel Cloud command UI or an app shell at the repo root. Replace `alices-adventures-in-wonderland` with another story slug when needed.

## Common Slugs

```bash
alices-adventures-in-wonderland
the-adventure-of-the-speckled-band
the-tell-tale-heart
nocturne
anima-machina
driftheart
the-snow-queen
```

## Preflight

```bash
pwd
```

```bash
git status -sb
```

```bash
php artisan migrate:status
```

```bash
php artisan queue:work --once --queue=adaptation
```

## Full Adaptation Pipeline

Run the complete V2/V2.1 adaptation pipeline for a story.

```bash
php artisan stories:run-adaptation alices-adventures-in-wonderland --force
```

Drain adaptation jobs in a worker pane.

```bash
php artisan queue:work --queue=adaptation --timeout=600
```

One-shot worker check.

```bash
php artisan queue:work --once --queue=adaptation
```

## Validation Runner

Canonical runner shape:

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" stepN alices-adventures-in-wonderland
```

Schema and enum check:

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step1
```

Model cast check:

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step2
```

Blade render probe:

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step3
```

V2.2 integration probe (pipeline order, 1A/1B blades, Paul Review markers):

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step_v22
```

Full V2.2 Cloud validation guide: [pipeline-upgrade-v2-2-validation-runbook.md](validation/pipeline-upgrade-v2-2-validation-runbook.md)

Runtime template token render:

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step4
```

Alignment leak scan:

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step5
```

Persisted story adaptation outputs:

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step6 alices-adventures-in-wonderland
```

Per-session adaptation outputs and cached prompts:

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step7 alices-adventures-in-wonderland
```

Prompt size budget:

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step8 alices-adventures-in-wonderland
```

Hard-ban scan:

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step9 alices-adventures-in-wonderland
```

Unadapted story 422 probe:

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step13 the-tell-tale-heart
```

Reconciliation probe:

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step14 alices-adventures-in-wonderland
```

## Dump Rendered Chaos Prompt

Dump session 1:

```bash
php artisan chaos:dump-prompt alices-adventures-in-wonderland --session=1
```

Dump a later session:

```bash
php artisan chaos:dump-prompt alices-adventures-in-wonderland --session=3
```

## Reassemble Cached Runtime Prompts Only

Use after changing `RuntimeNarratorTemplateBuilder` or `runtime-narrator-template.blade.php`. This does not rerun adaptation agents.

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->with(["adaptation","adaptation.sessionAdaptations"])->firstOrFail(); $builder=app(App\Ai\Adaptation\RuntimeNarratorTemplateBuilder::class); foreach($story->adaptation->sessionAdaptations()->orderBy("session_number")->get() as $sa){ $prompt=$builder->build($story,$sa); $sa->update(["runtime_narrator_prompt"=>$prompt,"runtime_narrator_assembled_at"=>now()]); echo "session ".$sa->session_number.": ".strlen($prompt)." bytes".PHP_EOL; }'
```

Reassemble one session only:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->with(["adaptation","adaptation.sessionAdaptations"])->firstOrFail(); $sa=$story->adaptation->sessionAdaptations()->where("session_number",3)->firstOrFail(); $builder=app(App\Ai\Adaptation\RuntimeNarratorTemplateBuilder::class); $prompt=$builder->build($story,$sa); $sa->update(["runtime_narrator_prompt"=>$prompt,"runtime_narrator_assembled_at"=>now()]); echo "session ".$sa->session_number.": bytes=".strlen($prompt)." chars=".mb_strlen($prompt).PHP_EOL;'
```

## Prompt Assembly Verification

Verify the major prompt assembly fixes across all sessions:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->with(["adaptation","adaptation.sessionAdaptations"])->firstOrFail(); foreach($story->adaptation->sessionAdaptations()->orderBy("session_number")->get() as $sa){ $p=(string)$sa->runtime_narrator_prompt; echo "session ".$sa->session_number.": bytes=".strlen($p)." chars=".mb_strlen($p)." has_entities=".(str_contains($p,"&#039;")?"yes":"no")." full_protagonist_symbolic=".(str_contains($p,"start of the story.\x27s symbolic")||str_contains($p,"start of the story.&#039;s symbolic")?"yes":"no")." section5_scene_rules=".(str_contains($p,"SCENE-SPECIFIC STORYGUARD RULES")?"yes":"no")." active_state_schema=".(str_contains($p,"ACTIVE IN THIS SESSION")?"yes":"no")." dormant_keys=".(str_contains($p,"DORMANT FUTURE")?"yes":"no")." chapter_labels=".(str_contains($p,"--- CHAPTER ")?"yes":"no")." editorial_unverified=".(str_contains($p,"UNVERIFIED")?"yes":"no").PHP_EOL; }'
```

Measure key section sizes:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->with(["adaptation","adaptation.sessionAdaptations"])->firstOrFail(); foreach($story->adaptation->sessionAdaptations()->orderBy("session_number")->get() as $sa){ $p=(string)$sa->runtime_narrator_prompt; preg_match("/=== SECTION 10 .*?=== SECTION 11/s",$p,$s10); preg_match("/=== SECTION 12 .*?=== SECTION 13/s",$p,$s12); preg_match("/=== SECTION 14 .*?=== SECTION 15/s",$p,$s14); preg_match("/=== SECTION 15 .*?=== SECTION 16/s",$p,$s15); echo "session ".$sa->session_number.": chars=".mb_strlen($p)." section10=".mb_strlen($s10[0]??"")." section12=".mb_strlen($s12[0]??"")." section14=".mb_strlen($s14[0]??"")." section15=".mb_strlen($s15[0]??"")." compressed_middle=".(str_contains($p,"content compressed to save context budget")?"yes":"no")." titles_only=".(str_contains($p,"source compressed to title + objective only")?"yes":"no").PHP_EOL; }'
```

Verify cold open source and cached prompt health:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->with(["adaptation","adaptation.sessionAdaptations"])->first(); $sa=$story->adaptation->sessionAdaptations->firstWhere("session_number",1); $arc=collect($story->adaptation->story_session_map["arc_progression"] ?? [])->firstWhere("session_number",1); echo "arc opens_with: ".json_encode($arc["opens_with"] ?? null).PHP_EOL; echo "entry cold_open exists: ".(!empty($sa->entry_point_diagnosis["cold_open"] ?? null) ? "yes" : "no").PHP_EOL; echo "entry cold_open preview: ".substr((string)($sa->entry_point_diagnosis["cold_open"] ?? ""),0,240).PHP_EOL; echo "cached has full protagonist possessive: ".(str_contains((string)$sa->runtime_narrator_prompt,"start of the story.&#039;s symbolic") || str_contains((string)$sa->runtime_narrator_prompt,"start of the story.\x27s symbolic") ? "yes" : "no").PHP_EOL; echo "cached has html entities: ".(str_contains((string)$sa->runtime_narrator_prompt,"&#039;") ? "yes" : "no").PHP_EOL;'
```

## Section 12 Event Diagnostics

Dump actual Section 12 event rows for Alice session 1:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail(); $all=App\Models\Event::query()->join("chapters","chapters.id","=","events.chapter_id")->where("chapters.story_id",$story->id)->orderBy("chapters.position")->orderBy("events.position")->get(["events.id","events.chapter_id","events.position","events.title","events.objectives","events.content","events.session_number","chapters.position as chapter_position","chapters.title as chapter_title"])->values()->map(function($e,$i){$e->story_order=$i+1; return $e;}); $s1=$all->where("session_number",1)->values(); echo "session_1_count: ".$s1->count().PHP_EOL; echo "duplicate_db_ids: ".($s1->pluck("id")->duplicates()->values()->implode(",") ?: "none").PHP_EOL; echo "session_1_global_range: ".$s1->pluck("story_order")->min()."-".$s1->pluck("story_order")->max().PHP_EOL; echo "non_contiguous_global_order: ".(($s1->pluck("story_order")->values()->all() === range($s1->pluck("story_order")->min(),$s1->pluck("story_order")->max())) ? "no" : "yes").PHP_EOL; foreach($s1 as $e){ $label="EVENT ".$e->position; $namespaced="CHAPTER ".$e->chapter_position." / EVENT ".$e->position; $content=(string)$e->content; $excerpt=substr(preg_replace("/\s+/"," ",$content),0,90); echo implode(" | ",["rendered_label=".$label,"suggested_label=".$namespaced,"db_id=".$e->id,"chapter_id=".$e->chapter_id,"chapter=".$e->chapter_position.": ".$e->chapter_title,"local_event_position=".$e->position,"story_order=".$e->story_order,"session=".$e->session_number,"title=".$e->title,"objective_len=".strlen((string)$e->objectives),"source_len=".strlen($content),"excerpt=".$excerpt]).PHP_EOL; }'
```

## Inspect Stored Pipeline Outputs

Story-level adaptation output keys:

```bash
php artisan tinker --execute='$a=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail()->adaptation; foreach(["ip_trimming","format_detection","ip_audit","voice_profile","story_session_map"] as $k){ echo $k.": ".(!empty($a->{$k})?"present":"missing").PHP_EOL; } echo "status: ".$a->adaptation_status->value.PHP_EOL;'
```

Session-level output keys:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->with(["adaptation.sessionAdaptations"])->firstOrFail(); foreach($story->adaptation->sessionAdaptations()->orderBy("session_number")->get() as $s){ echo "session ".$s->session_number." status=".$s->session_status->value.PHP_EOL; foreach(["entry_point_diagnosis","session_architecture","session_choice_design","choice_consequence_map","session_close_design","editorial_verification","runtime_narrator_prompt"] as $k){ echo "  ".$k.": ".(!empty($s->{$k})?"yes":"no").PHP_EOL; } }'
```

Inspect one session's entry-point diagnosis:

```bash
php artisan tinker --execute='$s=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail()->adaptation->sessionAdaptations()->where("session_number",1)->firstOrFail(); dump($s->entry_point_diagnosis);'
```

Inspect one session's choice design:

```bash
php artisan tinker --execute='$s=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail()->adaptation->sessionAdaptations()->where("session_number",1)->firstOrFail(); dump($s->session_choice_design);'
```

Inspect one session's consequence map:

```bash
php artisan tinker --execute='$s=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail()->adaptation->sessionAdaptations()->where("session_number",1)->firstOrFail(); dump($s->choice_consequence_map);'
```

Inspect editorial verdicts:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->with(["adaptation.sessionAdaptations"])->firstOrFail(); foreach($story->adaptation->sessionAdaptations()->orderBy("session_number")->get() as $s){ echo "session ".$s->session_number.": ".data_get($s->editorial_verification,"final_verdict.production_status","missing").PHP_EOL; }'
```

## Dispatch One Pipeline Phase

Use these only when the earlier dependencies for that phase already exist.

Entry point diagnosis for one session:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail(); App\Jobs\Adaptation\EntryPointDiagnosisJob::dispatch($story,1)->onQueue("adaptation"); echo "dispatched entry point diagnosis session 1".PHP_EOL;'
```

Session architecture for one session:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail(); App\Jobs\Adaptation\SessionArchitectureJob::dispatch($story,1)->onQueue("adaptation"); echo "dispatched session architecture session 1".PHP_EOL;'
```

Choice design for one session:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail(); App\Jobs\Adaptation\ChoiceDesignJob::dispatch($story,1)->onQueue("adaptation"); echo "dispatched choice design session 1".PHP_EOL;'
```

Consequence mapping for one session:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail(); App\Jobs\Adaptation\ConsequenceMappingJob::dispatch($story,1)->onQueue("adaptation"); echo "dispatched consequence mapping session 1".PHP_EOL;'
```

Session close for one session:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail(); App\Jobs\Adaptation\SessionCloseJob::dispatch($story,1)->onQueue("adaptation"); echo "dispatched session close session 1".PHP_EOL;'
```

Editorial verification for one session:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail(); App\Jobs\Adaptation\EditorialVerificationJob::dispatch($story,1)->onQueue("adaptation"); echo "dispatched editorial verification session 1".PHP_EOL;'
```

Runtime narrator assembly for one session:

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail(); App\Jobs\Adaptation\RuntimeNarratorAssemblyJob::dispatch($story,1)->onQueue("adaptation"); echo "dispatched runtime narrator assembly session 1".PHP_EOL;'
```

## Chaos Runtime Probes

Start Chaos Mode with Anthropic model:

```bash
curl -sS -X POST "https://<host>/chaos-mode/start" -H "X-Inertia: true" -d "story_slug=alices-adventures-in-wonderland" -d "model=claude-sonnet-4-6"
```

Continue a completed session:

```bash
curl -sS -X POST "https://<host>/chaos-mode/continue" -H "X-Inertia: true" -d "session_id=<chaos_session_ulid>" -d "model=claude-sonnet-4-6"
```

Send a turn:

```bash
curl -sS -X POST "https://<host>/chaos-mode/turn" -H "X-Inertia: true" -d "session_id=<chaos_session_ulid>" -d "player_action=I follow the Rabbit at once." -d "model=claude-sonnet-4-6"
```

## Inspect Chaos Runtime State

Latest Chaos session summary:

```bash
php artisan tinker --execute='$s=App\Models\ChaosSession::latest()->first(); echo "id: ".$s->id.PHP_EOL; echo "story_session_number: ".$s->story_session_number.PHP_EOL; echo "turn_count: ".$s->turn_count.PHP_EOL; echo "session_complete: ".($s->session_complete?"yes":"no").PHP_EOL; echo "is_climactic_choice: ".($s->is_climactic_choice?"yes":"no").PHP_EOL; echo "defining_choice_id: ".($s->defining_choice_id ?? "null").PHP_EOL; echo "defining_choice_line: ".($s->defining_choice_line ?? "null").PHP_EOL;'
```

Latest Chaos world state:

```bash
php artisan tinker --execute='$s=App\Models\ChaosSession::latest()->first(); dump($s->world_state);'
```

Latest Chaos alignment:

```bash
php artisan tinker --execute='$s=App\Models\ChaosSession::latest()->first(); dump($s->alignment_scaffold);'
```

Latest Chaos symbolic/session memory:

```bash
php artisan tinker --execute='$s=App\Models\ChaosSession::latest()->first(); echo "session_memory: ".($s->session_memory ?? "null").PHP_EOL; echo "symbolic_memory: ".($s->symbolic_memory ?? "null").PHP_EOL;'
```

## Wiping / Resetting Runtime Data

Destructive: delete Chaos runtime sessions for one story. Does not delete adaptation outputs.

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail(); $count=App\Models\ChaosSession::where("story_id",$story->id)->delete(); echo "deleted chaos sessions: ".$count.PHP_EOL;'
```

Destructive: clear cached runtime narrator prompts only. Use when you want Chaos Mode to fail readiness until reassembly.

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail(); $count=$story->adaptation->sessionAdaptations()->update(["runtime_narrator_prompt"=>null,"runtime_narrator_assembled_at"=>null]); echo "cleared prompts: ".$count.PHP_EOL;'
```

Destructive: reset all event session assignments for one story. Only do this before rerunning the session map/adaptation pipeline.

```bash
php artisan tinker --execute='$story=App\Models\Story::where("slug","alices-adventures-in-wonderland")->firstOrFail(); $count=$story->events()->update(["session_number"=>null]); echo "cleared event session numbers: ".$count.PHP_EOL;'
```

## Export / Review Adaptation Data

If available in the environment, export adaptation data:

```bash
php artisan adaptation:export alices-adventures-in-wonderland
```

If the command name differs, list artisan commands and search for adaptation-related exports:

```bash
php artisan list
```
