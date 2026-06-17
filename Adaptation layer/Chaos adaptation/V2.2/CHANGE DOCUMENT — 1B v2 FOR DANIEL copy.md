# What Changed in 1B v2 and Why

**For:** Daniel (Pipeline Engineering)
**From:** Thomas Wittmer / Lorespinner Interactive
**Date:** June 16, 2026
**Re:** Deliverable 1B v2 — Voice Lock (Screenwriter) Strengthened

---

## One-Sentence Summary

The original 1B prompt produced descriptive voice profiles ("this writer uses fragments"). The v2 produces enforceable voice specifications ("fragment rate: target 28-36%, floor 20%, ceiling 45%, confidence HIGH"). Same questions, enforceable answers.

---

## The Problem We Found

We ran a real screenplay through the 1B pipeline and audited four runtime outputs against the source writer's actual measurable voice patterns. The outputs started recognizable and decayed measurably — by Output 10, the prose retained roughly 25% of the writer's voice characteristics. The rest was competent but generic AI prose.

Root cause: the extraction produced prose descriptions of the writer's voice, and the runtime treated those descriptions as soft suggestions. Suggestions decay over extended generation. Specifications do not.

---

## What Changed (7 Additions)

Nothing was removed or rewritten. Every word of the original 1B FINAL is preserved. Seven new sections were added. Here is exactly what and where.

### Addition 1: Section M — Numerical Enforcement Layer

**Where:** New Section M in Task 1, after Section L (Comparative Exclusion)

**What it does:** Requires the extraction to convert every measurable metric into a specification with four components: TARGET (aim here), FLOOR (never below), CEILING (never above), CONFIDENCE (how much to trust it). Covers punctuation density, sentence length distribution, fragment rate, opener distribution, dialogue ceilings per character, and word length.

**Why:** The original prompt asked for "average words per action line" as an observation. The runtime read the observation and wrote whatever it wanted. Now the extraction produces "avg words: target 8-12, ceiling 20, confidence HIGH (1700+ data points)" — the runtime can check every sentence against this.

### Addition 2: Section N — Rhythm Transition Architecture

**Where:** New Section N in Task 1, after Section M

**What it does:** Requires the extraction to build a state-transition matrix showing what sentence length follows each sentence length. After a 3-word punch line, what comes next? After a 15-word line, what comes next? Captures the writer's rhythm MOVEMENT, not just their average.

**Why:** The original prompt captured rhythm as a static description. But rhythm is dynamic — a writer who punches then breathes has a fundamentally different cadence from one who stacks fragments. Without this, the runtime either stacks fragments (staccato noise) or avoids them (drift).

### Addition 3: Section O — Beat Architecture Protocol

**Where:** New Section O in Task 1, after Section N

**What it does:** Requires extraction of ultra-short (1-2 word) standalone lines as a distinct voice component — their frequency, vocabulary, placement, and function.

**Why:** Many screenwriters use standalone lines like "Silence." or "Hold." as structural rhythm markers. These are not descriptions — they are beats. The original prompt counted them as short sentences. The v2 treats them as a separate voice component with extractable patterns.

### Addition 4: Section P — Scene Transition Compression Protocol

**Where:** New Section P in Task 1, after Section O

**What it does:** Requires analysis of the last 1-3 action lines before each scene change. Captures how the writer ends scenes — the compression pattern at boundaries.

**Why:** Scene endings reveal a writer's instinct for closure. If the writer consistently closes on a 3-word image, the runtime must not close on a 25-word reflective sentence.

### Addition 5: Single-Source Confidence Framework

**Where:** Inserted at the TOP of Task 1, before the section listings

**What it does:** Tags every extracted constraint with a confidence level based on sample size:

- ABSOLUTE: Zero occurrences across the full source. Hard ban.
- HIGH: 100+ instances. Statistically robust. Hard enforcement, narrow tolerance.
- MEDIUM: 20-99 instances. Moderate enforcement, wider tolerance.
- LOW: <20 instances. Guidance only.

**Why:** When working from one screenplay, some metrics have 4000+ data points (periods) and others have 6 (ellipses). Both need different enforcement strength. Without confidence tags, the runtime treats everything the same — either too strict on weak data or too loose on strong data.

**Critical insight for the pipeline:** Zero-occurrence data is the strongest weapon in single-source extraction. When a writer produces 17,000 words without a single semicolon, that zero is ABSOLUTE confidence. The framework ensures these zeros are enforced as hard bans, not merely noted.

### Addition 6: Quantitative Translation Mappings

