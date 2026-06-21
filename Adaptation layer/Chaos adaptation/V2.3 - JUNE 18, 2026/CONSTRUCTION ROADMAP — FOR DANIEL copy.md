# Construction Roadmap — For Daniel

**To:** Daniel — Pipeline Engineering
**From:** Thomas Wittmer / Lorespinner Interactive
**Date:** June 18, 2026
**Re:** The whole change-set in build order — what to read, in what order, and what to install, in what order. Pair this with the Integration Safety & Migration Plan for the detail.

---

## WHAT THIS CHANGE-SET DOES (one line)

Stops the runtime narrator's voice from decaying, gives the opening a real cold-open phase, and ties the first choice to the protagonist's stakes — all as prompt swaps into existing jobs, all reversible per IP.

---

## PART 1 — READ IN THIS ORDER

Read these four first, top to bottom. They take ~20 minutes total and give you the why, the proof, the safety model, and the plan before you touch a job.

1. **IMPLEMENTATION BRIEF — VOICE DECAY FIX.** The overview: the problem, the fix, and what changed across 1A v2 / 1B v3 / D8 v2. Start here.
2. **QA FINDING — VOICE DECAY IN LIVE ANIMA MACHINA OUTPUT.** The evidence the fix is built on (em-dashes at 40× source, fragment decay, etc.). Read for context; it's the "why this matters."
3. **INTEGRATION SAFETY & MIGRATION PLAN.** How this rolls out without risk — the fail-safe behavior, the one new wiring step, the canary rollout, the pre-flight checklist, rollback. **Read this before installing anything.**
4. **CONSTRUCTION ROADMAP** (this document). The build sequence.

Then read each prompt as you install it (Part 2): **1B v3 / 1A v2 → Deliverable 10 → Deliverable 4 Patch → D8 v2.**

---

## PART 2 — INSTALL THE PROMPTS IN THIS ORDER

Install producer-before-consumer, so each prompt's downstream consumers exist by the time they're needed. Each step is a prompt paste into an existing job (except Deliverable 10, which is one new job). D8 v2 is fail-safe, so order isn't strictly required — but this sequence keeps every slot filled cleanly.

**Step 1 — Voice Lock prompts (the foundation).**
- Install **DELIVERABLE 1B v3** into VoiceLockChapterJob (screenplay branch). Replaces 1B FINAL + 1B v2.
- Install **DELIVERABLE 1A v2** into VoiceLockChapterJob (prose branch). Replaces 1A FINAL.
- These now output three new sections — Voice Anchor, Anchor Card, Self-Check — that everything downstream uses. Sections 1–2 are unchanged in name/position.

**Step 2 — Cold Open phase (new job).**
- Install **DELIVERABLE 10 — Phase 3 Cold Open & First Agency** as a new build-time job placed **between Phase 2 (Session Map) and Phase 4 (Beat Architecture).**
- It consumes the Voice Lock output and produces the cold-open prose, a first-choice spec, a cut point, and the emotional promise — feeding the existing `{{PHASE_3_COLD_OPEN_PROSE}}` slot that D3/D4/D8 already reference. No new runtime plumbing.

**Step 3 — Choice Design patch.**
- Apply **DELIVERABLE 4 PATCH** to the Phase 5 Choice Design prompt: replace Task 1 only, and add the First-Choice Spec input line. Everything else in Phase 5 is unchanged.

**Step 4 — Runtime template (last, because it consumes everything).**
- Install **DELIVERABLE 8 v2** into the runtime assembly job. Replaces D8 v1.
- **The one real engineering step:** map four new slots — `{{VOICE_ANCHOR}}` (Section 4A), `{{ANCHOR_CARD}}` and `{{RUNTIME_SELF_CHECK}}` (Section 18), `{{SPEECH_CEILING}}` (Section 3) — from the Voice Lock output, **by header name, not position.** Confirm name-based parsing first (top item on the pre-flight checklist).
- D8 v2 is fail-safe: if a slot is empty, omit the sub-block and it runs as v1. So it's safe to install at any point.

---

## PART 3 — BRING ONE IP LIVE (canary), THEN EXPAND

Do this on one low-stakes IP first. Never all at once.

1. Re-run Voice Lock (1A v2 / 1B v3) for the canary IP → new-shape Voice Profile.
2. Run Phase 3 (Deliverable 10) → cold open + first-choice spec.
3. Run Phase 5 with the patched Task 1 → expanded first choice.
4. Assemble the runtime prompt with D8 v2. Run the **pre-flight checklist** (Migration Plan): no literal `{{}}` tokens, char count < 65k, cold open present, anchor present or cleanly omitted.
5. Run **Build-Time QA** on 8–12 sample outputs (QA Finding checklist): em-dashes ~0, no smooth-triad/"the kind of"/essay-line drift, tail compression matches the head, first choice is stakes-tied (not a tutorial), openings end on the live moment.
6. Smoke-test a full live session.
7. Promote, then expand IP by IP. Keep the old prompts as fallback throughout. Roll back any IP in one step by repointing to the prior prompt.

---

## QUICK REFERENCE — WHAT EACH NEW/CHANGED PROMPT REPLACES

| Install | Into job | Replaces / action |
|---|---|---|
| DELIVERABLE 1B v3 | VoiceLock (screenplay) | Replaces 1B FINAL + 1B v2 |
| DELIVERABLE 1A v2 | VoiceLock (prose) | Replaces 1A FINAL |
| DELIVERABLE 10 (Phase 3 Cold Open) | NEW job, before Phase 4 | Fills the undefined Phase 3 slot |
| DELIVERABLE 4 PATCH | Phase 5 Choice Design | Replaces Task 1 only |
| DELIVERABLE 8 v2 | Runtime assembly | Replaces D8 v1; map 4 new slots by name |

Unchanged and untouched: FormatDetection routing, Phase 2 Session Map, Phase 6 Consequence, Phase 8 Editorial, Trimming, Social Echo, StoryGuard, state schema.

---

## END OF ROADMAP
