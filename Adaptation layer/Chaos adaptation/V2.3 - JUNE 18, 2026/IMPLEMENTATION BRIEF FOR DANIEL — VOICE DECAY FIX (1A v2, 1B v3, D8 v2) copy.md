# Implementation Brief — Voice Decay Fix

**To:** Daniel — Pipeline Engineering
**From:** Thomas Wittmer / Lorespinner Interactive
**Date:** June 18, 2026
**Re:** Three updated deliverables — 1A v2 (Novelist Voice Lock), 1B v3 (Screenwriter Voice Lock), D8 v2 (Runtime Narrator Template). What the problem was, how we tested it, the fix, and exactly how to integrate it.

---

## TL;DR

The runtime narrator's voice decays over a session — it starts sounding like the author and drifts toward generic AI prose. Root cause: the old design tried to hold the voice with **numeric targets that nothing in the runtime can enforce** (an LLM can't count its own output, and there's no linter at runtime), and it **stripped the actual voice examples** out of the runtime prompt to save tokens. The fix replaces numeric runtime enforcement with two things an LLM can actually do: **imitate locked example passages**, and **follow short binary rules it re-reads every turn**. All numeric auditing moves to a pre-launch QA step. No new runtime infrastructure and no linter are required.

Three files change. Routing and story structure do not.

---

## WHAT WE ENCOUNTERED

1. **Voice decay is real and compounding.** In testing, generated prose held the author's voice early and lost it across the session.
2. **The old enforcement couldn't run.** The prior prompts assumed the runtime could check fragment rates, punctuation densities, etc., and reject output that missed a target. An LLM cannot reliably count its own output; there is no runtime linter. So every numeric runtime check was effectively a no-op.
3. **The runtime never saw the voice.** The old D8 loaded the voice as abstract technique *descriptions* ("uses short declaratives") with the example quotes removed to save tokens — and, under token pressure, cut those examples first. The narrator was told about the voice but never shown it.
4. **Rules stated once aren't enforced.** Even a correct ban (e.g., "no em-dashes") was violated heavily, because it was stated once at the top of a 65k-character prompt and never re-checked.
5. **The narrator drifts toward its own prior output.** Across a session, the model's earlier (already-slightly-generic) responses sit in context and become the de-facto style reference. It imitates itself downhill.

---

## HOW WE TESTED IT

We audited five consecutive live outputs from the Anima Machina session against the source voice. Findings (full detail in **QA FINDING — VOICE DECAY IN LIVE ANIMA MACHINA OUTPUT**):

- **Em-dashes at ~40× the source rate** (~10 in ~1,700 words) despite an explicit ban already in the prompt.
- **Fragment compression eroding** — a punchy opening smoothing into long compound sentences by output 5.
- **Smooth "rule-of-three" triads proliferating** in later outputs (an AI cadence tell).
- **Essay-line / stakes-summary endings** explaining the dilemma rather than showing it.
- **Banned "the kind of" constructions** leaking in.
- Note: response length was already ~300–380 words (the tested voice-preserving range), so **length is not the problem** — structural smoothing is.

The output was good enough not to read as obvious slop, which is exactly why the decay was slipping through unmeasured.

---

## THE SOLUTION (one principle, three documents)

**Principle:** a voice is held at runtime by **imitation** (example passages) and by **binary, local rules** (search-and-fix a token; spot a pattern within a few sentences) — never by counting. Anything numeric is diagnostic and runs at build time only.

Each Voice Lock prompt now produces three new runtime-critical outputs, in addition to the existing DNA profile and ban list:

- **Voice Anchor** — 6–8 short locked prose passages in second-person present tense, in the author's voice, that the runtime imitates. (This is the centerpiece.)
- **Anchor Card** — 8–12 binary/local rules the narrator re-reads every turn (e.g., "delete every em-dash"; "no character speaks more than N words"; "use 'steps forward,' never 'moves forward'").
- **Runtime Self-Check** — a short search-and-fix checklist the narrator runs on each passage before delivering it.

The numeric audits (the old 14/18-point protocols) are preserved but **repositioned as a Build-Time QA step** run on sample outputs before an IP ships — never in the live narrator.

**Per format:**
- **1B v3 (Screenwriter)** merges and replaces 1B FINAL + 1B v2. It builds the Voice Anchor by translating real script moments into prose (closing the screenplay-to-prose gap once, offline). Includes a calibration so compressed fragment-punch triads ("Suit. Skin. Geometry.") are kept while smooth filler triads are cut.
- **1A v2 (Novelist)** keeps the existing, working extraction intact and adds the same three pieces. For novels the only conversion is POV + tense (the prose is already prose), so exemplars stay near-verbatim. It honors documented author exceptions (some authors genuinely use cognitive verbs, prolepsis, or commentary — those are preserved, not stripped).
- **D8 v2 (Runtime Template)** loads the Voice Anchor verbatim, re-asserts the Anchor Card and Self-Check at the very end of the prompt (for recency), reverses the token-budget cut order so the voice is protected, and is format-agnostic (serves both 1A v2 and 1B v3 from one template).

