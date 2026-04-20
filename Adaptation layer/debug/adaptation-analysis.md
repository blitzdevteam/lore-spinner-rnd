# Adaptation Analysis — `adpataion-second-try.json`

**Source:** `database/exports/adpataion-second-try.json`
**Story:** Alice's Adventures in Wonderland (id: 1)
**Exported:** 2026-04-16T16:30:36+00:00
**Adaptation status:** `adapting-sessions`

---

## 1. High-Level Structure

The adaptation JSON is organized in two tiers:

1. **`story_wide`** — macro strategic layer:
   - `format_detection`
   - `ip_audit`
   - `story_session_map` (session allocation, arc progression, branch opportunities, cross-session payoff plan, branch dimensions)
2. **`sessions[]`** — per-session deep design, 7 sessions. Each session contains 6 design phases:
   - `entry_point_diagnosis` (Phase 3 — the cut / cold open)
   - `session_architecture` (Phase 4 — beat map)
   - `session_choice_design` (Phase 5 — branching + expressive choices)
   - `choice_consequence_map` (Phase 6 — echoes + payoffs)
   - `session_close_design` (Phase 7 — resolution + hook + end choice)
   - `editorial_verification` (Phase 8 — 10-question QA)

---

## 2. Per-Session Status Snapshot

| Session | Status | Editorial Verdict | Primary Issue |
|---|---|---|---|
| 1 | completed | **RED** | Q4 Consequential Choices — S1_C2 (DRINK ME) collapses back to canonical outcome regardless of option |
| 2 | completed | **AMBER** | Q4 — Branching Choice 2 mismatch: beat map says DRINK ME, choice design + consequence map are written for Rabbit-rejection. Either 4 branching choices exist or one is un-mapped |
| 3 | completed | **AMBER** | Q4 — S3_C3 is structurally branching but labeled EXPRESSIVE in beat map; S3_C1's in-session consequence is too tonal, needs concrete state change per option |
| 4 | **failed** | n/a | All phase fields `null` |
| 5 | **failed** | n/a | All phase fields `null` |
| 6 | **failed** | n/a | All phase fields `null` |
| 7 | **failed** | n/a | All phase fields `null` |

**Only 3 of 7 sessions completed, all three failed the same QA question.**

---

## 3. Game Entry Point (Where The Story Starts)

From `sessions[0].entry_point_diagnosis`:

- **`start_event_position: 1`**
- **`start_event_id: 80`**
- **`cut_point`**: *"Chapter 1 / Prologue and the Descent — Alice notices the White Rabbit: paragraph beginning 'There was nothing so very remarkable in that; … but when the Rabbit actually took a watch out of its waistcoat-pocket…'"*
- **Cut eliminates:** bank-side boredom, daisy-chain, sister-with-book, weather framing.
- **Cold open:** Alice already rising from the bank as the Rabbit flashes past, watch-glint, then chase to the rabbit-hole edge.
- **Emotional promise:** *"pursuit"* — the user arrives feeling restless and wanting to chase the impossible.
- **First branching choice (S1_C1):** arrives at the lip of the rabbit-hole (jump / study / listen).

> The game's true opening moment for the user is the Rabbit-with-watch reveal, followed immediately by a point-of-no-return choice at the hole within 300 words.

---

## 4. Systemic Issues

### 4.0 CRITICAL RUNTIME GATE — Cold open will NOT fire in current state

`CreateGameAction::resolveStartEvent` gates the cold-open event routing on **story-level** `adaptation_status === COMPLETED`:

```36:49:app/Actions/Game/CreateGameAction.php
        if ($adaptation?->adaptation_status !== AdaptationStatusEnum::COMPLETED) {
            return $firstEvent;
        }

        $session1 = $adaptation->sessionAdaptations()
            ->where('session_number', 1)
            ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
            ->first();

        $startEventId = $session1?->entry_point_diagnosis['start_event_id'] ?? null;
```

Alice's export has `adaptation_status: "adapting-sessions"`. Session 1 is completed, but because S4–S7 failed, the overall status will never flip to `completed` and will most likely land on `partial-completion` or `failed` after reconciliation.

**Runtime impact:** the game starts at event position 1 (first event of first chapter, i.e. the Rabbit-with-watch moment from the source text unedited), **not** at the cold-open `start_event_id` (80). The carefully designed cut never takes effect.

Separately, the blade template has a **per-session** gate that would still inject the cold open if S1 is completed:

