{{-- Pipeline Upgrade V2.2 — Deliverable 1B: Screenwriter Voice Lock merge synthesis. --}}
@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetectionOutput ?? ($formatDetection ?? ''), 'currentPhase' => $currentPhase ?? 'Voice Lock Phase — Screenwriter Merge (1B)'])

LORESPINNER — VOICE LOCK PHASE: SCREENWRITER / TV WRITER (DELIVERABLE 1B FINAL)

FORMAT DETECTION:
{{ json_encode($formatDetection ?? [], JSON_PRETTY_PRINT) }}

PHASE 1 AUDIT:
{{ json_encode($ipAudit ?? [], JSON_PRETTY_PRINT) }}

You are synthesizing the complete SCREENWRITER Voice Profile from per-chapter observation fragments. Set profile_type to SCREENWRITER.

The output becomes CONSTITUTIONAL LAW. It overrides every subsequent phase.

THIS IS A SCREENWRITER EXTRACTION. Do NOT apply novelist metrics. Prose translation must follow the SCREENPLAY-TO-PROSE PROTOCOL.

---

TASK 1 — WRITER VOICE DNA EXTRACTION (synthesize across all chapter fragments)

A. SIGNATURE WRITING TECHNIQUES (8-12): name, 2-3 quotes, why this writer, frequency.

B. ACTION LINE METRICS: average words per line, fragment percentage, verb-first percentage, ALL CAPS density, paragraph rhythm, 3-4 demonstrative action lines.

C. DIALOGUE METRICS (summarize in sentence_level_patterns and diction where applicable): speech length, contractions, interruptions.

D. DICTION FINGERPRINT: vocabulary clusters, register in action lines, 5-6 distinctive quotes from action lines or dialogue.

E. SCREENPLAY STRUCTURE METRICS: scene density, INT/EXT ratio, action-to-dialogue ratio, transition types, parenthetical vocabulary, character introduction patterns.

F. EMOTIONAL VOCABULARY HIERARCHY: rank MOTION/KINETIC, PHYSICAL/BODILY, DARK/LIGHT, SOUND, VIOLENCE, EMOTIONAL STATE with representative quotes.

G. CHARACTER DIALOGUE FINGERPRINT — PER MAJOR CHARACTER: rhythm, tics, restrictions, emotional range, 3+ distinguishing markers, signature line.

H. EMOTIONAL RANGE MAP: all seven registers with quote, technique, rendering method.

I. COLLOCATION FINGERPRINT (15-20 pairs): pair, quotes, frequency, AI substitution, category.

J. NEGATIVE SPACE MAP (minimum 5): screenwriter-specific absences (camera direction, V.O., montage, novelistic interiority, etc.).

K. SHOW/EXPLAIN RATIO: calibrated to THIS writer's action-line show/explain balance.

L. COMPARATIVE EXCLUSION (2-3 neighbors): neighbor writer, overlapping quality, differentiating techniques.

M. SCREENPLAY-TO-PROSE PROTOCOL: element-by-element rules (scene headings, action lines, dialogue, parentheticals, transitions) for how runtime prose translates this writer's screenplay voice.

---

TASK 2 — MASTER RULE 1: HARD BAN LIST

SECTION A — UNIVERSAL BANS:
@include('ai.agents.adaptation.voice-lock._voice-lock-universal-bans')

SCREENWRITER NOTE: Maximum force on show-don't-explain. Could this sentence be filmed? If not, cut it.

SECTION B — IP-SPECIFIC BANS (minimum 6): ban, evidence, positive replacement.

Set universal_bans_acknowledged to true.

---

TASK 3 — 14-POINT CONTINUOUS AUDIT PROTOCOL

Design exactly 14 audit points for RUNTIME self-audit. Points 6 (Action Line Compression), 7 (Dialogue Compression), and 11 (Screenplay-to-Prose Compliance) must be SCREENWRITER-SPECIFIC.

RUNTIME PASS THRESHOLD: 14/14.

Return structured JSON matching the required schema. profile_type must be SCREENWRITER.
