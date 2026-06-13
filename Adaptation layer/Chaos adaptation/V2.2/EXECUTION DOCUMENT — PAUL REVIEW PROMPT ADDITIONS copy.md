# EXECUTION DOCUMENT — PAUL REVIEW PROMPT ADDITIONS

**Version:** 1.0  
**Date:** June 9, 2026  
**For:** Thomas Wittmer / Daniel (Pipeline Engineering)  
**Companion File:** PLUG-IN PROMPTS — PAUL REVIEW ADDITIONS (CORRECTED).md

---

## WHAT THIS IS

Four copy-paste prompt additions that address the core issues in Paul's 26-page LoreSpinner assessment. No new pipeline phases. No new jobs. No rearchitecting. Each block appends to an existing deliverable prompt.

---

## BUG REPORT — WHAT WAS WRONG WITH THE ORIGINAL DRAFT

The original "Pipeline Additions from Paul Review" document had **4 bugs** that would have caused problems if deployed as-is.

### BUG 1: Word Count Was Catastrophically Wrong

**Original:** 100–130 words per response. Never exceed 150.

**Problem:** Paul's #1 compliment was "it feels authored, not AI-generated." At 100–130 words, you destroy the authorial voice. Thomas's own testing with the Sherlock IP proved this — a 200-word version scored 6.5–8/10 while a 300-word version scored 8.3–8.6/10. The 100–130 target would have been worse than the lowest-scoring version tested.

**Fix:** Changed to 300–350 words (soft ceiling 350, hard ceiling 400, climax exception 500). This is directly from Thomas's sweet spot analysis.

### BUG 2: Custom Input Rule Was in the Wrong Deliverable

**Original:** The Custom Input Rule was placed in Addition 3, targeting Deliverable 4 (Choice Design — a pipeline prompt).

**Problem:** Custom input happens LIVE during gameplay. The pipeline designs scripted choices at build time — it has no concept of what a player will type in real time. Putting a custom input handler in a pipeline prompt is a pipeline/runtime blur. The pipeline can't execute custom input handling because the pipeline doesn't run during gameplay.

**Fix:** Moved the Custom Input Protocol to Deliverable 8 (Runtime Narrator Template) where it belongs. The runtime narrator is the LLM that receives custom input and must respond to it.

### BUG 3: Consequence Latency Conflict

**Original:** Addition 1 said "within 2 responses." Addition 6 said "maximum latency: 3 responses." Two different numbers for the same constraint.

**Fix:** Reconciled to: Target = 2 responses (runtime enforcement). Hard maximum = 3 responses (pipeline design constraint). Both numbers now appear in their correct context — the pipeline designs consequences to surface within 2, with a hard max of 3. The runtime enforces the 2-response target.

### BUG 4: Two Separate Deliverable 8 Blocks Created Integration Risk

**Original:** Additions 1 and 4 were separate blocks both targeting Deliverable 8. Daniel would have needed to paste two separate blocks into the same prompt, risking one getting missed or creating ordering conflicts.

**Fix:** Consolidated into one self-contained block (Prompt Addition 1 of 4) with 7 numbered rules. Daniel pastes once.

---

## WHAT EACH PROMPT ADDITION DOES

### ADDITION 1 → Deliverable 8 (Runtime Narrator Template)

**7 rules in one block. This is where most of Paul's feedback lands.**

| Rule | What It Does | Paul Issue It Fixes |
|------|-------------|-------------------|
| Response Length | Caps responses at 300–350 words with a 400 hard ceiling | "Responses are too long" — Paul's #1 issue |
| Forward Pull Endings | Forces every response to end on momentum, not atmosphere | "Too many responses end on description" |
| Beat Response Structure | Makes every response a 4-part beat (setup → reaction → change → pull) | "Treat every response as a scene beat, not prose continuation" |
| No Dead-End Responses | Prevents responses that leave the player in the same dramatic position | "No dead-end responses" — Paul's CYOA principle |
| Consequence Visibility | Surfaces player impact within 2 responses | "Make consequences more visible" — Paul's #3 priority |
| Description Economy | Kills repeated scene-setting and descriptive overhead | "Reduce repeated scene-setting" |
| Custom Input Protocol | Absorb → Reinterpret → Respond in character → Redirect | "Custom input = story energy, not interruption" |

**Why this makes the experience stronger:** This single block addresses Paul's entire cadence complaint. The current rhythm is: choice → wait → large text block → choice → wait → large text block. After this addition, the rhythm becomes: choice → short active beat → clear consequence → next decision. The player feels the story is moving, responsive, and personal.

---

