{{-- Pipeline Upgrade V2.2 — Deliverable 1A: Novelist Voice Lock merge synthesis. --}}
@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetectionOutput ?? ($formatDetection ?? ''), 'currentPhase' => $currentPhase ?? 'Voice Lock Phase — Novelist Merge (1A)'])

LORESPINNER — VOICE LOCK PHASE: NOVELIST / AUTHOR (DELIVERABLE 1A FINAL)

FORMAT DETECTION:
{{ json_encode($formatDetection ?? [], JSON_PRETTY_PRINT) }}

PHASE 1 AUDIT:
{{ json_encode($ipAudit ?? [], JSON_PRETTY_PRINT) }}

You are synthesizing the complete NOVELIST Voice Profile from per-chapter observation fragments. Set profile_type to NOVELIST.

The output becomes CONSTITUTIONAL LAW. It overrides every subsequent phase.

---

TASK 1 — AUTHOR VOICE DNA EXTRACTION (synthesize across all chapter fragments)

Produce all sections below with direct quotes drawn from fragments. Minimum counts are hard requirements.

A. SIGNATURE WRITING TECHNIQUES (8-12): name, 2-3 quotes, why this author, frequency.

B. SENTENCE-LEVEL PATTERNS: average length, cadence, clause preference, punctuation habits, 3-4 demonstrative sentences.

C. DICTION FINGERPRINT: vocabulary clusters, register, word frequency patterns, 5-6 distinctive quotes.

D. NARRATOR PERSPECTIVE: POV, reliability, distance, commentary, tense, interior monologue, 3-4 representative quotes.

E. PARAGRAPH ARCHITECTURE: pattern, transitions, chapter opening/closing style, 2 consecutive demonstrative paragraphs.

F. DIALOGUE FINGERPRINT — PER MAJOR CHARACTER: rhythm, tics, restrictions, emotional range, 3+ distinguishing markers, signature line. Dialogue differentiation is mandatory.

G. DIALOGUE TAG PATTERNS: said percentage, other tags, action beats frequency, banned tags.

H. EMOTIONAL RANGE MAP: TENSION, HUMOR, GRIEF, WONDER, FEAR, VIOLENCE, INTIMACY — quote, technique, rendering method. Mark ABSENT where absent.

I. COLLOCATION FINGERPRINT (15-20 pairs): pair, quotes, frequency, AI substitution, category.

J. NEGATIVE SPACE MAP (minimum 5): technique, absence evidence, why AI defaults.

K. SHOW/EXPLAIN RATIO: show language, explain language, approximate balance, enforcement note.

L. COMPARATIVE EXCLUSION (2-3 neighbors): neighbor author, overlapping quality, 2+ differentiating techniques.

---

TASK 2 — MASTER RULE 1: HARD BAN LIST

SECTION A — UNIVERSAL BANS (identical for every IP):
@include('ai.agents.adaptation.voice-lock._voice-lock-universal-bans')

SECTION B — IP-SPECIFIC BANS (minimum 6): ban, evidence from Task 1, positive replacement.

Set universal_bans_acknowledged to true.

---

TASK 3 — 14-POINT CONTINUOUS AUDIT PROTOCOL

Design exactly 14 audit points tailored to this IP for RUNTIME self-audit by the narrator LLM. Points 7 (Paragraph Architecture) and 11 (Narrator Compliance) must be NOVELIST-SPECIFIC.

For each point: point_number, point_name, pass_fail_definition, detection_method, repair_instruction.

RUNTIME PASS THRESHOLD: 14/14.

Return structured JSON matching the required schema. profile_type must be NOVELIST.
