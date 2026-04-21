# Alice Adaptation — Third Try Analysis (Post-Fix)

**Source:** `database/exports/adapptation-third-try.json`
**Exported:** 2026-04-21T13:58:05+00:00
**Pipeline commit:** `30eaf2075f988fdc69f5a17aa22ee687d6cf613b` — "Adaptation: fix event-position drift via story-global ordinal"
**Companion docs:** `adaptation-analysis.md` (pre-fix), `fix-event-position-drift-process-log.md` (implementation)

---

## 1. Verdict

The fix works. Every canonical symptom from the pre-fix export is gone.

- `adaptation_status: "completed"` — full pipeline cleared to reconciliation.
- `confirmed_session_count: 4` — all 4 sessions reached `session_status: "completed"`.
- `session_allocation.event_range` values now tile `1..90` contiguously across the story (story-global ordinal, not per-chapter).
- Session 1's `start_event_id = 1` — the Alice-notices-the-Rabbit opening in Chapter 1. **No longer `id=63` "Duchess links arms..." from Chapter 6.**
- Every session's start event sits in the chapter that session covers (no cross-chapter drift).

---

## 2. Session allocation — contiguous story-global ranges

| Session | `event_range` | `chapters_covered` (agent) | Primary dramatic question |
|--------:|:-------------:|:---------------------------|:--------------------------|
| 1 | `1-23` | Ch.1 → early Ch.2 | Can Alice catch up to the White Rabbit by solving the hall's size-and-key puzzle without losing herself to panic? |
| 2 | `24-42` | Late Ch.2 → Ch.3 | Can Alice regain control of her size—and her sense of self—after being mistaken for 'Mary Ann' and chased into the wood? |
| 3 | `43-62` | Ch.4 → Ch.5 | Will Alice navigate Wonderland's social rules (and threats) to secure a place in the Garden without becoming complicit in cruelty? |
| 4 | `63-90` | Ch.6 → Ch.7 | Will Alice submit to the Court's nonsense, play along to survive, or openly defy it? |

**Ranges cover `1..90` with zero gaps and zero overlaps.** Alice has 90 events total. Before the fix, the agent emitted ranges with the same story-global intent, but the code interpreted them as per-chapter — so `"59-69"` matched zero events, tripped the equal-split fallback, and everything downstream became noise.

## 3. Session 1 entry point — the critical fix target

```json
"start_event_position": 1,
"start_event_id": 1,
"start_event_chapter_position": 1,
"start_event_local_position": 1
```

- `start_event_id = 1` — first event in the database.
- `start_event_chapter_position = 1` — Chapter 1.
- `start_event_local_position = 1` — first event in Chapter 1.

Both new traceability fields (`start_event_chapter_position`, `start_event_local_position`) added to `EntryPointDiagnosisJob` are being persisted as planned.

The generated cold open fully lands the emotional promise:

> "Heat shimmers off the river stones, and your stockings stick to the back of your knees as you lean over the grass, half-listening to your sister's page-turning.
>
> Then a White Rabbit flashes past so close you catch the clean, sharp scent of crushed clover—and it mutters, plainly, like a person: 'Oh dear! Oh dear! I shall be late!'"

Emotional promise: "pursuit. A user arrives feeling restless and wanting to chase the impossible before it vanishes." — exactly what Session 1 is supposed to sell.

## 4. All four sessions' entry points

| Session | `start_event_id` | `start_event_chapter_position` | `start_event_local_position` | Semantic check |
|:-:|:-:|:-:|:-:|:---|
| 1 | 1 | 1 | 1 | Rabbit-with-watch cold open, Chapter 1 first event. Correct. |
| 2 | 24 | 2 | 16 | Tear-pool → Rabbit returns panicking about the Duchess → drops gloves/fan. Ch.2 content. Correct. |
| 3 | 45 | 4 | 3 | Smoky kitchen with Duchess and cook. Ch.4 content. Correct. |
| 4 | 63 | 6 | 1 | Queen's procession / "Duchess links arms". Ch.6 first event. **This is now the legitimate Session 4 opening**, not a Session 1 bug symptom. |

`event_id = 63` has been reclassified from "the bug event that wrongly opened Session 1" to "the correct first event of Session 4." The fix didn't move the event — it moved which session claims it.

## 5. Non-fix observations (quality of the generated adaptation itself)

These are AI-output quality notes, separate from the structural fix. Tracking them here to inform future adaptation-layer work.

### 5.1 Editorial verification: Session 1 Q4 still fails (`REVISE`)

