Duration:
=== ANIMA MACHINA TIMING (story id 24) ===

STORY
  created_at:    2026-06-16 06:25:15 UTC
  published_at:  2026-06-16 06:25:15 UTC
  updated_at:    2026-06-16 06:32:26 UTC
  status:        published
  seed wall-clock: 7 minutes (created → updated_at)

CHAPTERS (6)
  first chapter created: 2026-06-16 06:25:30 UTC
  last chapter updated:  2026-06-16 06:32:11 UTC
  chapter span: 6 minutes

EVENTS (90)
  first event created: 2026-06-16 06:25:33 UTC
  last event updated:  2026-06-16 06:50:20 UTC
  event span: 24 minutes

ADAPTATION
  created_at:  2026-06-16 06:40:32 UTC
  updated_at:  2026-06-16 07:35:34 UTC
  status:      partial-completion
  runtime prompts: 5/5
  last runtime assembled: 2026-06-16 07:42:32 UTC
  adaptation wall-clock: 1 hour (adaptation created → last runtime)
    S1: completed | runtime=yes | 2026-06-16 07:41:49 UTC
    S2: completed | runtime=yes | 2026-06-16 07:42:10 UTC
    S3: completed | runtime=yes | 2026-06-16 07:42:10 UTC
    S4: completed | runtime=yes | 2026-06-16 07:42:13 UTC
    S5: completed | runtime=yes | 2026-06-16 07:42:32 UTC

END-TO-END
  story created → last runtime: 1 hour
  pending adaptation jobs: 0

  --------------------------------------
A. Preflight — is the pipeline done?
php artisan tinker --execute='
$story = App\Models\Story::where("slug","anima-machina")->firstOrFail();
$a = $story->adaptation;
echo "status: ".$a->adaptation_status->value.PHP_EOL;
echo "voice_profile: ".(!empty($a->voice_profile) ? "present" : "MISSING").PHP_EOL;
echo "sessions: ".$a->sessionAdaptations->count().PHP_EOL;
echo "pending jobs: ".DB::table("jobs")->where("queue","adaptation")->count().PHP_EOL;
'




status: completed
voice_profile: present
sessions: 5
pending jobs: 0
==========

B. Session grid (runtime prompts = playable in Chaos):

php artisan tinker --execute='
$story = App\Models\Story::where("slug","anima-machina")->firstOrFail();
$a = $story->adaptation;
$ready = 0;
foreach ($a->sessionAdaptations()->orderBy("session_number")->get() as $s) {
  $rp = $s->runtime_narrator_prompt ?? "";
  if (strlen($rp) > 0) $ready++;
  echo "S{$s->session_number}: {$s->session_status->value}"
    . " entry=".(!empty($s->entry_point_diagnosis) ? "y" : "n")
    . " arch=".(!empty($s->session_architecture) ? "y" : "n")
    . " choices=".(!empty($s->session_choice_design) ? "y" : "n")
    . " conseq=".(!empty($s->choice_consequence_map) ? "y" : "n")
    . " close=".(!empty($s->session_close_design) ? "y" : "n")
    . " editorial=".(!empty($s->editorial_verification) ? "y" : "n")
    . " runtime=".(strlen($rp) > 0 ? strlen($rp)."ch" : "MISSING")
    . PHP_EOL;
}
echo "v2_ready: {$ready}/".$a->sessionAdaptations->count().PHP_EOL;
'
Wait until pending jobs: 0 and v2_ready matches session count before exporting for final QA.


S1: completed entry=y arch=y choices=y conseq=y close=y editorial=y runtime=129002ch
S2: completed entry=y arch=y choices=y conseq=y close=y editorial=y runtime=116057ch
S3: completed entry=y arch=y choices=y conseq=y close=y editorial=y runtime=124536ch
S4: completed entry=y arch=y choices=y conseq=y close=y editorial=y runtime=123398ch
S5: completed entry=y arch=y choices=y conseq=y close=y editorial=y runtime=111647ch
v2_ready: 5/5
==========

C. Deliverable 1B gates — format + voice profile

Format detection (must be screenplay → 1B path, not 1A):

php artisan tinker --execute='
$a = App\Models\Story::where("slug","anima-machina")->firstOrFail()->adaptation;
$fd = $a->format_detection ?? [];
echo "detected_format: ".($fd["detected_format"] ?? $fd["type"] ?? "MISSING").PHP_EOL;
echo "evidence: ".Illuminate\Support\Str::limit($fd["evidence"] ?? "", 200).PHP_EOL;
'


detected_format: SCREENPLAY
evidence: Page 1 contains clear screenplay formatting, including the transition "FADE IN:", the scene slugline "INT. AMORA DIVE LAB - DAY - REALITY," and character names in all caps such as "NORA KAI (32)." The...

