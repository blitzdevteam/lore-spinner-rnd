# Curt Feedback — Fix Plan

**Date:** 2026-04-26
**Companion diagnosis:** `curt-feedback-diagnosis.md` (sibling)
**Companion log review:** `curt-game-log-review.md` (sibling — this is the doc that surfaced WS-0 below)
**Companion runtime context:** `runtime-logic.md` (root of `Adaptation layer/`)
**Out of scope (verified working):** TTS / ElevenLabs (Curt's #7 — not a code defect, see diagnosis §7).
**Status of `entry_point_diagnosis.cold_open`:** authored by `EntryPointDiagnosisJob`, persisted on `session_adaptations.entry_point_diagnosis`. Verified live for Alice in `database/exports/adapptation-third-try.json`. The opening fix below is wiring this existing data through, not generating new content.

> **WS-0 applied locally, awaiting review.** The `Game::prompts()` ordering-collision fix (see §0.5 below) is the highest-priority change because it corrupts the data every other workstream is diagnosed against. It is implemented in two surgical edits and verified at the SQL level. It must land before WS-A trace logging is interpreted, since pre-fix traces would be sampled against a poisoned conversation history.

---

## 0. Plan shape

Four workstreams, ordered by impact-per-effort. Each work item is independent enough to ship on its own branch; the order below is the recommended sequence to maximize what the next playtest can validate.

```
WS-0  Game::prompts() ordering collision        ── P0, must ship first (corrupts all data)
   └─ 0.1  Drop ->oldest() from Game::prompts() relation; add explicit oldest() at UI eager-load

WS-A  Observability & first-turn correctness   ── ship next, validates everything else
   ├─ A1  Per-turn turn-trace logging
   └─ A2  Explicit isFirstTurnInEvent signal in system prompt

WS-B  State persistence & choice-binding       ── unblocks #3, #4, half of #5
   ├─ B1  Extend NarrationAgent schema (+input_classification, +mapped_choice_id, +state_delta)
   ├─ B2  Persist the schema fields into game state in PromptController::store
   ├─ B3  Surface tracked state back into the system prompt (closing the loop)
   └─ B4  Authored-choice detection (compare prompt to session_choice_design A/B/C text)

WS-C  Opening narration uses the cold open     ── direct fix for #6
   ├─ C1  StoryOpeningGeneratorJob reads SessionAdaptation S1 cold_open + emotional_promise
   ├─ C2  Post-adaptation hook: regenerate opening when pipeline completes
   ├─ C3  Tweak GameOpeningNarration.vue render to handle paragraph prose vs <br> staccato
   └─ C4  Opening-narration system prompt: Section 2 sources from cold open verbatim/as direction
```

Workstream D (lower priority, listed at the bottom) gathers the longer-tail items: S1↔S2 cold-open overlap, Phase 5 leading-bias prompt tightening, branch-dimensions pollution, choice-design Q4 alignment.

---

## 0.5. WS-0 — `Game::prompts()` ordering collision (P0, APPLIED)

**Status:** code change applied locally on this branch. Awaiting review before commit/push.

**Why this is P0 and not part of WS-B:** `curt-game-log-review.md` confirmed empirically that `Game::prompts()->latest()->first()` was returning the *oldest* prompt, and `Game::prompts()->latest()->limit(6)` was returning the *oldest 6* prompts — the inverse of what the call sites assume. This single defect explains, on its own, why Curt's player input seems to be ignored after the first few turns (#1, #3, #4) and a large share of the rehash drift (#2): the conversation-history feed to the narrator is permanently stale after turn 6, and the player's most recent input is overwritten onto the very first prompt row.

Until this is fixed, every other diagnosis is diagnosed against a corrupted history window. WS-A's trace log would be sampling the same poisoned data.

### Root cause

`app/Models/Game.php` defined the relation as:

```php
public function prompts(): HasMany
{
    return $this->hasMany(Prompt::class)->oldest();
}
```

Eloquent **stacks** subsequent ordering calls instead of replacing them. So at the call sites:

- `$game->prompts()->latest()->first()` → `ORDER BY created_at ASC, created_at DESC` → DB honors the first clause → returns the oldest prompt.
- `$game->prompts()->latest()->limit(6)` → same stacking → returns the oldest 6 (not the newest 6) for `buildConversationHistory`.

### Caller audit (every site that uses `Game::prompts()`)

| Site | Pattern | Order-dependent? | Effect of the fix |
|---|---|---|---|
| `app/Http/Controllers/User/GameController.php::show()` (eager-load) | `'prompts:...'` | YES — UI iterates oldest→newest, `prompts[length-1]` is treated as latest | **Now uses explicit closure with `oldest()`** so render order is preserved |
| `app/Http/Controllers/User/GameController.php::reset()` | `->prompts()->delete()` | no | none |
| `app/Http/Controllers/User/GameController.php::begin()` | `->exists()` / `->create()` | no | none |
| `app/Http/Controllers/User/Game/PromptController.php::store()` line 31 | `->latest()->first()?->update()` | YES (wants newest, to attach the player's input) | **Now actually returns the newest** — bug fixed transparently |
| `app/Http/Controllers/User/Game/PromptController.php::store()` line 37–39 | `->where('event_id', …)->count()` | no | none |
| `app/Http/Controllers/User/Game/PromptController.php::store()` line 78 | `->prompts()->create()` | no | none |
| `app/Http/Controllers/User/Game/PromptController.php::buildConversationHistory()` | `->latest()->limit(6)->reverse()` | YES (wants the newest 6 in chronological order) | **Now actually returns the newest 6** — narrator's window slides forward as it should |
| `app/Console/Commands/WipeStoriesCommand.php` line 49 | `->prompts()->delete()` | no | none |
| `app/Actions/Prompt/CreatePromptAction.php` line 14 | `->latest()->first()` | YES | **Now actually returns the newest** |

`Event::prompts()` and `VoiceLabSession::prompts()` are unaffected — they live on different parents.

### The fix (two surgical edits)

**Edit 1 — `app/Models/Game.php`:** drop the relation-level ordering. Add a docblock so future contributors understand the trap.

```php
/**
 * Intentionally returns an UNORDERED HasMany.
 *
 * Earlier this relation chained ->oldest() so iteration was always
 * chronological. That collided with call sites doing ->latest()->first()
 * and ->latest()->limit(6): Laravel stacks ORDER BY clauses, so the
 * resulting SQL was ORDER BY created_at ASC, created_at DESC and the DB
 * honored the first clause — silently returning the oldest row from
 * latest() and the oldest 6 rows from the conversation-history query.
 *
 * Call sites that need a specific order MUST add it explicitly
 * (oldest() for UI rendering, latest() for newest-first reads).
 *
 * @return HasMany<Prompt, $this>
 */
public function prompts(): HasMany
{
    return $this->hasMany(Prompt::class);
}
```

**Edit 2 — `app/Http/Controllers/User/GameController.php::show()`:** preserve the UI's chronological assumption explicitly at the only site that depends on it.

```php
$game->load([
    'story',
    'currentEvent.chapter',
    'prompts' => fn ($q) => $q
        ->select(['id', 'game_id', 'event_id', 'response', 'choices', 'prompt'])
        ->oldest(),
    'prompts.event',
]);
```

The closure form is required (you can't combine `prompts:col1,col2` shorthand with an ordering constraint), and the column-selection must be passed via `select()` to preserve the original payload shape used by `PromptResource` and the Vue UI in `resources/js/pages/User/Games/Show.vue`.

### SQL verification (post-fix)

Captured locally with `php -r` against the Laravel boot:

```sql
-- show() eager-load
select "id", "game_id", "event_id", "response", "choices", "prompt"
from "prompts"
where "prompts"."game_id" = ?
order by "created_at" asc           -- single ORDER BY, oldest-first ✓

-- latest()->first()
select * from "prompts"
where "prompts"."game_id" = ?
order by "created_at" desc          -- single ORDER BY, newest-first ✓

-- latest()->limit(6)  (window-function form because of the LIMIT/relation interaction)
select * from (
  select *, row_number() over (
    partition by "prompts"."game_id" order by "created_at" desc
  ) as "laravel_row"
  from "prompts"
  where "prompts"."game_id" = ?
) as "laravel_table"
where "laravel_row" <= 6
order by "laravel_row"               -- single window ORDER BY, newest-first ✓

-- where('event_id', …)->count()
select * from "prompts"
where "prompts"."game_id" = ? and "event_id" = ?
                                     -- no ORDER BY needed (count) ✓
```

No more stacked `ORDER BY created_at ASC, created_at DESC`.

### Acceptance

1. After WS-0 ships, run a fresh game and play 8+ turns.
2. `php artisan tinker --execute="App\Models\Game::find('<id>')->prompts()->latest()->first()->prompt"` returns the most recent player input (was returning turn-1's input or `null` before the fix).
3. The narrator's conversation history (visible once WS-A's trace log lands) shows the most recent 6 turns, not the oldest 6.
4. `prompts[0].prompt` in the API response is no longer overwritten on every turn (this was the smoking-gun evidence in `curt-game-log-review.md`).

### Branch / commit guidance

Single commit on the working branch. Suggested message style (descriptive, imperative, scoped):

> `fix(game): drop ->oldest() from Game::prompts() relation to unbreak ->latest() and conversation history`
>
> *(body explains the stacked-ORDER-BY collision, lists the two edited files, and references curt-game-log-review.md for the empirical evidence.)*

No PR per current request — direct commit/push, then await review.

---

## 1. WS-A — Observability & first-turn correctness

**Why first:** Curt's playtest is anecdotal. Without per-turn observability we cannot confirm whether subsequent fixes actually move the needle, and "Alice surrealism masks failures" (Curt's #8) means we have to instrument rather than feel for regressions. A1 + A2 are both small; together they make WS-B and WS-C measurable.

### A1 — Per-turn turn-trace logging

**Goal:** Every player turn writes a structured log entry capturing the inputs, decisions, and outcomes.

**Files:**

- `app/Http/Controllers/User/Game/PromptController.php` — `store()`
- new file: `app/Support/NarrationTurnTrace.php` (or inline a Log channel — pick the lighter option)

**Concretely:** at the bottom of `PromptController::store()`, after the new prompts row is created, call:

```php
Log::channel('narration')->info('narration.turn', [
    'game_id' => $game->id,
    'event_id_before' => $currentEvent->id,
    'event_id_after' => $game->current_event_id,
    'session_number_before' => $currentEvent->session_number,
    'session_number_after' => $game->current_session_number,
    'turn_count' => $turnCount,
    'advance_event_returned' => $aiResult['advance_event'],
    'force_advanced' => ! ($aiResult['advance_event']) && $turnCount >= 5,
    'is_continue' => $isContinue,
    'player_input_first_120' => mb_substr((string) $prompt, 0, 120),
    'narrator_response_first_120' => mb_substr(strip_tags((string) $aiResult['response']), 0, 120),
    'choices_returned' => $aiResult['choices'],
    'system_prompt_hash' => hash('sha256', $systemPrompt),
]);
```

**Operator surface:** add `php artisan game:trace {gameId}` console command that pretty-prints the channel rows for one game in chronological order. Source: `app/Console/Commands/SimulateGameStartCommand.php` already exists and is a good pattern reference.

**Hard validation rules to assert in the same trace command** (per row):

1. `event_id_after >= event_id_before` (no rewind unless session-cut adjusts).
2. If `advance_event_returned === true`, `event_id_after !== event_id_before`.
3. If session transition occurred, `session_number_after === nextEvent.session_number`.
4. Generated `choices` differ from the immediately previous turn's choices (no exact-string repeats).

Surface failures as colored warnings in the trace output. These give the next playtest a deterministic pass/fail per turn instead of vibe.

**Acceptance:** running `php artisan game:trace <id>` after any game shows N-row table; rules 1–4 are evaluated and any violation is highlighted. No extra DB columns needed (channel/file is enough; promote to a DB table later if useful).

---

### A2 — Explicit `isFirstTurnInEvent` signal in the system prompt

**Goal:** Stop relying on the model to infer "this is turn 1, the screenplay may be narrated" vs "this is turn 2+, stop narrating". The runtime knows the answer; we should pass it.

**Files:**

- `app/Http/Controllers/User/Game/PromptController.php` — `renderSystemPrompt()`
- `app/Http/Controllers/User/GameController.php` — `generateFirstNarration()`
- `resources/views/ai/agents/narration/system-prompt.blade.php`

**Code change in PromptController** (after computing `$turnCount`):

```php
'isFirstTurnInEvent' => $turnCount === 0,
```

(In `GameController::generateFirstNarration()`, this is unconditionally `true` — pass it explicitly.)

**Blade change** — replace the existing turnCount-pacing block with an explicit two-state block. New section, inserted after the existing CONTEXTUAL REFERENCE block:

```blade
=== TURN STATE ===
@if(!empty($isFirstTurnInEvent))
This is TURN 1 of this event. You MAY narrate the CURRENT_EVENT screenplay (converted into cinematic prose) up to the first natural decision point.
@else
This is TURN {{ $turnCount + 1 }} of this event (turns elapsed: {{ $turnCount }}).
The CURRENT_EVENT screenplay was already narrated on turn 1. You MUST NOT narrate it again, paraphrase it, or restart the scene.
Respond ONLY to the player's most recent action. Build forward from established facts.
@endif
```

Then keep the existing pacing-pressure block (lines 216-225 of the current template) but only fire it when `!$isFirstTurnInEvent`.

**Why this matters:** today the `@if(!empty($turnCount))` guard fails when `turnCount === 0` (PHP empty), so turn 1 gets *no* turn-state instruction at all. The model has to infer it, and rehash drift (Curt's #2) is the fallout.

**Acceptance:** turn 1 system prompt includes "TURN 1 of this event"; turn 2 includes "TURN 2 ... screenplay was already narrated". Visible in the file `storage/app/debug/system_prompt_*.txt` if you wire the existing simulator's debug-dump path through `PromptController`.

---

## 2. WS-B — State persistence & choice-binding

**Why this matters:** This is the structural defect from `curt-feedback-diagnosis.md` §3 (Defect B). The columns exist. Nothing writes to them. This is the single highest-impact change because it unblocks Curt's #3 ("progress not preserved"), most of #4 ("can't move forward"), and the runtime half of #5 ("choices don't matter").

### B1 — Extend `NarrationAgent` schema

**File:** `app/Ai/Agents/NarrationAgent.php`

Add four new fields to the structured-output schema. All are nullable / optional in semantics, but per Laravel-AI conventions we'll mark them `required` with explicit defaults for none-cases (to keep the schema strict):

```php
public function schema(JsonSchema $schema): array
{
    return [
        'response' => $schema->string()->required()->title('Response')->description('...'),
        'choices'  => $schema->array()->required()->title('Choices')->description('...')->items($schema->string()->required()),
        'advance_event' => $schema->boolean()->required()->title('Advance Event')->description('...'),

        'input_classification' => $schema
            ->string()
            ->required()
            ->title('Input Classification')
            ->description("How you classified the player's most recent action. One of: expressive, branch_aligned, emergent, unsupported, opening (use 'opening' only on turn 1)."),

        'mapped_choice_id' => $schema
            ->string()
            ->required()
            ->title('Mapped Choice ID')
            ->description('When input_classification is branch_aligned AND a pre-authored branching choice slot was active, the choice_id (e.g. S1_C1) the player selected. Empty string otherwise.'),

        'mapped_option_letter' => $schema
            ->string()
            ->required()
            ->title('Mapped Option Letter')
            ->description("When mapped_choice_id is set, one of 'A', 'B', 'C'. Empty string otherwise."),

        'state_delta' => $schema
            ->object()
            ->required()
            ->title('State Delta')
            ->description('Structured changes to player state from this turn. Object with optional keys: objects_acquired (string[]), objects_lost (string[]), conditions_added (string[]), conditions_removed (string[]), location_changed (string|null), tracked_dimension_path (object: dimension_name → A|B|C). Empty object if nothing changed.'),
    ];
}
```

**Notes on the contract:**
- We make the new fields `required` so the model is forced to consider them every turn. The "no classification yet" case is a valid value (`""` for IDs, `{}` for state_delta), not absence.
- `input_classification` mirrors the four categories already described in `system-prompt.blade.php` (`expressive`, `branch_aligned`, `emergent`, `unsupported`) plus the new `opening` for turn-1 first-narration.
- Keep the `state_delta` object loose: it's a free-form change description the runtime applies. Locking the shape too early couples this to a Phase 6 consequence model that hasn't shipped.

**Acceptance:** the agent returns `input_classification`, `mapped_choice_id`, `mapped_option_letter`, `state_delta` on every turn. Verifiable via `php artisan game:trace`.

---

### B2 — Persist into game state

**File:** `app/Http/Controllers/User/Game/PromptController.php` (`store()`)

After the AI call, compute updates to the four state columns:

```php
$gameUpdate = [];

// existing: current_event_id / current_session_number on advance

// NEW — branching_choices_taken: append on branch_aligned with mapped_choice_id
if ($aiResult['input_classification'] === 'branch_aligned' && $aiResult['mapped_choice_id'] !== '') {
    $taken = $game->branching_choices_taken ?? [];
    $taken[$aiResult['mapped_choice_id']] = $aiResult['mapped_option_letter'];
    $gameUpdate['branching_choices_taken'] = $taken;
}

// NEW — tracked_dimensions: merge state_delta.tracked_dimension_path
$dimPath = data_get($aiResult, 'state_delta.tracked_dimension_path', []);
if (!empty($dimPath)) {
    $tracked = $game->tracked_dimensions ?? [];
    foreach ($dimPath as $dimName => $letter) {
        $tracked[$dimName] = [
            'current_path' => $letter,
            'choice_id' => $aiResult['mapped_choice_id'] ?: null,
            'updated_at' => now()->toIso8601String(),
        ];
    }
    $gameUpdate['tracked_dimensions'] = $tracked;
}

// NEW — branch_resolution_log: always append one row per turn
$log = $game->branch_resolution_log ?? [];
$log[] = [
    'turn_index' => count($log),
    'event_id' => $currentEvent->id,
    'session_number' => $currentEvent->session_number,
    'input_classification' => $aiResult['input_classification'],
    'mapped_choice_id' => $aiResult['mapped_choice_id'] ?: null,
    'mapped_option_letter' => $aiResult['mapped_option_letter'] ?: null,
    'player_input' => $isContinue ? '__continue__' : $prompt,
    'state_delta' => $aiResult['state_delta'] ?? [],
    'recorded_at' => now()->toIso8601String(),
];
$gameUpdate['branch_resolution_log'] = $log;

// NEW — current_beat_type: derive from sessionAdaptation.session_architecture beat_map
//        keyed by event_id → beat_map[].beat_type. Skip if no adaptation.
$beatType = $this->deriveCurrentBeatType($currentEvent, $sessionAdaptation);
if ($beatType !== null) {
    $gameUpdate['current_beat_type'] = $beatType;
}

if (!empty($gameUpdate)) {
    $game->update($gameUpdate);
}
```

`deriveCurrentBeatType()` is a new private helper. Pseudocode:

```php
private function deriveCurrentBeatType(Event $event, ?SessionAdaptation $sa): ?string
{
    $beatMap = $sa?->session_architecture['beat_map'] ?? null;
    if (!$beatMap) {
        return null;
    }
    // Beat map entries don't carry event_ids today (they're time_range + beat_type + moment).
    // Map by position in the session: find the index of $event among the session's events
    // ordered by (chapter.position, event.position), and pick the beat-map entry by ratio.
    // If no clean mapping, return null and let the cache stay stale.
    // Implementation detail: sketch in B2.1 below.
    return null; // placeholder until B2.1 is decided
}
```

**B2.1 — beat-type derivation strategy:**
The `session_architecture.beat_map` from the agent uses time-ranges (e.g. `"0:00-2:30"`) and `beat_type` labels, not event ids. Two options:

- **Option a (cheap, less precise):** divide the session's events evenly across the beat_map entries. Beat 1 is events 1..⌈N/B⌉, etc. Approximate but enough for the system prompt to know "we're in escalation".
- **Option b (correct, requires schema):** add `event_id` (or `story_position`) to each beat_map entry by extending `SessionArchitectureJob` and its prompt. Higher lift, deferred.

Recommend Option a for B2.1 with a TODO noting Option b. The downstream impact of imprecise `current_beat_type` is small (it's a hint to the narrator); the impact of *no* beat type is that B3 has nothing to surface.

**Acceptance:** after a 6-turn game on Alice, `SELECT branching_choices_taken, tracked_dimensions, branch_resolution_log, current_beat_type FROM games WHERE id = ...` returns populated JSON, not all `null`.

---

### B3 — Surface tracked state back into the system prompt

**Goal:** Close the loop. The AI emits state, we persist it; now the next turn must *see* the state we persisted.

**Files:**

- `app/Http/Controllers/User/Game/PromptController.php` — `renderSystemPrompt()`
- `resources/views/ai/agents/narration/system-prompt.blade.php`

Pass three new variables into the view:

```php
'currentBeatType' => $game->current_beat_type,
'trackedDimensions' => $game->tracked_dimensions ?? [],
'branchingChoicesTaken' => $game->branching_choices_taken ?? [],
```

Add a new section to the system prompt, sited right before the `=== ADAPTATION LAYER CONTEXT ===` block (so that designed structure and tracked state appear adjacent):

```blade
@if(!empty($currentBeatType) || !empty($trackedDimensions) || !empty($branchingChoicesTaken))
=== PLAYER STATE (PERSISTED ACROSS TURNS) ===
@if(!empty($currentBeatType))
Current beat: {{ $currentBeatType }}
@endif

@if(!empty($branchingChoicesTaken))
Branching choices taken so far:
@foreach($branchingChoicesTaken as $choiceId => $letter)
- {{ $choiceId }}: option {{ $letter }}
@endforeach
@endif

@if(!empty($trackedDimensions))
Tracked dimensions (player path on each):
@foreach($trackedDimensions as $dimName => $entry)
- {{ $dimName }}: path {{ $entry['current_path'] ?? '?' }} (set at {{ $entry['choice_id'] ?? 'unknown' }})
@endforeach
@endif

These are AUTHORITATIVE. Honor them. Do NOT contradict prior choices the player has already committed to.
@endif
```

**Acceptance:** turn N+1's system prompt includes a `=== PLAYER STATE ===` block listing whatever B2 wrote on turn N. Verify by reading `storage/app/debug/system_prompt_<turn>.txt` produced by the simulator.

---

### B4 — Authored-choice detection

**Goal:** When the player picks an authored A/B/C, the runtime sets `mapped_choice_id` + letter *deterministically*, instead of trusting the AI to self-classify.

**File:** `app/Http/Controllers/User/Game/PromptController.php` — `store()` (before the AI call)

Sketch:

```php
private function detectAuthoredChoice(string $playerPrompt, Event $currentEvent, ?SessionAdaptation $sa): array
{
    $choiceDesign = $sa?->session_choice_design;
    if (!$choiceDesign) {
        return ['choice_id' => null, 'letter' => null];
    }

    foreach (['branching_choice_1', 'branching_choice_2', 'branching_choice_3'] as $key) {
        $bc = $choiceDesign[$key] ?? null;
        if (!$bc) continue;

        foreach (['option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C'] as $optKey => $letter) {
            $authored = trim((string) ($bc[$optKey]['text'] ?? ''));
            if ($authored !== '' && trim($playerPrompt) === $authored) {
                return [
                    'choice_id' => $bc['choice_id'] ?? $key,
                    'letter' => $letter,
                ];
            }
        }
    }

    return ['choice_id' => null, 'letter' => null];
}
```

Then in `store()`:

```php
$authored = $this->detectAuthoredChoice($prompt, $currentEvent, $sessionAdaptation);
// Pass into the system prompt or the player message so the AI knows
// "the player picked authored option B of S1_C1 verbatim".
```

Inject into the user-message prompt template (`prompt.blade.php`) when `$authored['choice_id']` is set:

```blade
@if(!empty($authoredChoiceId))
[AUTHORED CHOICE SELECTION DETECTED] Player selected option {{ $authoredOptionLetter }} of {{ $authoredChoiceId }}.
@endif
```

This gives the model deterministic ground truth and lets B2's persistence step trust the deterministic detection (overriding the model's `mapped_choice_id` if they disagree).

**Edge case:** the runtime currently doesn't render *only* the authored options on a branching slot — it's the AI's job to interleave authored options with generated ones. Detection is conservative (exact string match). Fuzzier matching (Levenshtein, normalized whitespace) can be added later if exact match misses.

**Acceptance:** when a player clicks an authored A/B/C, `branching_choices_taken` records `{S1_C1: "A"}` deterministically — independent of whether the model also returned the same classification.

---

## 3. WS-C — Opening narration uses the cold open

**Why:** Diagnosis §6 — `entry_point_diagnosis.cold_open` is authored, story-tone-correct (verified for Alice in `database/exports/adapptation-third-try.json`), and ignored by `StoryOpeningGeneratorJob`. The fix is a wiring change.

### C1 — `StoryOpeningGeneratorJob` reads the Session 1 cold open

**File:** `app/Jobs/Story/StoryOpeningGeneratorJob.php`

Add a lookup for the completed Session 1 `entry_point_diagnosis`:

```php
public function handle(): void
{
    try {
        $storyData = $this->story->system_prompt ?? [];

        $firstChapter = $this->story->chapters()->orderBy('position')->first();
        $firstEvent = $firstChapter?->events()->orderBy('position')->first();

        // NEW — pull the authored cold open if Session 1 of the adaptation is complete.
        $session1 = $this->story->adaptation
            ?->sessionAdaptations()
            ->where('session_number', 1)
            ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
            ->first();

        $coldOpen = $session1?->entry_point_diagnosis['cold_open'] ?? null;
        $emotionalPromise = $session1?->entry_point_diagnosis['emotional_promise'] ?? null;

        $response = (new OpeningNarrationAgent)->prompt(
            view('ai.agents.opening-narration.prompt', [
                'title' => $this->story->title,
                'teaser' => $this->story->teaser,
                'characterName' => $storyData['character_name'] ?? null,
                'toneAndStyle' => $storyData['tone_and_style'] ?? null,
                'worldRules' => $storyData['world_rules'] ?? [],
                'firstChapterTitle' => $firstChapter?->title,
                'firstEventContent' => $firstEvent?->content,
                // NEW
                'coldOpen' => $coldOpen,
                'emotionalPromise' => $emotionalPromise,
            ])->render()
        );

        $this->story->update(['opening' => $response['opening']]);
        // ...
    }
}
```

Update the user-message prompt at `resources/views/ai/agents/opening-narration/prompt.blade.php`:

```blade
@if(!empty($coldOpen))
=== AUTHORED COLD OPEN (USE AS SECTION 2 SOURCE) ===
The following cold open has been authored by the adaptation pipeline as the canonical first-scene direction for this story. Use it as the SOURCE for Section 2 of the opening preamble. You may compress, re-segment for `<br>` line breaks, or polish for staccato rhythm — but you MUST preserve its voice, sensory detail, and emotional promise. Do NOT substitute generic phrasing.

COLD OPEN:
{{ $coldOpen }}

@if(!empty($emotionalPromise))
EMOTIONAL PROMISE: {{ $emotionalPromise }}
@endif
@endif
```

Update the system prompt at `resources/views/ai/agents/opening-narration/system-prompt.blade.php` — Section 2 instructions:

```blade
SECTION 2: STORY-SPECIFIC INTRODUCTION (~15 lines max)
- IF an AUTHORED COLD OPEN is provided in the user message, use it as the canonical SOURCE for Section 2. Preserve its voice and sensory detail; you may re-segment into <br>-separated lines for staccato pacing, but do not invent new content or substitute its phrasing.
- IF no AUTHORED COLD OPEN is provided, fall back to the existing flow: announce the title, introduce the character/setting, hint at the central threat, end with a memorable mantra and "Welcome... to [Story Title]".
```

**Acceptance:** Running `php artisan tinker --execute="App\Jobs\Story\StoryOpeningGeneratorJob::dispatchSync(App\Models\Story::where('slug','alices-adventures-in-wonderland')->first());"` produces a `stories.opening` whose Section 2 reads in Carroll's voice and references "the White Rabbit", "the watch", "the rabbit-hole", consistent with the cold open.

---

### C2 — Post-adaptation hook: regenerate opening when pipeline completes

**Why:** Stories are created before the adaptation runs. Without a hook, `stories.opening` is forever the no-cold-open version even after the adaptation finishes.

**File:** `app/Jobs/Adaptation/AdaptationStatusReconciliationJob.php` (likely candidate — confirm by reading the file; whichever job marks `adaptation_status = COMPLETED` is the right place).

After `adaptation_status` flips to `COMPLETED`, dispatch `StoryOpeningGeneratorJob` for the story:

```php
if ($adaptation->adaptation_status === AdaptationStatusEnum::COMPLETED) {
    StoryOpeningGeneratorJob::dispatch($adaptation->story)
        ->onQueue('opening-generation');
}
```

This is idempotent (the job replaces `stories.opening` wholesale). Add an INFO log so we can see in the trace that an opening was regenerated.

**Edge case:** for stories that already have a (no-cold-open) `opening` and an adaptation already complete, ship a one-shot artisan command `story:regenerate-opening {slug}` for backfill.

**Acceptance:** when the adaptation pipeline transitions Alice's story to `completed`, the `opening` field is automatically refreshed within ~minute. Log line `StoryOpeningGeneratorJob: Generated opening for story [N]` appears in the queue worker output.

---

### C3 — Frontend render adjustment for paragraph prose

**File:** `resources/js/components/GameOpeningNarration.vue`

The current segmenter splits on `<br>`. Cold-open prose is paragraph-based (`\n\n`), not staccato-line-based. After C1 ships, Section 1 will still be `<br>`-separated (Lorespinner welcome) but Section 2 will be paragraph prose. The component must handle both.

Two small changes:

1. **Segmenter** — split on either `<br>` or `\n\n` so paragraphs are first-class:
   ```ts
   const segments = computed(() => {
       return props.opening
           .split(/<br\s*\/?>|\n\s*\n+/gi)
           .map((s) => s.trim())
           .filter((s) => s.length > 0);
   });
   ```

2. **Per-segment delay** — paragraphs are longer than punchy lines. Bump the upper bound and use word count rather than character count:
   ```ts
   const wordCount = current.split(/\s+/).filter(Boolean).length;
   const isShort = wordCount <= 6;
   const delay = isShort
       ? Math.min(80 + current.length * 2, 250)
       : Math.min(400 + wordCount * 30, 1400);
   ```

   Short Section-1 lines reveal in ~80–250ms (current behavior preserved). Section-2 paragraphs reveal in ~400ms–1.4s, giving the player time to read each paragraph before the next appears.

**Acceptance:** visual QA on Alice's regenerated opening — Section 1 reads as the existing branded staccato; Section 2 reads as Carroll-voiced paragraphs that don't ladder into a narrow column.

---

### C4 — Optional follow-up: tone the existing reference example

The system prompt currently includes a hardcoded reference example (the Session Zero pitch) that the agent is told to match in length/structure. With C1 sourcing Section 2 from the cold open, the reference example is only relevant for Section 1 + the "fallback flow" (no cold open). Tighten the example's framing in `system-prompt.blade.php` so the agent doesn't try to rewrite Section 2 in the example's voice when a cold open is available:

```blade
=== REFERENCE EXAMPLE (FOR SECTION 1 ONLY — and for Section 2 fallback when no cold open is provided) ===
```

No code change beyond the heading; pure documentation hygiene. Defer if time-pressed.

---

## 4. WS-D — Lower-priority follow-ups

These are listed for completeness; none are blocking the next playtest. Cross-references in parentheses.

| Item | Source | Estimated effort |
|---|---|---|
| Q4 alignment fix (beat map ↔ choice design choice IDs) | `adaptation-3-analysis.md` §5.1 | M — pipeline prompt + validation |
| Branch-dimensions pollution from Phase 5 | `branch-dimensions-pollution-bug.md` | S — pick fix 5.1 or 5.4 from that doc |
| S1↔S2 cold-open overlap (Session 2 replays the fall) | `adaptation-analysis.md` §4.2, diagnosis §1.3 | S — adjust S2 entry-point prompt or beat map |
| Tighten Phase 5 choice prompt: forbid passive "wait/observe" as mandatory; require three mechanically distinct downstream effects | `curt-feedback-diagnosis.md` §5.2 | S — prompt only |
| TTS feature probe + error toast | `curt-feedback-diagnosis.md` §7.2 | XS — only if TTS issues recur |

---

## 5. Test plan (single Alice playthrough)

After WS-A + WS-B + WS-C ship, run a manual smoke playthrough on Alice that exercises every fix. Compare against pre-fix behavior.

### 5.1 Pre-fix snapshot (baseline)

Before any code change ships, capture the current state for comparison:

```bash
# Get the current opening
php artisan tinker --execute="echo App\Models\Story::where('slug','alices-adventures-in-wonderland')->first()->opening;" > /tmp/pre-fix-opening.html

# Drop any test games for this story (keep production data — query first)
# manual: pick a test user account, reset
```

### 5.2 Post-fix verification

| # | Action | Expected | Evidence |
|---|---|---|---|
| 0 | After 8+ turns: `php artisan tinker --execute="App\Models\Game::find('<id>')->prompts()->latest()->first()->prompt"` | Returns the most recent player input (not turn-1's input, not `null`) | WS-0 |
| 1 | Run `php artisan game:trace <id>` on a fresh Alice game | Table renders, all 4 hard-rules green | A1 |
| 2 | Inspect first turn's system prompt dumped to `storage/app/debug/` | Contains `=== TURN STATE === This is TURN 1 of this event` | A2 |
| 3 | After 3 turns inside event 1, dump turn 3's system prompt | Contains `=== TURN STATE === This is TURN 3 ... screenplay was already narrated` | A2 |
| 4 | After 5 turns, run `SELECT branching_choices_taken, tracked_dimensions, branch_resolution_log FROM games WHERE id=...` | All three populated, `branch_resolution_log` has 5 entries with valid `input_classification` values | B1, B2 |
| 5 | Click an authored A/B/C choice on a branching slot | `branching_choices_taken` gets `{S1_C1: "A"}` (or whichever); turn-trace shows `branch_aligned` classification | B4 |
| 6 | Inspect turn N+1's system prompt after a state-changing turn N | Contains `=== PLAYER STATE ===` block listing taken choices and tracked dimensions | B3 |
| 7 | Inspect `stories.opening` after backfill | Section 1 still says "Welcome... to Lorespinner Interactive."; Section 2 reads in Carroll voice and matches the Session 1 cold open's beats | C1, C2 |
| 8 | Visit the game page on a fresh Alice game | Opening narration animates: Section 1 punchy lines (existing pacing), Section 2 paragraph reveals on slower cadence | C3 |
| 9 | Re-run trace: confirm `event_id_after >= event_id_before` for every turn (no rewind) | Curt's #1 doesn't surface | A1 rule 1 |
| 10 | Verify narrator's first 120 chars on turn 2 don't share >30% n-gram overlap with turn 1's first 120 chars | Curt's #2 doesn't surface | manual review of trace |

### 5.3 Failure modes to watch

- **The model rejects the new schema fields (returns invalid `input_classification`).** Falsy enum values should fall through to `expressive` (safest default) rather than aborting the turn. Implement defensive defaults in B2.
- **Authored-choice detection misses by whitespace.** Add `Str::squish` to both sides of the equality check; if still missing, log the diff for inspection.
- **`StoryOpeningGeneratorJob` runs before adaptation is complete.** C1 falls back to the existing flow when `$session1` is null — so this is safe.
- **Section-2 cold-open rendering is too dense for the typewriter.** C3's word-count delay should handle this; if paragraphs scroll past too fast, raise the `Math.min(400 + ...)` ceiling.

---

## 6. Branching strategy

Recommended slicing for clean review (direct-commit on the working branch per current request — no PRs):

0. `fix/game-prompts-ordering` — WS-0 only. **Already applied locally; awaiting review before commit.**
1. `feat/narration-trace-logging` — A1 only.
2. `feat/narration-first-turn-signal` — A2 only.
3. `feat/narration-state-schema` — B1 only (schema change, no persistence yet — agents return new fields, runtime ignores them).
4. `feat/narration-state-persistence` — B2 + B3 + B4 (the runtime now reads/writes/feeds-back the state).
5. `feat/opening-uses-cold-open` — C1 + C2 + C3 (+ C4 documentation tidy).

Each branch is independently shippable. Order matters mostly for the trace logs to mean something during testing — A1 first means every later branch has its own evidence.

---

## 7. Out of scope for this fix cycle

For clarity:

- **TTS** — verified working; no code change.
- **Adaptation-pipeline content quality issues** (Q4 alignment, branch_dimensions pollution, S1↔S2 overlap) — listed in WS-D, not blocking the next playtest.
- **Schema migration for `events.story_position`** — already noted as a follow-up in `fix-event-position-drift-process-log.md` §7. Not needed for any fix above.
- **Filament admin UI for inspecting persisted state** — could come for free via the trace log; defer until a real admin user asks.

---

## 8. Files touched (summary)

### 0 (WS-0 — already applied locally)
- `app/Models/Game.php` (drop `->oldest()` from the `prompts()` relation; add docblock explaining the trap)
- `app/Http/Controllers/User/GameController.php` (`show()` eager-load adds explicit `oldest()` constraint via closure form to preserve UI ordering)

### A
- `app/Http/Controllers/User/Game/PromptController.php` (A1 logging hook + A2 isFirstTurnInEvent)
- `app/Http/Controllers/User/GameController.php` (A2 isFirstTurnInEvent on first narration)
- `resources/views/ai/agents/narration/system-prompt.blade.php` (A2 turn-state block)
- `app/Console/Commands/GameTraceCommand.php` (A1 — new file)
- `config/logging.php` (A1 — add `narration` channel)

### B
- `app/Ai/Agents/NarrationAgent.php` (B1 schema)
- `app/Http/Controllers/User/Game/PromptController.php` (B2 persistence + B4 detection)
- `resources/views/ai/agents/narration/system-prompt.blade.php` (B3 player-state block)
- `resources/views/ai/agents/narration/prompt.blade.php` (B4 authored-choice signal)

### C
- `app/Jobs/Story/StoryOpeningGeneratorJob.php` (C1 cold-open lookup)
- `resources/views/ai/agents/opening-narration/prompt.blade.php` (C1 cold-open injection)
- `resources/views/ai/agents/opening-narration/system-prompt.blade.php` (C1 + C4 source policy)
- `app/Jobs/Adaptation/AdaptationStatusReconciliationJob.php` (C2 post-adaptation hook)
- `app/Console/Commands/StoryRegenerateOpeningCommand.php` (C2 backfill — new file)
- `resources/js/components/GameOpeningNarration.vue` (C3 paragraph rendering)

---

*End of fix plan. Cross-link to `curt-feedback-diagnosis.md` for the why and `runtime-logic.md` for the per-turn data flow context.*