```227:227:resources/views/ai/agents/narration/system-prompt.blade.php
@if(!empty($sessionAdaptation) && $sessionAdaptation->session_status === \App\Enums\Adaptation\SessionAdaptationStatusEnum::COMPLETED)
```

So the template is more permissive than the controller. Net effect:
- The LLM **would** receive the cold-open brief (if the first event's `session_number = 1`)
- But the game **starts at the wrong event**, so the brief describes a scene the event-content disagrees with

**Suggested fix direction (not patched):**
- Relax the `CreateGameAction` gate to:
  ```php
  if ($session1?->session_status === SessionAdaptationStatusEnum::COMPLETED
      && ! empty($session1->entry_point_diagnosis['start_event_id'])) {
      // route to cold-open event
  }
  ```
- I.e., treat session-1 completion as sufficient for session-1 routing, independent of whole-story status. This mirrors the blade's per-session permissiveness and lets users experience Session 1 even while the rest of the pipeline is still adapting/failed.

A **dry-run simulator** is available to verify current state on any environment without touching the DB:

```bash
php artisan game:simulate-start alices-adventures-in-wonderland
```

It prints: adaptation gate status, resolved start event (vs first event), session lookup result, which adaptation blocks got injected into the system prompt, and a final PASS/PARTIAL/NOT-WIRED verdict. Full rendered prompt is dumped to `storage/app/debug/` for inspection.

---

### 4.1 All three completed sessions fail Q4 (same failure mode)

Every completed session was flagged on **Q4 — Consequential Choices**:

- **S1:** Path C explicitly says *"finally does drink (or is forced into change)"* — collapses all three options back to the canonical shrink → key-unreachable beat.
- **S2:** The middle branching choice is defined as TWO different things across artifacts (DRINK ME in beat map vs Rabbit-rejection in choice design + consequence map).
- **S3:** S3_C3 is a branching session-end choice by architecture (three distinct Session 4 openings) but is labeled EXPRESSIVE in the beat map. S3_C1's per-path consequence is mostly narration tone, not state.

This is **not three isolated bugs** — this is a **generator-level pattern**: the choice architect is producing options that *sound* divergent but re-converge to the canonical source beat. The verification phase catches it but the upstream phases don't prevent it.

### 4.2 Session-time regression between S1 and S2

- **S1 `start_event_position`:** 1 (Rabbit-with-watch)
- **S1 beat map covers:** cold open → fall → hall → bottle → cake → grow → tear-pool → Rabbit returns → fan/gloves → identity spiral
- **S2 `start_event_position`:** 15 (already falling)
- **S2 cold open:** Alice mid-fall, grabs marmalade jar

**S2 cold open reopens material S1 already dramatized (the fall).** The story-session-map assigns S1 to "events 1-8" and S2 to "events 9-21", so the intent is chapter 2 pool material — but the actual generated cold open for S2 regresses to a S1 beat. This double-dramatizes the fall and will produce whiplash for any user session-to-session.

Likely cause: S2's entry-point job received inadequate "where S1 ended" context, so it re-diagnosed from the source text instead of from S1's resolution state.

### 4.3 Session 2 `editorial_verification.consequential_choices` contradicts its own artifact

S2's editorial verification says explicitly:
> *"in the beat map it is the DRINK ME decision at 6:30–8:30, but in the choice design it is the Rabbit-rejection response, and the consequence map is written for the Rabbit-rejection version."*

But S2's `choice_consequence_map.consequence_map_choice_1` is for `curiosity_vs_caution` (jar), and `consequence_map_choice_2` is for `compliance_vs_defiance` (Rabbit rejection). There is **no consequence map entry for DRINK ME**. The beat map references DRINK ME as a branching choice at 6:30–8:30, but no branching choice definition or consequence map exists for it. Either the beat map is wrong, or Choice 2 is missing its design.

### 4.4 `story_wide.branch_dimensions` has duplicate / malformed entries

Entries at the end of the array:

```
"compliance_vs_defiance(how_alice_responds_to_hostile_authority_and_a_crowd_trying_to_force_her_out)."
"identity_stability_vs_adaptation(how_alice_defines'self'_under_pressure,_with_payoff_in_session7_when_she_is_called_as_witness)."
"control_vs_surrender(how_alice_uses_deliberate_size-control_to_approach_the_next_social_space_in_session4)."
```

These are kebab-snake-paren hybrids, each carrying `"origin": "phase_5"`. They are **re-emissions of dimensions already declared earlier** in the same `branch_dimensions` array (e.g., plain `compliance_vs_defiance` and `identity_stability_vs_adaptation` both exist earlier). The Phase 5 worker is appending new dimension records instead of either (a) reusing the existing canonical name or (b) upserting into the existing record.

**Consequence:** downstream payoff tracking, UI filters, and any dimension-keyed analytics will split the same semantic dimension across two rows with different ids.

### 4.5 `cross_session_payoff_plan` mixes two ID namespaces

Entries reference:
- `"S1E1"`, `"S1E6"`, `"S2E16"`, `"S5E39"`

These look like `S{session}E{event_position}` — **event-position IDs**, not choice IDs. But `branch_dimensions` uses `choice_id` values like `S2_C1`, `S1_C2`, `S3_C1`. The two namespaces never reconcile. A runtime that wants to fire a payoff based on a branching choice cannot join these tables.

Also: Session 3 is completed but has **zero** cross-session payoff entries. The plan is not being extended as sessions complete.

### 4.6 `story_session_map.branch_opportunities` references events, not choices

21 entries, each keyed by `event_position` + `event_title` + `choice_dimension`. This is a *pre-design* scouting list from Phase 2, and it never gets reconciled against the actual Phase 5 choices. Example:

- Branch opportunity S1E1 (notices Rabbit) → `curiosity_vs_caution` → payoff S7 verses/trial.
- Actual S1_C1 choice is `curiosity_vs_caution` at the rabbit-hole threshold.
- No explicit link between them in the data.

A consumer can't tell whether `branch_opportunities` is aspirational, authoritative, or stale.

### 4.7 Sessions 4-7 are hard-failed with no error context

All four `failed` sessions contain literal `null` for every phase field, only `session_status` + `updated_at`. There is no error code, no failure phase marker, no retry counter, no last-successful-phase pointer. Debugging which phase broke requires going outside the export into logs.

---

## 5. Per-Session Detail

### Session 1 — RED

**What works:**
- Cold open lands `emotional_promise` ("pursuit") within the first paragraph.
- S1_C1 (rabbit-hole threshold) has clean per-path echoes to S2 and S7.
- S1_C3 (polite / defiance / observe) has concrete next-session opening differences.

**What fails:**
- **S1_C2 (DRINK ME) is not load-bearing.** Path C explicitly admits forced convergence: *"the player arrives at S1_C3 with a materially different state variable... when you finally do drink (or are forced into change)."* The revision instructions already say to split state by path (key possession / size band / Rabbit proximity / tear-pool size), but these are not implemented in the current artifact.

**Revision instructions already present in the JSON** (from verification) — can be applied directly.

### Session 2 — AMBER

**What works:**
- Entry point correctly identifies S1's ending as the Rabbit+fan+gloves moment and plants an identity hook at the close.
- S2_C1 (marmalade jar) has three distinct mechanical consequences (hand injury / trophy object / observation pattern).

**What fails:**
- **Choice 2 identity crisis.** Beat map, choice design, and consequence map disagree about whether Choice 2 is DRINK ME or Rabbit-rejection. Pick one, update all three artifacts.
- **Time regression:** S2 cold open replays S1's fall. Either (a) shift `start_event_position` to 18+ (post-hall, at the tear-pool) so S1 owns the fall cleanly, or (b) treat S2 cold open as a recap and immediately advance past the hall.

**Revision path:** keep Rabbit-rejection as S2_C2 (consequence map is already written for it), remove DRINK ME from branching, re-label the DRINK ME beat as deterministic or expressive.

### Session 3 — AMBER

**What works:**
- Clean cold open with highest-energy cut in the set (pebbles + fire threat at Rabbit house).
- S3_C2 (Caterpillar "Who are you?") has the strongest moral-weight framing across all three sessions.
- S3_C3 defines three clearly distinct Session 4 openings (hall/garden, Duchess smoke, deeper wood).

**What fails:**
- **S3_C3 labeled EXPRESSIVE in beat map** despite routing three divergent next-session openings. Promote to BRANCHING in the beat map.
- **S3_C1's current-session echo is tonal only** — the differences between paths are in narration tone, not in what actually happens. Need concrete state changes: e.g., Path A unlocks "Bill-the-chimney plan heard", Path B triggers an earlier "smoke timer" visibility drop, Path C grants a "layout intel" flag used at breakout.

**Revision path:** both fixes are already described in the JSON's `revision_instructions`.

### Sessions 4–7 — FAILED

All null. No failure metadata.

- **S3's close explicitly seeds S4** with three concrete openings (hall re-entry / Duchess kitchen / deeper wood). The design capital to start S4 exists — the generator just never ran or never completed.
- **Story-session map** has full allocation, arc progression, and primary dramatic questions for S4–S7. Design brief inputs are available.

---

## 6. Suggested Fix Priorities

### P0 — Pipeline / Retry

1. **Resume S4–S7 generation.** Identify which job class produced these nulls and why it bailed silently. Candidates from the codebase: `EntryPointDiagnosisJob`, `SessionArchitectureJob`, `ChoiceDesignJob`, `ConsequenceMappingJob`, `AdaptationStatusReconciliationJob`.
2. **Add per-session failure metadata** to the session record: `failed_phase`, `failed_at`, `error_message`, `retry_count`. Right now a failed session is indistinguishable from "never attempted".
3. **Add a dry-run / revalidate command** that re-runs only Phase 8 (`editorial_verification`) against existing completed sessions, so we can iterate on verification prompt without regenerating everything.

### P1 — Q4 Systemic Failure

4. **Tighten the Choice Design phase prompt** to require, per branching choice: three distinct *mechanical state changes* (not just narration tone) that survive at least into the next session. Explicitly forbid language like *"finally does drink (or is forced into change)"* that admits convergence.
5. **Add a pre-Phase-8 structural lint** that checks each branching choice has: (a) a beat-map entry typed BRANCHING, (b) a `session_choice_design` definition, (c) a `choice_consequence_map` entry keyed to the same `choice_id`. Fail fast before Phase 8 runs.

### P1 — Data Consistency

6. **Canonicalize `branch_dimensions`.** Deduplicate by dimension name; the Phase 5 worker should upsert into an existing dimension, not append a new row with `origin: phase_5`.
7. **Normalize IDs across `cross_session_payoff_plan` and `branch_opportunities`.** Pick one namespace: either event-position references (`S{n}E{pos}`) or choice references (`S{n}_C{n}`). Ideally both columns, explicitly, so runtimes can choose.
8. **Fix the S1↔S2 time overlap.** Either advance S2's `start_event_position` past the fall, or reframe S1's ending so it terminates before the fall. The story-session map's event-range allocation (S1: 1-8, S2: 9-21) is being ignored by the entry-point worker.

### P2 — UX / Debug Tooling

9. **Per-phase export mode.** Add an export flag to emit one phase at a time (entry_point, architecture, choices, consequences, close, verification) for any session, to make prompt iteration cheap.
10. **Verification delta report.** When `production_status` is RED or AMBER, surface the `revision_instructions` at the top of the export (not buried 1200 lines down per session), so debugging starts with the fix list already in hand.
11. **Cross-session consistency report.** Auto-generated doc that lists: (a) each branching choice's dimension, (b) where it's echoed, (c) where it pays off, (d) which pairs have no payoff — so we can spot orphan choices like S3_C* which currently have zero entries in `cross_session_payoff_plan`.

---

## 7. Open Questions For Debug Session

1. Do we know which job class handles which phase? A short map (phase → job → prompt file) would let us localize the Q4 failure mode to one prompt.
2. Are the 4 null sessions (S4–S7) failing on phase 3 (entry point) or did the pipeline bail before even dispatching them? This is the difference between "prompt bug" and "orchestration bug".
3. Is `story_wide.branch_dimensions` intended to be authoritative, or is `session_choice_design[].choice_id` authoritative? Canonical source matters for the de-dup fix.
4. Should `cross_session_payoff_plan` be hand-authored from `branch_opportunities`, or generated from completed `session_choice_design` entries? Right now it sits between the two with no clear owner.
5. Is there a feature flag or mode to re-run only failed sessions without re-running completed ones?

---

## 8. Quick Wins If Prioritizing For A Working Demo

If the goal is to get *one playable session* out the door cleanly rather than fix the whole pipeline:

- **Ship Session 1 only** with manual patches to S1_C2:
  - Path A (full drink) → shrink → key lost → tears (canonical).
  - Path B (sip) → partial shrink → can grab key but not fit door → altered cake beat (holds key through growth).
  - Path C (refuse) → Rabbit returns earlier, mistakes Alice for staff, cake appears but bottle vanishes.
- This matches the revision instructions already in the JSON and gives a demonstrably branching experience without requiring S2–S7 to exist.

---

*End of analysis.*
