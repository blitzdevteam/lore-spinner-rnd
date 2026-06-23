# System Fix — Entry Point / Cold Open Alignment

## Applies to all screenplay adaptations


Use the repository's existing ChaosStoryConfig access pattern. Do not invent a new `find()` method if it does not already exist.

becareful of wiring and relations
Preserve the intended OUTPUT code fence in the Blade prompt. Do not add an extra stray closing ``` after TASK 1.


## Problem

The pipeline currently allows two failure modes:

Phase 2 can allocate Session 1 in a way that skips opening beats, and Phase 3 can then choose a later “stronger” beat even when the screenplay’s literal opening is already playable.

This results in cold opens that do not match the authored first page, even when that opening already contains embodied scene action, tension, procedure, mystery, discovery, or a playable threshold.

The cold open is not the root problem. Runtime is not the root problem.

The fix is:

```text
1. Phase 2 must stop skipping playable opening beats when Session 1 covers the story beginning.
2. Phase 3 must stop treating screenplay openings like novel exposition.
3. prefer_literal_opening must be implemented as a story-level override now.
```

Do **not** hard-code event 1 globally.
Do **not** change runtime.
Do **not** change cold-open injection.
Do **not** full `--force`.
Do **not** add the strict earliest-event PHP guard yet (prompt-only enforcement for this pass).

---

# 1. Add `prefer_literal_opening` as a story-level config option

File:

```text
app/ChaosMode/ChaosStoryConfig.php
```

Add an optional key per story row:

```php
'prefer_literal_opening' => true,
```

Meaning:

```text
Strongly prefer the literal source opening when Session 1 represents the story beginning — unless that opening is genuinely non-playable.
```

This is a **story-level author override**, not a global hard-coded rule.

**Config semantics (no third `auto` enum in code):**

| Value | Behavior |
|-------|----------|
| **`true`** | Phase 2 must include opening beats when Session 1 covers the story beginning. Phase 3 **strongly prefers** the literal opening / earliest playable session moment. Forward cut allowed only when the playable-opening test fails. **Prompt enforcement only — no PHP throw guard in this pass.** |
| **`false` / omitted** | Patched Phase 3 Task 1 screenplay rules still apply (literal-first rubric). Forward cut remains allowed when the rubric says the earliest moment is non-playable. |

`prefer_literal_opening=true` means **“strongly prefer the literal opening unless genuinely non-playable”** — not “always force the earliest event in code.”

Set `prefer_literal_opening => true` only on stories where the author requires the literal first page. All other screenplays rely on the default patched prompts.

---

# 2. Shared helper — normalize screenplay format in jobs

Phase 3 passes `FORMAT: SCREENPLAY (via 1B v3)` in the user prompt. Voice lock stores `profile_type: SCREENWRITER`. Any job logic that branches on screenplay must accept **both** labels.

When adding job-side conditionals (logging, future guards, etc.), use:

```php
$isScreenplay = in_array(strtoupper((string) ($format ?? '')), ['SCREENPLAY', 'SCREENWRITER'], true)
    || strtoupper((string) ($adaptation->voice_profile['profile_type'] ?? '')) === 'SCREENWRITER';
```

For Phase 3, `$format` comes from the job’s existing match on `profile_type` (`SCREENPLAY (via 1B v3)` / `NOVEL (via 1A v2)`). Prefer checking `profile_type === 'SCREENWRITER'` when `$format` is the human-readable label; use the combined helper when both may appear.

**This pass:** no PHP guard that throws on earliest-event mismatch. Prompt + verification gate only.

---

# 3. Wire `prefer_literal_opening` into Phase 2

File:

```text
app/Jobs/Adaptation/StorySessionMapJob.php
```

Where the job prepares variables for:

```text
resources/views/ai/agents/adaptation/story-session-map/prompt.blade.php
```

add:

```php
'preferLiteralOpening' => (bool) (ChaosStoryConfig::find($this->story->slug)['prefer_literal_opening'] ?? false),
```

Then edit:

```text
resources/views/ai/agents/adaptation/story-session-map/prompt.blade.php
```

Add this after `ESTIMATED SESSION COUNT FROM FORMAT DETECTION`:

```blade
STORY ENTRY OVERRIDE:
prefer_literal_opening: {{ $preferLiteralOpening ? 'true' : 'false' }}