### ADDITION 2 → Deliverable 3 (Phase 4 — Beat Architecture)

**2 rule sets in one block.**

| Rule Set | What It Does | Paul Issue It Fixes |
|----------|-------------|-------------------|
| Beat Ending Rules | Forces every designed beat to end on momentum (question, discovery, complication, decision, escalation, or character shift) — bans atmosphere, summary, and continuation endings | "End more responses with forward pull" |
| First-3-Minutes Rule | Requires the opening sequence to prove participation within 3 minutes — first choice within 90 seconds, first consequence within 120 seconds | "Tighten the first few minutes" — Paul's #5 priority |

**Why this makes the experience stronger:** This fixes the problem at design time, not just runtime. If the beat architecture is designed with dead-end endings, even a perfect runtime narrator can't save it. By banning atmosphere endings and mandating fast openings in the beat map itself, every IP ships with structurally stronger pacing baked in.

---

### ADDITION 3 → Deliverable 4 (Phase 5 — Choice Design)

**1 rule set.**

| Rule Set | What It Does | Paul Issue It Fixes |
|----------|-------------|-------------------|
| Choice Contrast Rules | Forces scripted choices to represent different player instincts, not polite variations. Includes contrast test and instinct test. | "Choices feel like three polite variations of the same action" |

**Why this makes the experience stronger:** Paul identified that choices often feel like three ways of saying the same thing. This makes the player feel like their decision doesn't matter because all roads lead to the same place. The contrast rules force each choice to map to a genuinely different player instinct (investigate vs challenge vs comfort) and require that each choice produces a visibly different outcome within 2 responses. The player feels that HOW they play reveals something about their character.

---

### ADDITION 4 → Deliverable 5 (Phase 6 — Consequence Mapping)

**1 rule set.**

| Rule Set | What It Does | Paul Issue It Fixes |
|----------|-------------|-------------------|
| Consequence Visibility Rule | Requires every mapped consequence to specify WHAT changes, WHEN the player sees it (target 2, max 3 responses), and HOW the player sees it. Flags invisible consequences for redesign. | "Make player consequences visible" — Paul's #3 priority |

**Why this makes the experience stronger:** Paul said the player can participate but doesn't always see how their choice changed things. Consequences exist in the state tracker but never surface to the player — making them invisible. This rule ensures the pipeline designs consequences that are SEEN, not just tracked. Small visible consequences (a character changes tone, an NPC notices) beat large invisible ones (a state variable changes but nothing looks different).

---

## WHAT THESE ADDITIONS DO NOT TOUCH

| What's Preserved | Why |
|-----------------|-----|
| Voice Lock (Deliverables 1A, 1B) | Voice extraction is separate from cadence |
| Voice Profiles | Loaded from DB into runtime — not affected by these rules |
| Ban Lists | Additive rules, not overrides |
| 14-Point Audit Protocol | Runs alongside these rules, not replaced by them |
| Format Detection (Deliverable 2) | No changes to format gate |
| Story Guard / Canon Protection | These rules enhance delivery, not story logic |
| Deliverables 6, 7, 9 | Untouched |

---

## PIPELINE VS RUNTIME — WHERE EACH ADDITION LIVES

| Addition | Deliverable | Runs When | Architecture Role |
|----------|------------|-----------|------------------|
| 1 (Cadence + Economy + Custom Input) | Deliverable 8 | RUNTIME — live during gameplay | Tells the narrator LLM HOW to generate |
| 2 (Beat Endings + First 3 Min) | Deliverable 3 | PIPELINE — once per IP at build time | Tells the pipeline LLM WHAT to design |
| 3 (Choice Contrast) | Deliverable 4 | PIPELINE — once per IP at build time | Tells the pipeline LLM WHAT to design |
| 4 (Consequence Visibility) | Deliverable 5 | PIPELINE — once per IP at build time | Tells the pipeline LLM WHAT to design |

**Rule:** Pipeline creates context. Runtime uses context. These additions respect that boundary.

---

## PAUL'S QUESTION — AND THE ANSWER

Paul asked: *"Can this deliver the story at a pace and rhythm that keeps users emotionally engaged?"*

These 4 additions are the mechanical answer. They don't change WHAT the pipeline builds. They change HOW beats are designed for momentum, HOW choices are designed for contrast, HOW consequences are designed for visibility, and HOW the runtime narrator delivers every response — shorter, sharper, more consequential, and always ending with a reason to keep going.

The foundation Paul praised (authored voice, recovery, character integrity, story worlds) stays completely intact. The cadence problem gets fixed at both the design layer and the delivery layer.
