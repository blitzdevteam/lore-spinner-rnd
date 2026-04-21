# Bug: `branch_dimensions` pollution from `ChoiceDesignJob`

**Severity:** Medium — corrupts the canonical branch-dimension registry with near-duplicate, malformed entries; also masks a concurrent-write race.
**Scope:** `StoryAdaptation.story_session_map.branch_dimensions` (JSON column on `story_adaptations`).
**Observed in:** `database/exports/adapptation-third-try.json` (Alice, post event-position fix).
**Source file:** `[app/Jobs/Adaptation/ChoiceDesignJob.php](app/Jobs/Adaptation/ChoiceDesignJob.php)` — `enrichBranchDimensionRegistry()`.
**Related but unrelated:** `fix-event-position-drift-process-log.md` (the just-shipped ordinal fix). This is a separate Phase-5 bug that survived that fix.

---

## 1. Symptom

After `RunAdaptationPipelineJob` completes, `story_session_map.branch_dimensions` contains:

- The ~5 canonical dimensions emitted by `StorySessionMapAgent` in Phase 2. These are clean:
  ```json
  { "dimension_name": "impulse_vs_deliberation",
    "description": "Whether Alice acts immediately on curiosity/instinct or pauses to observe, plan, and control outcomes." }
  ```
- **Plus** trailing entries appended by `ChoiceDesignJob` in Phase 5, with mangled `dimension_name` values like:
  ```json
  { "dimension_name": "impulse_vs_deliberation(how_you_commit_to_the_chase_when_the_impossible_appears).",
    "description": "impulse_vs_deliberation (how you commit to the chase when the impossible appears).",
    "possible_paths": { "option_a": "...", "option_b": "...", "option_c": "..." },
    "origin": "phase_5",
    "session_introduced": 1,
    "choice_id": "S1_C1" }
  ```

Every branching choice across every session ends up as its own pseudo-dimension. For a 4-session story with 3 branching choices per session, the canonical 5-6 dimensions are followed by up to 12 polluted entries. The canonical entries are never updated with per-choice `possible_paths` metadata, which was the feature's intent.

---

## 2. Root cause

In `ChoiceDesignJob::enrichBranchDimensionRegistry`:

```75:83:app/Jobs/Adaptation/ChoiceDesignJob.php
            $tracked = Str::snake(Str::lower($choice['what_this_choice_tracks']));
            $existingIndex = null;

            foreach ($dimensions as $i => $dim) {
                if (Str::snake(Str::lower($dim['dimension_name'])) === $tracked) {
                    $existingIndex = $i;
                    break;
                }
            }
```

`$choice['what_this_choice_tracks']` is free-form descriptive text from the Phase 5 agent, e.g. `"impulse_vs_deliberation (how you commit to the chase when the impossible appears)."`. Running `Str::snake(Str::lower(...))` on that produces `"impulse_vs_deliberation(how_you_commit_to_the_chase_when_the_impossible_appears)."` — the entire parenthetical descriptor is snake-cased into the key.

The canonical `dim['dimension_name']` is the clean `"impulse_vs_deliberation"`. After `Str::snake`, it stays `"impulse_vs_deliberation"`. So the equality check:

```
"impulse_vs_deliberation" === "impulse_vs_deliberation(how_you_commit_to_the_chase_when_the_impossible_appears)."
```

**always returns false.** The `else` branch on lines 95-103 always fires, pushing a NEW entry instead of enriching the existing canonical one:

```95:103:app/Jobs/Adaptation/ChoiceDesignJob.php
            } else {
                $dimensions[] = [
                    'dimension_name' => $tracked,
                    'description' => $choice['what_this_choice_tracks'],
                    'possible_paths' => $pathData,
                    'origin' => 'phase_5',
                    'session_introduced' => $this->sessionNumber,
                    'choice_id' => $choice['choice_id'] ?? null,
                ];
            }
```

**Net effect:** the feature is fully broken. No canonical dimension ever gets enriched with `possible_paths`; every choice appends a malformed duplicate.

---

## 3. Secondary defect: concurrent-write race

`ChoiceDesignJob` runs per session and is dispatched via `Bus::batch` on the `adaptation` queue. Multiple sessions' `ChoiceDesignJob` runs can overlap.

Each overlapping job does:

```106:108:app/Jobs/Adaptation/ChoiceDesignJob.php
        $sessionMap['branch_dimensions'] = $dimensions;
        $adaptation->update(['story_session_map' => $sessionMap]);
```

This is a read-modify-write cycle on a JSON column, with no transaction, no `SELECT FOR UPDATE`, and no versioning. If Session 2's job reads `story_session_map` BEFORE Session 1's job writes, Session 2's `update(...)` clobbers Session 1's additions. **Last writer wins, earlier writer's entries get lost.**