If prefer_literal_opening is true, Session 1 must preserve the literal source opening beats when Session 1 covers the beginning of the story. Do not allocate Session 1 in a way that excludes the authored opening and starts at a later louder beat.
```

Note: Phase 2 system prompt is static; the model reads the actual `true`/`false` value from this user-prompt block.

---

# 4. Patch Phase 2 system prompt (TASK 1 allocation rules)

File:

```text
resources/views/ai/agents/adaptation/story-session-map/system-prompt.blade.php
```

Inside **TASK 1 — SESSION COUNT AND ALLOCATION**, apply **two** replacements.

### 4A — Event count rule

Find:

```text
* No session should contain fewer than 5 events or more than 20
```

Replace with:

```text
* Most sessions should contain 5–20 events. A session may exceed 20 only when preserving a coherent arc or natural dramatic unit requires it. Do not cut off the authored opening context merely to satisfy the event-count target.
```

### 4B — Opening / highest-energy rule

Find:

```text
* The first session must open with the highest-energy material
```

Replace with:

```text
* Session 1 must preserve the story's playable opening context. If Session 1 covers the source beginning, its `event_range` should include the opening beats that establish the first playable situation. Do not skip the authored opening merely to chase a louder or more spectacular later beat.

* "Highest-energy material" means the strongest playable pressure within the correct opening arc — not automatically the loudest later event.

* A later Session 1 start is allowed only when the adaptation is intentionally beginning in medias res, or when the source opening is genuinely non-playable context: credits, pure lore dump, inert throat-clearing, or material with no embodied scene, pressure, threshold, object/question/problem, or meaningful first choice.

* If `prefer_literal_opening` is true (see user prompt STORY ENTRY OVERRIDE), Session 1 must include the literal source opening beats in its `event_range` unless those beats were removed by IP trimming or are not present in the extracted events.
```

No schema change required. Phase 2 still outputs `event_range`; `StorySessionMapJob` still assigns `events.session_number` from that range.

---

# 5. Wire `prefer_literal_opening` into Phase 3

File:

```text
app/Jobs/Adaptation/EntryPointDiagnosisJob.php
```

Where the job prepares variables for:

```text
resources/views/ai/agents/adaptation/entry-point-diagnosis/prompt.blade.php
```

add:

```php
'preferLiteralOpening' => (bool) (ChaosStoryConfig::find($this->story->slug)['prefer_literal_opening'] ?? false),
```

Then edit:

```text
resources/views/ai/agents/adaptation/entry-point-diagnosis/prompt.blade.php
```

Add this after `FORMAT: {{ $format }}`:

```blade
STORY ENTRY OVERRIDE:
prefer_literal_opening: {{ $preferLiteralOpening ? 'true' : 'false' }}

If prefer_literal_opening is true, and this is Session 1 of a SCREENPLAY source, strongly prefer the earliest available session event that represents the literal source opening, unless it is genuinely non-playable. Do not move forward to a louder later beat.
```

---

# 6. Patch Phase 3 system prompt / D10 Task 1

File:

```text
resources/views/ai/agents/adaptation/entry-point-diagnosis/system-prompt.blade.php
```

Replace the entire current block from:

```text
## TASK 1 — SELECT THE STRONGEST ENTRY POINT
```

through the closing ` ``` ` of that section’s OUTPUT block (stop before `## TASK 2`).

> **Note for implementing agent:** The block below is literal instructional prose. Paste it verbatim into the blade template. Do **not** wrap plain-text lines in `{{ }}` or add `@if` / `@endif` Blade directives. The only Blade expressions in this system-prompt file are dynamic variables already present in the surrounding template (e.g. `{{ $format }}`). This Task 1 block is static and requires no Blade syntax changes of its own.

Replace with:

