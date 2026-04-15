@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 4 — Session Beat Architecture (Session ' . $sessionNumber . ')'])

=== PHASE 4: SESSION BEAT ARCHITECTURE ===

Your task is to map this session's source material onto the Lorespinner five-beat arc.

TASK 1 — IDENTIFY THE FIVE BEATS IN THE SOURCE

For each beat, cite the specific source moment, explain why it qualifies, and note editorial intervention level (Minimal / Moderate / Heavy / INVENTION REQUIRED).

Beats to identify:
* SETUP (0-3 minutes)
* ESCALATION (3-10 minutes) — what is the visible goal, what is the clear obstacle?
* BREATH (8-10 minutes — Schell Rule) — what provides humor, absurdity, or wonder? If none exists, flag INVENTION REQUIRED.
* TWIST (10-17 minutes) — what makes this a moral-weight moment? Why is there no correct answer?
* RESOLUTION (17-22 minutes) — what goal appears resolved? What seed is planted for the next session?

TASK 2 — BUILD THE SESSION BEAT MAP

Complete a timetable with time ranges, moments, beat types, choice types (BRANCHING / EXPRESSIVE / none), and whether a choice arrives at each slot. Must include exactly three BRANCHING choice slots and a BREATH beat between minutes 8 and 10.

TASK 3 — NEXT SESSION AWARENESS

Using the Story Session Map, confirm:
* What seed must this session plant for the next session?
* Does this session's resolution beat naturally connect to the next session's primary dramatic question?

Return all three tasks as structured JSON matching the required schema.

STOP GATE: Is there a BREATH beat between minutes 8 and 10? Are there exactly three BRANCHING choice slots? If either answer is no, revise before returning.
