# Pipeline Upgrade V2.2 — Validation Runbook (Laravel Cloud)

Companion to: `pipeline-upgrade-v2-validation-runner.php`  
Process log: `../process-log/v2-2-process-log.md`

All commands below run on the **deployed Laravel Cloud environment** after push + automatic redeploy — not on a local machine unless running static Blade checks only.

---

## 0. Prerequisites

- Code merged and **Laravel Cloud redeploy completed**
- Queue worker processing `adaptation` queue
- AI keys configured in Cloud environment
- QA stories (confirm slugs on Cloud):
  - **Wizard of Oz** — novelist / 1A primary baseline
  - **Sherlock** — novelist / 1A primary baseline
  - **Anima Machina** — screenplay / 1B (deferred until novelist path green)

Find slugs:
```bash
php artisan tinker --execute='foreach(["Wizard","Sherlock","Anima"] as $t){ $s=App\Models\Story::where("title","like","%{$t}%")->first(); echo ($s?->slug ?? "NOT FOUND")." — {$t}".PHP_EOL; }'
```

---

## 1. Static checks (no API cost)

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step3
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step_v22
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step4
```

**Expect:**
- All voice-lock 1A/1B blades render
- `step_v22` shows pipeline job chain strings
- `step4` includes markers: `PAUL REVIEW`, `300–350 words`, `COLLLOCATION FINGERPRINT`

---

## 2. Run full pipeline (Cloud — dispatches AI jobs)

```bash
php artisan stories:run-adaptation <slug> --force
# Queue worker should drain adaptation queue automatically
php artisan queue:work --queue=adaptation --stop-when-empty
```

Start with **Wizard of Oz**, then **Sherlock**.

**Monitor status transitions:**
`IP_TRIMMING → FORMAT_DETECTION → IP_AUDIT → VOICE_LOCK → … → COMPLETED`

---

## 3. Retrieve adaptation artifacts

**Story-wide JSON presence:**
```bash
php artisan tinker --execute='$a=App\Models\Story::where("slug","<slug>")->firstOrFail()->adaptation; foreach(["ip_trimming","format_detection","ip_audit","voice_profile","story_session_map"] as $k){ echo $k.": ".(!empty($a->{$k})?"present":"missing").PHP_EOL; } echo "status: ".$a->adaptation_status->value.PHP_EOL;'
```

**Pipeline order proof:**
```bash
php artisan tinker --execute='$a=App\Models\Story::where("slug","<slug>")->firstOrFail()->adaptation; echo "format: ".($a->format_detection["detected_format"]??"MISSING").PHP_EOL; echo "ip_audit: ".(!empty($a->ip_audit)?"present":"MISSING").PHP_EOL; echo "profile_type: ".($a->voice_profile["profile_type"]??"MISSING").PHP_EOL;'
```

**V2.2 voice profile schema gates (novelist):**
```bash
php artisan tinker --execute='$v=App\Models\Story::where("slug","<slug>")->firstOrFail()->adaptation->voice_profile; $dna=$v["author_voice_dna_profile"]??[]; echo "profile_type: ".($v["profile_type"]??"MISSING").PHP_EOL; echo "collocations: ".count($dna["collocation_fingerprint"]??[]).PHP_EOL; echo "negative_space: ".count($dna["negative_space_map"]??[]).PHP_EOL; echo "comparative_exclusion: ".count($dna["comparative_exclusion"]??[]).PHP_EOL; echo "audit_points: ".count($v["fourteen_point_audit_protocol"]??[]).PHP_EOL;'
```

**Pass thresholds (novelist):** collocation ≥15, negative_space ≥5, comparative_exclusion ≥2, audit_points = 14

**Runtime prompt check:**
```bash
php artisan tinker --execute='$s=App\Models\Story::where("slug","<slug>")->firstOrFail()->sessionAdaptations()->orderBy("session_number")->first(); echo "runtime_prompt_chars: ".strlen($s->runtime_narrator_prompt??"").PHP_EOL; echo (str_contains($s->runtime_narrator_prompt??"","300–350")?"has_cadence_rules":"missing_cadence_rules").PHP_EOL; echo (str_contains($s->runtime_narrator_prompt??"","COLLLOCATION FINGERPRINT")?"has_v22_voice_section":"missing_v22_voice").PHP_EOL;'
```

---

## 4. Compare against reference profiles

- **Wizard of Oz** → `V2.2/REFERENCE - VOICE PROFILE - L FRANK BAUM v2.1 copy.md`
- **Sherlock** → `V2.2/REFERENCE - VOICE PROFILE - ARTHUR CONAN DOYLE v2.1 copy.md`

Export for diff:
```bash
php artisan tinker --execute='file_put_contents(storage_path("app/voice-profile-<slug>.json"), json_encode(App\Models\Story::where("slug","<slug>")->firstOrFail()->adaptation->voice_profile, JSON_PRETTY_PRINT)); echo "written".PHP_EOL;'
```

---

## 5. Chaos Mode runtime smoke test

Test **Wizard of Oz** and **Sherlock** on the deployed app UI/API.

Checklist:
- Response ~300–350 words (hard max 400)
- Ends on forward pull (not atmosphere)
- Custom typed input absorbed (Custom Input Protocol)
- Consequence visible within 2 turns

---

## 6. Failures

| Symptom | Action |
|---------|--------|
| `adaptation_status = failed` | Check Cloud logs for failing job class |
| Missing V2.2 voice fields | Redeploy fix, re-run `--force` |
| `runtime_narrator_prompt` null | Pipeline did not reach D8; check session adaptations |
| `profile_type = MISSING` | Story not re-adapted under V2.2 |

---

## 7. Screenplay QA (later)

**Anima Machina** — after novelist baselines pass, verify `profile_type: SCREENWRITER` and 1B fields (`action_line_metrics`, `screenplay_to_prose_protocol`, etc.).
