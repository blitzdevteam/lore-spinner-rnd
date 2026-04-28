# Curt Feedback Fix — Process Log v2

**Started:** 2026-04-28  
**Prior log:** [`curt-fix-process-log.md`](curt-fix-process-log.md) — WS-0 → WS-C → WS-A → WS-B (through batch commit `a7272d2`).  
**Companion to:** [`curt-fix-validation-runbook.md`](curt-fix-validation-runbook.md)  
**Scope:** Post–Batch 3 live-validation failures (silent LLM stubs, OpenAI strict-schema rejection, B4 matcher shape mismatch, trace instrumentation drift).

---

## Rollback anchors

**Previous stable HEAD before this v2 work:** `aad7c42` (parent of session-sync commit). Curt Fix v2 narration/runtime fixes live entirely on `aad7c42`. Session mirroring is `d613904`.

Single-commit rollback — Curt Fix v2 only (restores silent stub + old matcher + trace typing only):

```bash
git revert d613904 aad7c42
```

Order matters newest-first when reverting a linear chain; or revert individually:

| Scope | Command |
|---|---|
| Session mirroring + trace rule fix only | `git revert d613904` |
| Curt Fix v2 (schema + stubs + B4 + observability + runner/runbook) only | `git revert aad7c42` |

Full rollback of **everything** since the original pre–WS-0 anchor remains unchanged from v1:

```bash
git reset --hard 6dd6fa943a23cecb709e61367205c4cca69bd26a
```

---

## Why v2 existed

PASS 1 validation against `curt-fix-validation-runbook.md` showed empty `world_state`, generic `"The scene unfolds before you..."` narration, `input_classification="freeform"` (not in schema), all-zero `state_delta_summary`, B4 deterministic matcher returning `(none)` for real inputs, and `game:trace` crashing with `assertHardRules(): Argument #1 ($eventBefore) must be of type ?string, int given`.

Root causes identified:

1. **`PromptController::generateNarration()`** swallowed every LLM exception and substituted a hard-coded stub — masking structured-output failures completely.
2. **OpenAI strict structured output** (`schema.strict: true` via Laravel AI / Prism) requires `additionalProperties: false` on **every** nested JSON Schema object. `NarrationAgent::schema()` nested `state_delta` item objects lacked `->withoutAdditionalProperties()`; only the root `ObjectSchema` wrapper set it. API returned 400; catch returned stub.
3. **`matchAuthoredChoice`** iterated flat `{option,text}` rows; real `session_choice_design` uses `branching_choice_*.option_a/b/c.text` and `expressive_choices[]`.
4. **`GameTraceCommand`** typed event ids as `?string` while `events.id` is integer in typical seeds.
5. **`GameController::generateFirstNarration`** fallback returned Project Gutenberg HTML from `events.content` on LLM failure — looked like “success” but was not the adaptation cold open.

---

## Batch A — Curt Fix v2: No Fallbacks, Real Turns

**Commit:** `aad7c42`  
**Goal:** Restore real LLM turns with persistent `world_state`; eliminate silent stubs; make failures observable.

### Files touched

| File | Change |
|---|---|
| `app/Ai/Agents/NarrationAgent.php` | Chain `->withoutAdditionalProperties()` on `state_delta` parent and nested item objects (`objects_acquired`, `objects_transformed`, `conditions_added`, `relationship_changes`, `tracked_path_update`). |
| `app/Http/Controllers/User/Game/PromptController.php` | Remove silent stub; log `narration.llm_success` / `narration.llm_failed`; wrap `generateNarration()` in try/catch at `store()` boundary with flash error (no prompt row on failure); rewrite `matchAuthoredChoice` + add `extractAuthoredOptions()` for real nested choice design; empty-string defaults instead of `freeform` when missing. |
| `app/Http/Controllers/User/GameController.php` | Remove `formatEventContentAsHtml` fallback from first narration; log `narration.cold_open_audit` + `narration.llm_success`; `begin()` flashes error if LLM fails. |
| `app/Console/Commands/GameTraceCommand.php` | `assertHardRules` event parameters `int\|string\|null`. |
| `Adaptation layer/debug/wsb-validation.php` | Synthetic `session_choice_design` matches pipeline nesting. |
| `Adaptation layer/debug/curt-fix-validation-runner.php` | `step11` direct `NarrationAgent` probe; `step9` inputs aligned to canonical overlap with S1 export. |
| `Adaptation layer/debug/curt-fix-validation-runbook.md` | PASS 2 order, Step 8 log row taxonomy, Step 9 + Step 11, rollback row for v2. |

### Validation evidence (local / export)

- In-process schema serialization: nested objects serialize with `additionalProperties: false` after wrap-through `ObjectSchema`.
- `wsb-validation.php`: `failures: 0` (28 checks).
- Export JSON smoke: `extractAuthoredOptions` yields 18 candidates for Alice S1; matcher matches canonical near-verbatim inputs to `S1_C1` A/B.

End-to-end PASS 2 remains container + API-key dependent — see runbook.

---

## Batch B — Session mirroring + trace rule semantics

**Commit:** `d613904`  
**Goal:** Align `games.current_session_number` with `events.session_number` for the current event; fix false-positive **rule 3** on `game:trace`; authoritative `session_number_after` in logs.

### Problem

`narration.turn` logged `session_number_before` from **`$currentEvent->session_number`** (event row) but `session_number_after` from **`$game->current_session_number`** (denormalized column only updated on cross-session advances). Reset/create left game column `null` while event had `1` → bogus `1 → null` and trace rule 3 fired.

### Files touched

| File | Change |
|---|---|
| `app/Actions/Game/CreateGameAction.php` | Set `current_session_number` from resolved start event on create. |
| `app/Http/Controllers/User/GameController.php` | `reset()` sets `current_session_number` to start event’s `session_number`, not `null`. |
| `app/Http/Controllers/User/Game/PromptController.php` | On every event advance, sync `current_session_number` to next event’s `session_number`; log `session_number_after` from `$game->currentEvent->session_number` after refresh; add `game_current_session_number_after` diagnostic. |
| `app/Console/Commands/GameTraceCommand.php` | Rule 3: only flag regression when both session numbers are non-null integers and `after < before`; print drift note if denormalized column ≠ event. |
| `Adaptation layer/debug/curt-fix-validation-runbook.md` | Document rule 3 semantics + drift note. |

---

## Curt symptom mapping (post v2)

| Symptom | Fix batch |
|---|---|
| “Scene unfolds…” / generic choices | A — kill stub |
| Empty `world_state` despite play | A — schema strict-mode |
| `(none)` on real branching inputs | A — B4 nested shape |
| `game:trace` TypeError on event id | A — signature |
| Rule 3 false positive `1 → null` | B — mirror + log source + rule semantics |

---

## USER REVIEW CHECKPOINT — v2 complete

- Curt Fix v2 landed and pushed (`aad7c42`).
- Session/trace follow-up landed and pushed (`d613904`).
- Next: maintain PASS 2 green on deployed container; if `narration.llm_failed` appears, read exception message (no silent masking).

Append future batches (e.g. WS-D deferred items from `curt-feedback-fix.md`) below this checkpoint.