In Alice's third-try export, 4 `ChoiceDesignJob` instances ran (one per session). We still see pollution entries from multiple sessions — but without verification we can't be sure none were lost. Across stories with tighter scheduling or slower LLM responses, entries from earlier-completing sessions could silently disappear.

---

## 4. Who cares / downstream impact

1. **Prompt builders that read `branch_dimensions`** — any downstream job or narration system prompt that enumerates canonical dimensions will see ~10-15 noisy entries instead of 5. This bloats system prompts and dilutes the canonical-axis instruction.
2. **Analytics / choice dashboards** — grouping choices by dimension becomes unreliable since the same choice registers as both its canonical dimension (once, statically) and as a synthetic per-choice pseudo-dimension.
3. **Consistency guarantees** — `branch_dimensions` is supposed to be canonical (3-6 entries per the Phase 2 prompt instructions). The race condition means even a future fixed match-logic could lose entries, so strict invariants can't be relied on.

Game runtime currently does **not** read `branch_dimensions` directly for the narration agent prompt, so there is no user-visible failure today. This is a data-integrity bug, not a runtime bug.

---

## 5. Proposed fix (for later)

### 5.1 Canonical-name extraction

Have the Phase 5 agent return the canonical `dimension_name` explicitly (a new required field on `session_choice_design.branching_choice_N`), or parse it out of `what_this_choice_tracks` with a bounded regex:

```php
// Grab the leading snake_case token before any parenthesis/description
if (preg_match('/^([a-z][a-z0-9_]*)/', Str::lower($choice['what_this_choice_tracks']), $m)) {
    $tracked = $m[1];
} else {
    continue; // or throw — we don't want malformed keys polluting the registry
}
```

This reduces `"impulse_vs_deliberation (how you commit to the chase when the impossible appears)."` → `"impulse_vs_deliberation"`, which correctly matches the canonical dimension.

### 5.2 Eliminate the race

Wrap the enrichment in a transactional, locked read-modify-write:

```php
DB::transaction(function () use ($adaptation, $choiceDesign): void {
    $fresh = $adaptation->newQuery()
        ->where('id', $adaptation->id)
        ->lockForUpdate()
        ->firstOrFail();

    $sessionMap = $fresh->story_session_map;
    // ... merge $choiceDesign into $sessionMap['branch_dimensions'] ...
    $fresh->update(['story_session_map' => $sessionMap]);
});
```

With `lockForUpdate`, concurrent `ChoiceDesignJob` instances serialize on the `story_adaptations` row for the duration of the merge. No lost writes.

### 5.3 Schema guard

Consider adding an assertion at the end of `RunAdaptationPipelineJob` (or `AdaptationStatusReconciliationJob`) that `count($branch_dimensions) <= 8` (or whatever ceiling makes sense) and every `dimension_name` matches `/^[a-z][a-z0-9_]*$/`. If it doesn't, log a warning and optionally prune pollution. This gives us a self-healing belt when the Phase 5 agent or the enrichment logic regresses.

### 5.4 Alternative: treat `branch_dimensions` as write-once

Arguably, Phase 5 shouldn't touch `branch_dimensions` at all. The canonical list is set in Phase 2 and is complete. Per-choice `possible_paths` could live **inside the session's own `session_choice_design`** (where they already do) — `branch_dimensions` doesn't need a `possible_paths` field, it's a canonical index. Removing `enrichBranchDimensionRegistry` entirely may be the cleanest fix.

Recommendation: **choose 5.4 first**, unless there's a downstream consumer that specifically expects `branch_dimensions[].possible_paths`. If there is, do 5.1 + 5.2.

---

## 6. Reproduction / verification

1. Export a completed adaptation: `php artisan adaptation:export alices-adventures-in-wonderland --path=<file>.json`.
2. Search for `"origin": "phase_5"` or for `dimension_name` values containing `(`. Any hit is a pollution entry.
3. Count unique canonical-prefix tokens (everything before `(`). In a clean registry these should all be unique AND all should appear only once (merged with `possible_paths`).

After a fix:
- Canonical registry has exactly the Phase 2 count (3-6 entries).
- No `dimension_name` contains `(` or `.`.
- Each canonical entry may have `possible_paths` populated from a matched Phase 5 choice, or may have none if no Phase 5 choice tracked it.

---

## 7. Not fixing now because

- Game runtime does not read `branch_dimensions`, so no player-visible impact.
- Coupling: a proper fix either changes the Phase 5 agent schema (5.1) or removes `enrichBranchDimensionRegistry` (5.4). Both warrant review beyond the scope of the just-shipped event-position fix.
- Deferring keeps this cycle focused on getting Alice's game start green before expanding scope.

File this as a queued follow-up. When picked up, reference this doc and the sibling `fix-event-position-drift-process-log.md` for pipeline context.
