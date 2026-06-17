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

## 7. Screenplay QA — 1B v2 (Anima Machina)

Run after novelist baselines pass. Do NOT re-run novelist baselines for this patch.

### 7A. Static checks (no API cost — run locally first)

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step_1b_v2 anima-machina
```

**Expected output (static probes only — DB unavailable locally):**
- 5A: All 7 markers present in both screenwriter blades (14/14 ok lines)
- 5C: All SCREENWRITER Section 6 instruction wrapper + enforcement heading checks pass (8/8 ok)
- 5C: All NOVELIST Section 3B absence checks pass (3/3 ok)

### 7B. Re-adapt Anima Machina on Cloud

```bash
php artisan stories:run-adaptation anima-machina --force
php artisan queue:work --queue=adaptation --stop-when-empty
```

### 7C. Verify 1B v2 voice profile fields

```bash
php artisan tinker --execute='$v=App\Models\Story::where("slug","anima-machina")->firstOrFail()->adaptation->voice_profile;
$dna=$v["author_voice_dna_profile"]??[];
echo "profile_type: ".($v["profile_type"]??"MISSING").PHP_EOL;
echo "numerical_enforcement_layer: ".(!empty($dna["numerical_enforcement_layer"])?"present":"MISSING").PHP_EOL;
echo "rhythm_transition_architecture: ".(!empty($dna["rhythm_transition_architecture"])?"present":"MISSING").PHP_EOL;
echo "beat_architecture_protocol: ".(!empty($dna["beat_architecture_protocol"])?"present":"MISSING").PHP_EOL;
echo "scene_transition_compression_protocol: ".(!empty($dna["scene_transition_compression_protocol"])?"present":"MISSING").PHP_EOL;
echo "voice_decay_prevention_protocol (top-level): ".(!empty($v["voice_decay_prevention_protocol"])?"present":"MISSING").PHP_EOL;
$s2p=$dna["screenplay_to_prose_protocol"]??[];
echo "screenplay_to_prose_protocol.element_rules: ".count($s2p["element_rules"]??[])." entries".PHP_EOL;
echo "screenplay_to_prose_protocol.quantitative_translation_mappings: ".count($s2p["quantitative_translation_mappings"]??[])." entries (need >=6)".PHP_EOL;
$rta=$dna["rhythm_transition_architecture"]??[];
echo "rhythm transition matrix: ".count($rta["transition_matrix"]??[])." rows (need 4)".PHP_EOL;'
```

### 7D. Canonical path guard check (run step_1b_v2 on Cloud)

```bash
php "Adaptation layer/Chaos adaptation/v2-implementation/validation/pipeline-upgrade-v2-validation-runner.php" step_1b_v2 anima-machina
```

**Expected 5B output:**
- All M–P fields under `author_voice_dna_profile`: `ok`
- `voice_decay_prevention_protocol` top-level with 3 sub-keys: `ok`
- `screenplay_to_prose_protocol` as object (not bare array): `ok`
- `quantitative_translation_mappings` count ≥ 6: `ok`
- 4×4 transition matrix: `ok`
- Canonical path guards (no misplaced qtm or vdpp): all `ok`
- Chapter fragments with required raw-count fields: `ok` (after pipeline run)

**Pass thresholds:** profile_type = SCREENWRITER, all M–P fields present, quantitative_translation_mappings ≥ 6, transition matrix 4×4, voice_decay_prevention_protocol top-level only.

### 7E. Runtime prompt check (SCREENWRITER Section 6)

```bash
php artisan tinker --execute='$s=App\Models\Story::where("slug","anima-machina")->firstOrFail()->sessionAdaptations()->orderBy("session_number")->first(); echo "runtime_prompt_chars: ".strlen($s->runtime_narrator_prompt??"").PHP_EOL; echo (str_contains($s->runtime_narrator_prompt??"","Apply the passage-level enforcement checks")?"has_1b_v2_instruction_wrapper":"missing_wrapper").PHP_EOL; echo (str_contains($s->runtime_narrator_prompt??"","NUMERICAL ENFORCEMENT LAYER")?"has_NEL_section":"missing_NEL").PHP_EOL; echo (str_contains($s->runtime_narrator_prompt??"","VOICE DECAY PREVENTION PROTOCOL")?"has_VDPP_section":"missing_VDPP").PHP_EOL;'
```

### 7F. Failures

| Symptom | Action |
|---------|--------|
| `profile_type = NOVELIST` on Anima | FormatDetection emitted NOVEL — check format_detection column |
| Missing M–P fields | Merge did not produce 1B v2 output — check LLM output in job logs |
| `quantitative_translation_mappings` count < 6 | LLM under-populated — add example to merge prompt, re-adapt |
| `voice_decay_prevention_protocol` under `author_voice_dna_profile` | Schema constraint violation — re-adapt with corrected merge instructions |
| Runtime prompt missing instruction wrapper | Section 6 Blade condition `!empty($voice[...])` false — check voice_profile for missing NEL or VDPP |