```json
"consequential_choices": {
    "verdict": "REVISE",
    "detail": "Branching choices are mis-numbered/misaligned across the session: Beat Map labels Choice 2 as the DRINK ME posture, but Session Choice Design labels S1_C2 as the Rabbit/authority moment; Beat Map labels Choice 3 as the authority moment, but Session Choice Design labels S1_C3 as the identity-test end choice."
}
```

This is the same Q4 class of failure noted in the original `adaptation-analysis.md`: the beat-map choices and the session-choice-design choices reference different moments with the same IDs. Phase 5 (ChoiceDesignJob) and Phase 4 (SessionArchitectureJob) disagree on what `S1_C2` means. `production_status: "AMBER"` with revision instructions. Not a blocker for game start, but worth a targeted fix.

### 5.2 Session close's `session_end_choice` may re-bank a third branching slot

Session 1's `session_close_design.session_end_choice` is the same three-option `rule_following_vs_rule_testing` prompt shown as `branching_choice_3`. The editorial verification already flags this — the three-branching-choice canonical set ambiguity is the specific thing Q4 is complaining about. See 5.1.

### 5.3 `branch_dimensions` pollution from Phase 5

The `story_wide.story_session_map.branch_dimensions` array has two kinds of entries mixed together:

1. The 5 clean canonical dimensions the `StorySessionMapAgent` produces (e.g. `impulse_vs_deliberation`, `rule_following_vs_rule_testing`).
2. Trailing entries with compound names like `"impulse_vs_deliberation(how_you_commit_to_the_chase_when_the_impossible_appears)."` carrying `"origin": "phase_5"` and `"choice_id": "S1_C1"`.

The canonical dimensions stay clean. The trailing entries are appended by `ChoiceDesignJob::enrichBranchDimensionRegistry` and represent a real bug in that job — full root cause analysis and proposed fix are in the sibling file `branch-dimensions-pollution-bug.md`.

---

## 6. What this tells us about the pipeline contract

The story-global ordinal is now the **shared vocabulary** between:

1. **`StorySessionMapAgent` prompt** — sees events as `Event N of M (Chapter X, local pos Y): ...` and is told all `event_range` values must use `N`.
2. **`StorySessionMapJob` filter** — projects `story_position` on `$allEvents`, filters `$ev->story_position >= $range[0] && <= $range[1]`.
3. **`EntryPointDiagnosisAgent` prompt** — sees the same global numbering per session, and is told `start_event_position` must use it.
4. **`EntryPointDiagnosisJob` resolver** — `firstWhere('story_position', $startPos)` instead of the old `firstWhere('position', ...)`.
5. **`events` table** — `position` stays per-chapter (unchanged), `session_number` is now correctly populated by story-global range filtering.
6. **Game runtime** (`CreateGameAction`, `GameController`) — still reads per-chapter `events.position` and `events.session_number`. The runtime didn't need to change because its contract with the DB was already correct; the adaptation layer was the one violating it.

Evidence this contract is now enforced end-to-end:

- 4/4 sessions resolved to non-empty event sets → no `RuntimeException` from the removed-fallback guard → pipeline status `completed`.
- `start_event_chapter_position` values (1, 2, 4, 6) progress monotonically with session number → chapter boundaries honored.
- Session 4's range `63-90` (28 events) and ch.6–ch.7 cover 28 events → counts match.

## 7. Remaining runtime verification

The data is clean. The last gate is confirming the game runtime picks up the same event:

```bash
php artisan game:simulate-start alices-adventures-in-wonderland
```

Expected from the patched simulator:

- `resolved start event: id=1, chapter_id=<ch1 id>, pos=1, ...`
- No `WARNING: resolved start event is in a DIFFERENT chapter` message.
- LLM response narrates a Chapter 1 Rabbit-with-watch scene matching the cold open.

Once that reports green, the fix is verified through to the player's first-screen experience.

---

## 8. Summary for the log

- **Pre-fix state:** Session 1 opened on event id=63 "Duchess links arms..." (Chapter 6). `events.session_number` was scrambled by an equal-split fallback firing off a filter-mismatch.
- **Post-fix state:** Session 1 opens on event id=1 (Chapter 1 first event). Every session's start event sits inside its declared chapter range. Event ranges tile 1..90 cleanly. No silent fallback behavior — failures would now throw and mark the adaptation `failed`.
- **Status:** Structural fix confirmed. Two quality issues remain (Q4 choice-ID alignment, branch_dimensions pollution) and are tracked separately.