**Where:** New subsection INSIDE the existing Screenplay-to-Prose Translation Protocol, after the 7-row table

**What it does:** Requires the extraction to produce specific numerical mappings that translate screenplay metrics to prose metrics with tolerance bands. Example: screenplay fragment rate 32% → prose target 25-35%, drift ceiling (floor) 20%.

**Why:** Screenplays and prose are different formats. A 76% fragment rate in action lines would be unreadable as continuous narrative. But without quantitative translation, the runtime guesses where the tolerance should be — and always guesses too loose. The 1A novelist prompt does not need this because novels are already prose. This is the key structural difference between 1A and 1B.

### Addition 7: Section 3B — Voice Decay Prevention Protocol

**Where:** New Section 3B between the 14-Point Audit Protocol (Task 3) and the Screenplay-to-Prose Translation Protocol

**What it does:** Three components:

1. **Re-anchoring trigger** — every 300-400 words of generated prose, the runtime re-injects the core enforcement constraints into its active context.
2. **Passage-level enforcement check** — before delivering any passage to the player, verify hard constraints (punctuation bans, dialogue ceilings, pronoun clustering, fragment floor). If ANY hard constraint is violated, REJECT and REGENERATE from scratch.
3. **Drift detection** — track key metrics across consecutive passages. If fragment rate, period density, or comma density trends consistently away from target over 3+ passages, flag for re-anchoring.

**Why:** We measured voice decay across four consecutive outputs: fragment rate dropped from 61% to 27%. This is not a model failure — it is a physics of autoregressive generation. Each token shifts the model slightly toward its default distribution. Over 500+ words, the shifts compound. Re-anchoring counteracts this structurally.

---

## What Changed in the Verification Gate

Three new checks added (questions 4-6):

4. Does the Numerical Enforcement Layer contain hard constraints for period density, comma density, and at least 3 ABSOLUTE-confidence bans?
5. Does the Rhythm Transition Architecture include a complete 4x4 transition matrix?
6. Does the Voice Decay Prevention Protocol specify a re-anchoring word-count trigger?

---

## What Changed in the Final Output Structure

```
SECTION 1: VOICE DNA PROFILE
  [Sections A through P — was A through L]

SECTION 2: MASTER RULE 1 — HARD BAN LIST
  [Unchanged]

SECTION 3: 14-POINT AUDIT PROTOCOL
  [Unchanged]

SECTION 3B: VOICE DECAY PREVENTION PROTOCOL   ← NEW
  [Re-anchoring, passage-level checks, drift detection]

SECTION 4: SCREENPLAY-TO-PROSE TRANSLATION PROTOCOL
  [Original table + NEW Quantitative Translation Mappings]
```

---

## What Did NOT Change

- All 12 original Voice DNA sections (A-L) are untouched
- All universal bans are untouched
- All 7 structural AI tells (June 2026) are untouched
- All IP-specific ban generation instructions are untouched
- All 14 audit points are untouched
- The Screenplay-to-Prose Translation Protocol table is untouched
- The format gate, implementation notes, and copy-paste structure are untouched

---

## Pipeline Implementation Notes

- The v2 prompt is a drop-in replacement for the 1B FINAL. Same copy-paste structure. Same job slot. Same conditional (1B for screenplays, 1A for novels).
- The Voice Profile output is larger (Sections M-P + 3B + Translation Mappings add approximately 30-40% more output). Verify token budget accommodates this.
- The Voice Decay Prevention Protocol (3B) requires the runtime to re-inject constraints every 300-400 words. This means the runtime narrator's system prompt must include the Numerical Enforcement Layer and the re-anchoring trigger. Confirm the runtime template loads Section 3B alongside the existing voice data.
- The passage-level enforcement check in 3B is deterministic (string matching, counting). It can be implemented as a post-generation filter without modifying the generation model.

---

## Why Screenwriter Extraction Is Harder Than Novelist Extraction

The 1A prompt (novelist) works better because novels are already prose. The extraction captures prose patterns and the runtime reproduces prose patterns. No format translation needed.

Screenplays are compressed, visual, present-tense, externalized, and format-constrained. The runtime must generate continuous prose narration from a source that was never designed to be read as continuous prose. The format translation gap between "screenplay action line" and "prose narrative sentence" is where the AI's default voice leaks in.

The v2 additions — specifically the Quantitative Translation Mappings (Addition 6) and the Confidence Framework (Addition 5) — exist to close this gap. They make the format translation measurable and enforceable instead of qualitative and hopeful.
