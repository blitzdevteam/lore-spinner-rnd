# QA FINDING — Voice Decay in Live Anima Machina Output

**To:** Daniel — Pipeline Engineering
**From:** Thomas Wittmer / Lorespinner Interactive
**Date:** June 18, 2026
**Re:** Audit of 5 sequential runtime outputs from the Anima Machina interactive test, and how Deliverable 1B v3 + Deliverable 8 v2 address what was found.

---

## SUMMARY

Five consecutive runtime outputs from the live Anima Machina session were audited against the author's source voice. The headline: **the output is good, not slop — but it is decaying, and every decay marker is a binary or local pattern that the current runtime fails to catch.**

The opening is tight and authentically compressed. Across the next four outputs the prose smooths out, lengthens, and drifts toward competent-but-generic AI cadence. This is the same decay curve measured in the earlier single-screenplay audit (fragment fidelity falling from the author's ~76% baseline toward the model's default), now visible turn by turn in real player-facing text.

Critically, the most prevalent tell — em-dashes — appears at roughly **40× the source rate despite an explicit em-dash ban already being in the runtime prompt.** This is the proof that stating a rule once, at the top of a 65k-character prompt, does not enforce it. Enforcement has to be a re-asserted, search-and-fix action. That is exactly what D1B v3 (Anchor Card + Self-Check) and D8 v2 (Section 18 Voice Re-Anchor) add.

This document lists the specific catches so the fix can be validated against the same sample after it ships.

---

## THE SAMPLE

Five outputs, in order:

1. **Opening** (cold open, before first choice)
2. **"Push through the door and face what's inside"**
3. **"Press the lens against the nearest reflective surface"**
4. **"Check Eli's body for anything the Mirror might have left behind"**
5. **"Pocket the lens and leave before AMORA arrives"**

Each delivered response runs ~290–400 words. (Note: this confirms the runtime is already producing ~300–350-word responses, the tested voice-preserving length. Length is not the problem. Structural smoothing is.)

---

## FINDINGS

### 1. Em-dash contamination — HARD FAIL, ~40× source rate

The Anima Machina script uses double-hyphens (screenplay convention) and effectively no em-dashes (~0.14 per 1,000 words). The outputs are saturated with them:

- Opening: "the glass—mercury pooling"
- Push door: "your port—not the tether error"; "still his—no chrome, no simplification—but the stillness"
- Press lens: "the glass—your reflection"; "goes dark—not black, but absent"; "the first time—something etched"
- Check body: "still Eli—the small scar above his left eyebrow"
- Pocket lens: "your sternum—warm, real"; "around you—citizens in their overlays"

~10 em-dashes in ~1,700 words ≈ 5.9/1k ≈ **40× the source.** This violates both the Universal em-dash ban and the author's documented habit.

**Caught by:** Anchor Card rule 1 + Self-Check step 1 ("search the draft for — and --, delete every one"). This single binary pass removes the most common tell in the system.

### 2. Fragment-rate decay — punchy head, smooth tail

The opening is authentically compressed: "Not him. Wrong." / "Catalog. Window. Bench." / "Suit. Skin. Geometry." By output 5 the prose has relaxed into long connective sentences:

> "Eight months of the Mirror pressing against your port, offering restoration, offering mercy, offering the lie that Eli could come back if you just let the system do what it was built to do."

That is a single ~30-word flowing compound sentence — the model settling toward its own default rhythm. The compression that defines the voice erodes across the session.

**Caught by:** the Voice Anchor (the narrator imitates locked compressed exemplars instead of its own drifting prior output) + Self-Check steps 5 and 7 (flag runaway sentences; compare texture against the nearest exemplar).

### 3. Triadic cadence proliferating — the AI music creeping in

Smooth rule-of-three constructions multiply in the later outputs:

- "offering restoration, offering mercy, offering the lie"
- "the way objects are, the way the cold coffee is, the way the morning light is"
- "every backup, every trace, every version of Eli"
- "what can be salvaged, what can be restored, what can be made useful again"

The author caps triads hard; they are an AI rhythm tell.

**Important calibration (now built into D1B v3 / D8 v2):** this targets only the *smooth connective* triad. The compressed *fragment-punch* triad — "Suit. Skin. Geometry." — is on-voice and must be preserved. The test is smoothness, not the count of three. Because the distinction is a judgment call, it is enforced as model/build-time review, not a runtime auto-fail, to avoid flattening the real voice.

### 4. Essay-lines / thematic hand-holding — and a structural cause

Most outputs end by explaining the stakes rather than showing them:

> "Eli's extended arm waits for you to decide whether the dead stay dead or whether the system's mercy is worth the cost of living inside a lie."

Also: "the kind that does not breathe back" and "the kind of space where the city's infrastructure shows its bones" (the banned "the kind of," twice).

Part of this is voice decay and is caught by the explanatory-commentary ban + Self-Check. **But part of it is structural, not voice:** the forced *forward-pull / agency-handoff* ending appears to require a stakes summary every single turn, which manufactures essay-lines on demand. The narrator is being told to over-explain the dilemma before each choice. No voice fix fully resolves this while the structure mandates a thematic recap every turn.

**Action:** flag the forward-pull ending rules (D8 narration rules + the Paul-cadence additions) for review alongside the upcoming opening/choice-architecture work. This is a design issue separate from the voice profile.

---

## WHAT THIS VALIDATES

- The decay is real, measurable, and made of exactly the binary/local markers the fix targets.
- The current runtime fails even when the correct ban is present (em-dashes), because the ban is stated once and never re-asserted or acted on. D8 v2's Section 18 (re-asserted Anchor Card + Self-Check, placed last in the prompt for maximum recency) is the direct remedy.
- The voice fix and a structural fix are distinct problems. The forced-stakes-summary ending is structural and belongs to the opening/choice assignment, not the voice profile.

---

## RECOMMENDED VALIDATION AFTER THE FIX SHIPS

Re-run the same five choice points through the D1B v3 + D8 v2 build and re-audit against this list:

1. Em-dash count must be 0 (or at the source's ~0.14/1k).
2. "the kind of," "offering X/Y/Z" smooth triads, and essay-line endings should be absent or sharply reduced.
3. Fragment compression in output 5 should resemble output 1, not smooth out.
4. Fragment-punch triads ("Suit. Skin. Geometry.") should still be present — confirm the calibration did not over-correct.

If decay still appears in the tail, escalate to the optional second-pass "voice editor" call (it reuses the same Self-Check checklist, no rework).

---

## END OF QA FINDING