```text
## TASK 1 — SELECT THE STRONGEST ENTRY POINT

The entry point is the moment where playable pressure begins. It is a cut, but it is not automatically a forward cut.

**HARD GATE — PROTAGONIST AGENCY (disqualifying, applied BEFORE scoring):** The player must be PRESENT in their own body at the entry point AND able to act within the first beat. A moment where the protagonist is absent, off-screen, or a passive spectator to someone else's event is DISQUALIFIED — no matter how visually striking or high-pressure it is. Spectacle the player only watches is not an entry point; it is a cutscene. A great cold open is the protagonist's body in a moment where THEY can do something (Akira at the door, about to press the buzzer — not a camera watching a stranger transform). Disqualify first, then score the survivors.

First identify the source format:

- For SCREENPLAY sources, evaluate the earliest available session moment first.
- For NOVEL sources, evaluate the earliest available session moment, but expect that the playable hook may be buried later.

The entry point must be selected from the allocated session events shown in the user prompt. The `start_event_position` you return MUST be one of those listed story-global event numbers.

Do NOT open on a flashback, a dream, or a non-interactive info-dump.

STORY OVERRIDE:

If `prefer_literal_opening` is true (see user prompt STORY ENTRY OVERRIDE), and this is Session 1 of a SCREENPLAY source, you must strongly prefer the earliest available session moment that represents the literal source opening.

Do not move forward from that moment merely because a later event is louder, cleaner, more spectacular, or closer to a trailer beat.

Only move forward despite `prefer_literal_opening=true` if the earliest available session moment is genuinely non-playable (see playable-opening test below). If you still move forward, explicitly state why the earliest moment failed that test.

PLAYABLE-OPENING TEST (screenplay):

An earliest session moment is non-playable only if ALL of the following are true:
- credits only, OR pure lore dump, OR inert throat-clearing
- no embodied scene
- no active pressure
- no protagonist-facing problem
- no object, question, threshold, signal, body, machine, or discovery in play
- no meaningful first choice can be reached within roughly 300 words

RULES:
- **Respect a strong literal opening.** If the source's literal opening passes the agency gate and grounds the protagonist through action, KEEP it. Do not relocate a working opening for a flashier one. Relocation exists only for openings that are slow, expository, or fail the gate.

SCREENPLAY ENTRY RULE:

For SCREENPLAY sources, the literal opening / earliest available session moment is presumed playable unless it fails the playable-opening test above.

Screenplays often begin with compressed visual action, procedure, ritual, surveillance, travel, discovery, lab work, object-focused mystery, or quiet scene pressure. Do NOT treat these as exposition debt merely because they are not loud or climactic.

If the earliest available session moment contains any of the following, START THERE:

1. embodied scene action
2. visual or procedural activity
3. a live object, question, anomaly, message, door, threshold, signal, body, machine, or discovery
4. tension or uncertainty already present
5. a protagonist-facing problem
6. a choice or threshold that can arrive within roughly 300 words

Do NOT move forward merely because a later event is:
- higher energy
- more spectacular
- closer to the premise
- easier to summarize
- more obviously “dramatic”
- a cleaner trailer moment

Relocation must not cut the grounding or the first agency.

When you move the entry point forward, never skip past:
(a) the beat that establishes the protagonist's identity-through-action, or
(b) the protagonist's first real choice.

If a striking world-event (a reveal, a transformation, an attack) is the best hook, stage it AROUND the grounded protagonist — they witness or are caught in it as an agent — rather than opening cold on the event with the player absent.

Fold necessary context into compressed present-tense pressure; do not amputate the opening to reach the spectacle faster.

If you move forward in a SCREENPLAY source, you must explicitly state why the earliest available session moment failed the playable-opening test.

NOVEL ENTRY RULE:

For NOVEL sources, the literal opening often contains narration, scene-setting, backstory, or interiority before live pressure begins. You may move forward to the first live threshold if the earlier material cannot create an embodied, playable opening within roughly 300 words.

ENTRY-POINT RUBRIC (among moments that PASS the gate, the strongest entry maximizes these):

Evaluate candidate moments from the allocated source against this rubric. Score each candidate; pick the highest.

1. **Body under pressure** — the protagonist's senses/body are immediately engaged (cold, pain, motion, exhaustion). Can the player FEEL something in the first two sentences?
2. **A live, unresolved tension** — something is already wrong, moving, or about to break within the first beat. Not setup. Pressure.
3. **The protagonist's own threshold** — THEY are about to cross a line they cannot uncross (a door only they can open, a send button under their hand, an assignment they must take or refuse). The threshold must be the protagonist's to cross — not the world's to cross around them. A world-event the player merely witnesses does NOT count here.
4. **Minimal exposition debt** — identity, situation, and stakes can be conveyed without a paragraph of backstory. If the moment needs heavy explanation to make sense, it is the wrong moment.
5. **Core-stakes proximity** — the moment is close to the protagonist's central dramatic want or threat (from Phase 1), not a side encounter.

Selection rule:

- For SCREENPLAY: choose the earliest available session moment that passes the playable-opening test. Do not skip it for a louder later beat.
- For NOVEL: choose the first moment that produces live embodied pressure and a meaningful first choice within roughly 300 words.
- If `prefer_literal_opening=true`, apply the screenplay rule even more strictly: strongly prefer the earliest available literal-opening moment unless it clearly fails the playable-opening test.

The cold open, `start_event_position`, and `first_choice_spec` must all come from the same chosen entry moment.

OUTPUT:
```
CANDIDATES CONSIDERED: [list the 2-4 moments evaluated]
AGENCY GATE: [for each candidate — PASS (protagonist present + can act) / DISQUALIFIED (absent or spectator) + one-line reason]
ENTRY POINT: [the chosen source moment — must be a gate-PASS]
WHY (rubric scores, gate-passers only): body, tension, the protagonist's-own-threshold, exposition debt, core-stakes proximity — one line each
WHAT IS NOT CUT: [confirm the chosen entry does not skip the identity-through-action grounding or the protagonist's first agency moment]
ENTRY-POINT ADJUSTMENT: [literal opening kept / earliest available session moment / moved forward to: ___ — and why it still passes the gate and cuts nothing essential]
CUT POINT: [where the cold open ends and hands to the Phase 4 SETUP beat]
```
```

---

# 7. Patch Phase 3 Task 6 screenplay note

Same file:

```text
resources/views/ai/agents/adaptation/entry-point-diagnosis/system-prompt.blade.php
```

Find in **TASK 6 — EMOTIONAL PROMISE & FORMAT NOTES**:

```text
- **Screenplay (1B v3):** the source opening is already compressed and visual; translate to second-person present prose via the Screenplay-to-Prose protocol. The entry point is usually near the literal opening.
```

Replace with:

```text
- **Screenplay (1B v3):** the source opening is usually already compressed, visual, and playable. Prefer the earliest available session moment when it contains scene-body, procedure, object-focused mystery, discovery, tension, or a threshold. Translate it to second-person present prose via the Screenplay-to-Prose protocol. Do not move forward merely to find a louder hook. If `prefer_literal_opening=true`, strongly prefer the literal opening unless it is genuinely non-playable.
```

---

# 8. Patch Phase 3 verification gate

Same file:

```text
resources/views/ai/agents/adaptation/entry-point-diagnosis/system-prompt.blade.php
```

Inside **VERIFICATION GATE**, add item 0 before item 1 ("Body first"):

```text
0. **Agency gate?** Is the protagonist PRESENT in their own body at the entry point and able to act in the first beat — not a spectator to someone else's event? If the opening watches a striking thing happen TO the world while the player does nothing, it FAILS — return to Task 1.
```

Also add immediately after item 1:

```text
1A. **Screenplay opening honored?** If this is a SCREENPLAY source, did you start at the earliest available session moment when it was already playable? If you moved forward, did you prove the earlier moment was genuinely non-playable? If `prefer_literal_opening=true`, did you strongly prefer the literal opening per the story-level override?
```

---

---

# 10. Re-run scope

After these patches are deployed, affected screenplay stories need to rerun from the point where the bad decision was made.

**If opening events are already assigned to Session 1** but Phase 3 chose a later entry:

```text
Re-run Phase 3 → Phase 8 for the affected session(s).
Re-assemble runtime if needed.
```

**If Phase 2 allocation skipped opening events from Session 1** (Session 1 `event_range` starts after the authored opening):

```text
Re-run Phase 2 for the story.
StorySessionMapJob will regenerate session_adaptations and dispatch Phase 3 → Phase 8 for all sessions in the batch.
Re-assemble runtime for affected sessions.
```

Do not run full `--force` unless earlier pipeline stages are corrupt.

Do not rerun Voice Lock.

Do not rerun trim / format detection.

---

# Done when

Screenplay adaptations succeed when:

```text
Session 1 does not skip the authored opening merely to chase louder later material or to satisfy a rigid 5–20 event cap.
Phase 3 evaluates the earliest available session moment first.
If that moment is playable, Phase 3 starts there.
If Phase 3 moves forward, it explains why the earlier moment was genuinely non-playable.
Cold open, start_event_position, and first_choice_spec all describe the same moment.
Stories with prefer_literal_opening=true strongly prefer the literal opening via prompts (no PHP guard in this pass).
```

---

# Final instruction to the agent

Implement the patches exactly (§1–§8, §10). Skip §9.

The root fix is:

```text
Screenplays should not be treated like novels at entry selection.
If the opening is already embodied, visual, tense, procedural, mysterious, or threshold-bearing, start there.
Do not move forward just because a later beat is louder.
Do not trim the opening arc merely to satisfy event-count targets.
If prefer_literal_opening=true, strongly prefer the literal opening unless genuinely non-playable — prompt only, no code guard yet.
```