==========
1B voice profile schema (from DELIVERABLE 1B verification gate + V2.2 runbook):

php artisan tinker --execute='
$v = App\Models\Story::where("slug","anima-machina")->firstOrFail()->adaptation->voice_profile ?? [];
$dna = $v["author_voice_dna_profile"] ?? [];
echo "profile_type: ".($v["profile_type"] ?? "MISSING").PHP_EOL;
echo "collocations: ".count($dna["collocation_fingerprint"] ?? [])." (need >=15)".PHP_EOL;
echo "negative_space: ".count($dna["negative_space_map"] ?? [])." (need >=5)".PHP_EOL;
echo "comparative_exclusion: ".count($dna["comparative_exclusion"] ?? [])." (need >=2)".PHP_EOL;
echo "audit_points: ".count($v["fourteen_point_audit_protocol"] ?? [])." (need =14)".PHP_EOL;
echo "voice_profile_bytes: ".strlen(json_encode($v)).PHP_EOL;
'



profile_type: SCREENWRITER
collocations: 20 (need >=15)
negative_space: 7 (need >=5)
comparative_exclusion: 3 (need >=2)
audit_points: 14 (need =14)
voice_profile_bytes: 37746
==========

D. Story-wide phases present:

php artisan tinker --execute='
$a = App\Models\Story::where("slug","anima-machina")->firstOrFail()->adaptation;
foreach (["ip_trimming","format_detection","ip_audit","voice_profile","story_session_map"] as $k) {
  echo $k.": ".(!empty($a->{$k}) ? "present" : "MISSING").PHP_EOL;
}
'


ip_trimming: present
format_detection: present
ip_audit: present
voice_profile: present
story_session_map: present
==========

E. Full adaptation export (phases 2–8 JSON — includes voice_profile)

php artisan adaptation:export anima-machina

On server:

ls -la /var/www/html/database/exports/adaptation-anima-machina-*.json
wc -c /var/www/html/database/exports/adaptation-anima-machina-*.json

Note: adaptation:export includes voice_profile and all session phase JSON, but not runtime_narrator_prompt or ip_trimming. Get those separately below.

==========

F. Upload to bucket (download via File explorer)
Disk name is public (not s3).

----------

F1. Voice profile only (1B deliverable artifact)
php artisan tinker --execute='
$json = json_encode(App\Models\Story::where("slug","anima-machina")->first()->adaptation->voice_profile, JSON_UNESCAPED_UNICODE);
$dest = "exports/anima-voice-profile.json";
echo "source: ".strlen($json)." bytes\n";
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $json);
echo "uploaded: ".Illuminate\Support\Facades\Storage::disk("public")->size($dest)." bytes\n";
'

----------

F2. Full adaptation JSON (latest export)
php artisan tinker --execute='
$slug = "anima-machina";
$dir = database_path("exports");
$files = glob($dir."/adaptation-{$slug}-*.json");
if (empty($files)) { echo "No export found. Run: php artisan adaptation:export {$slug}\n"; exit(1); }
usort($files, fn($a,$b) => filemtime($b) <=> filemtime($a));
$src = $files[0];
$dest = "exports/anima-adaptation.json";
$content = file_get_contents($src);
echo "source file: ".basename($src)."\n";
echo "source: ".strlen($content)." bytes\n";
if (strlen($content) < 1000) { echo "ABORT: source too small\n"; exit(1); }
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $content);
echo "uploaded: ".Illuminate\Support\Facades\Storage::disk("public")->size($dest)." bytes\n";
'

----------

F3. Runtime narrator prompts (all sessions)
php artisan tinker --execute='
$story = App\Models\Story::where("slug","anima-machina")->firstOrFail();
$max = $story->adaptation->sessionAdaptations()->max("session_number") ?? 0;
foreach (range(1, $max) as $n) {
  $rp = $story->adaptation->sessionAdaptations()->where("session_number",$n)->first()->runtime_narrator_prompt ?? null;
  if (!$rp) { echo "S{$n}: MISSING\n"; continue; }
  $dest = "exports/anima-session-{$n}-runtime.txt";
  Illuminate\Support\Facades\Storage::disk("public")->put($dest, $rp);
  echo "S{$n}: uploaded ".strlen($rp)." bytes\n";
}
'

----------

F4. Optional: ip_trimming (not in adaptation:export JSON)
php artisan tinker --execute='
$json = json_encode(App\Models\Story::where("slug","anima-machina")->first()->adaptation->ip_trimming, JSON_UNESCAPED_UNICODE);
$dest = "exports/anima-ip-trimming.json";
Illuminate\Support\Facades\Storage::disk("public")->put($dest, $json);
echo "uploaded: ".Illuminate\Support\Facades\Storage::disk("public")->size($dest)." bytes\n";
'

==========

G. Download from bucket