---

## FILE MAP — WHAT REPLACES WHAT

| New file | Replaces | Pipeline slot |
|---|---|---|
| DELIVERABLE 1B v3 — Voice Lock (Screenwriter) | 1B FINAL **and** 1B v2 | VoiceLockChapterJob, screenplay branch |
| DELIVERABLE 1A v2 — Voice Lock (Novelist) | 1A FINAL | VoiceLockChapterJob, prose branch |
| DELIVERABLE 8 v2 — Runtime Narrator Template | D8 (v1) | Runtime assembly job |

Routing is unchanged: FormatDetectionJob still selects 1A (prose) vs 1B (screenplay). Both Voice Locks now emit the same runtime-critical sections in the same shape, so the assembly job treats them identically.

Each Voice Profile now contains, in this shape:
- Section 1: Voice DNA · Section 2: Hard Ban List · **Section 3: Voice Anchor · Section 4: Anchor Card · Section 5: Runtime Self-Check** · Section 6: Build-Time QA (1B v3 also has Section 7: Screenplay-to-Prose table).

---

## HOW TO INTEGRATE — ASSEMBLY JOB CHANGES

1. **Map the three new sections into D8 v2:**
   - Voice Profile **Section 3 (Voice Anchor)** → D8 **Section 4A**, loaded **verbatim** (do not compress to technique names).
   - Voice Profile **Section 4 (Anchor Card)** → D8 **Section 18**.
   - Voice Profile **Section 5 (Self-Check)** → D8 **Section 18**.
   - (Per-character speech ceiling → D8 Section 3 "Speech ceiling" line.)
2. **Place Section 18 last** in the assembled prompt. It is re-read immediately before each generation — recency is the point.
3. **Reverse the token-budget cut order** (D8 v2 "Character Count Validation"): if over 65k, trim in this order — Tier 3 state → compress Section 12 → trim 4B technique descriptions → reduce Voice Anchor toward a **floor of 5 exemplars**. **Never** cut the Anchor Card, the Self-Check, or below 5 exemplars. If it still won't fit, split the episode.
4. **Runtime behavior:** the narrator runs the Self-Check (Section 18) on each draft before delivering. No new service required.
5. **No linter anywhere in the runtime path.** All runtime enforcement is binary/local, performed by the narrator.

---

## WHAT DID **NOT** CHANGE (so you can scope the work)

- The voice **extraction logic** (DNA capture, ban generation) in both prompts — preserved.
- **Format routing** (1A vs 1B) — unchanged.
- D8's **story machinery** — StoryGuard layers, world reactivity, freedom contract, choice presentation, tiered state, session-complete signal — unchanged.
- D8 **word-count blocks** (115–125 word outcomes, etc.) — unchanged (see open item below).
- The **novelist extraction** in 1A — preserved intact; v2 only adds the anchor layer.

---

## VALIDATION BEFORE WIDE ROLLOUT

Run the Build-Time QA step on a fresh build: generate sample outputs from an assembled D8 v2 prompt and re-audit against the QA Finding checklist. Pass criteria:
1. Em-dash count at or near the source rate (~0 for Anima Machina).
2. "the kind of," smooth filler triads, and essay-line endings absent or sharply reduced.
3. Tail compression (output 5) resembles the opening, not smoothed out.
4. Fragment-punch triads ("Suit. Skin. Geometry.") still present — confirm the calibration didn't over-correct.

If decay still appears in the tail, the next step is the optional **second-pass "voice editor"** — a cheap, separate model call that reuses the same Self-Check checklist on the finished passage. No rework needed; the checklist is identical.

---

## OPEN ITEMS (not blocking, flagged for the record)

- **Response cadence:** the brief's tested sweet spot is 300–350 words per response; D8's per-block counts (115–125 outcomes) should be confirmed to sum to that range, or reconciled with the Paul-review cadence additions. The live outputs already land ~300–380, so this appears fine but is unverified in the prompt text.
- **Forced stakes-summary endings:** the "forward-pull / agency-handoff" rule is manufacturing essay-lines at every turn. This is structural, not voice, and is being addressed in the next workstream (Story Opening / choice architecture).

---

## END OF BRIEF
