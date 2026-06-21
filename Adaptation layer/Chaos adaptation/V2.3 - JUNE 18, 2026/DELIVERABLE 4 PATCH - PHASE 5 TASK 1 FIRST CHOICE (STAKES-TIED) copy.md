# DELIVERABLE 4 PATCH — Phase 5 Choice Design, Task 1 (Stakes-Tied First Choice)

**Lorespinner Pipeline Upgrade — June 2026**
**Type:** Minimal patch to an existing prompt (additive, reversible).
**Applies to:** DELIVERABLE 4 — UPDATED PHASE 5 CHOICE DESIGN.
**What it changes:** Replaces **TASK 1** only. Everything else in the Phase 5 prompt is unchanged.
**Why:** Phase 3 (Cold Open & First Agency, Deliverable 10) now produces a **First-Choice Spec** designed to be powerful and tied to the protagonist's core stakes. Phase 5 Task 1 must *consume* that spec and expand it — and must refuse the soft-tutorial failure mode. The original Task 1 only called the first choice "identity-establishing," with no mechanism to prevent a low-stakes warm-up.
**Rollback:** restore the original Task 1 text. No other change.

---

## INTEGRATION

In your Phase 5 Choice Design prompt, locate `TASK 1 — BRANCHING CHOICE #1 (SETUP BEAT …)` and replace that entire task block with the block below. Add one line to the prompt's input header so Task 1 receives the Phase 3 spec:

```
FIRST-CHOICE SPEC (from Phase 3 / Deliverable 10): [PASTE the First-Choice Spec output]
```

If Phase 3 has not been run for this IP (fallback mode), Task 1 operates as before but must still apply the stakes-tied / no-tutorial gate at the bottom of this block.

---

## REPLACEMENT TEXT — TASK 1

```
TASK 1 — BRANCHING CHOICE #1 (SETUP BEAT — the first agency moment)

This is the player's first real decision. It sets the register they carry through the session and tells them what kind of person they are choosing to be. It is NOT a tutorial.

INPUT: The Phase 3 First-Choice Spec (entry point, the threshold/stake it turns on, the question, and three option directions with alignment and tracked dimension). Your job is to EXPAND that spec into full outcomes in the author's voice — not to redesign it. Preserve the spec's threshold, stakes-tie, and the unexpected third option.

HARD REQUIREMENTS (gate — verify before writing outcomes):
- TIED TO CORE STAKES: the choice engages the protagonist's central want or threat established in the cold open — not a side encounter, not a passerby, not a moral exercise on a stranger.
- NO SOFT TUTORIAL: if the choice is a low-stakes warm-up (help/ignore a random NPC, a tap-to-continue, a no-cost decision), it FAILS. Return to Phase 3 and raise the stakes or move the entry point. Do not ship a tutorial as Choice #1.
- REAL FORK, NO CORRECT ANSWER: three options, each a legitimate human value; at least one is the unexpected third path from the spec.
- ARRIVES WITHIN ~300 WORDS of the cold open's first word.

Source moment: [from the Phase 3 spec / beat map]
What this choice tracks: [branch dimension from Phase 2]
Alignment order for this choice: [randomized]

NARRATIVE SETUP (2-3 sentences of second-person prose in the author's voice — use the Phase 3 cold-open setup verbatim or lightly finished):
[the passage immediately before the question — ends on the live moment, NOT a stakes summary]

CHOICE QUESTION: [in second person, from the spec]

  A. [OPTION — one sentence, from the spec]
     Alignment: [internal only]
     Outcome (115-125 words): [full outcome in the author's voice; ends on a live image/action, never an essay-line stakes recap]
  B. [OPTION — one sentence]
     Alignment: [internal only]
     Outcome (115-125 words): [text]
  C. [OPTION — the unexpected third path]
     Alignment: [internal only]
     Outcome (115-125 words): [text]

PERSISTENT STATE CHANGES: [per option — inventory, NPC dispositions, environmental flags, emotional ledger, alignment shift — same format as before]
WORLD NOTICED SIGNAL: [per option — in-world, in the author's voice, non-gamey]
STORYGUARD MANIFEST: [canon boundaries, character truth, scene integrity, fold-back path, freeform alignment mapping — same format as before]

FIRST-CHOICE GATE CONFIRMATION (all must be YES):
- Tied to the protagonist's core stakes (not a side encounter): [YES/NO]
- Not a soft tutorial / no-cost warm-up: [YES/NO]
- No correct answer; three genuine values: [YES/NO]
- Includes the unexpected third option: [YES/NO]
- Arrives within ~300 words: [YES/NO]
- Each outcome ends on a live moment, not a stakes summary: [YES/NO]
If any answer is NO, revise — or return to Phase 3.
```

---

## END OF DELIVERABLE 4 PATCH
