@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 2 — Story Session Map'])

=== PHASE 2: STORY SESSION MAP ===

You have the full structural extraction of the source IP. Your job is to convert these canonical chunks into a story-wide session roadmap before any single session is designed in detail.

TASK 1 — SESSION COUNT AND ALLOCATION

Using the estimated session count from Format Detection as a starting point, confirm or revise the session count based on the actual event structure. Then allocate events to sessions.

Rules:
* Each session covers a natural dramatic arc (rising tension, climax/choice, resolution)
* Session boundaries should fall at natural breaks — chapter boundaries, location shifts, or time jumps
* No session should contain fewer than 5 events or more than 20
* The first session must open with the highest-energy material

TASK 2 — ARC PROGRESSION

For each session, name the primary dramatic question, the emotional register shift from the previous session, and the key transition moment.

TASK 3 — MAJOR BRANCH OPPORTUNITIES

Identify the 2-3 strongest branching choice opportunities per session. Reference specific event positions. For each, name the event position and title, what dimension it would track, and whether it has natural downstream payoff in a later session.

TASK 4 — CROSS-SESSION PAYOFF PLAN

Map the highest-value branching choices from early sessions to their payoff moments in later sessions.

TASK 5 — BRANCH DIMENSION DEFINITIONS (STORY-LEVEL)

Using the branch opportunities identified in Task 3, define the core branch dimensions of the story. A branch dimension is a reusable narrative axis that multiple choices across sessions may reference and evolve.

Rules:
* Each dimension is a tension or axis, not a single trait (e.g. trust_vs_caution, not just trust)
* A story should have 3-6 core dimensions
* Each dimension must connect to at least one branch opportunity from Task 3
* Dimensions defined here are canonical — Phase 5 must reference them when designing branching choices

Return all five tasks as structured JSON matching the required schema.

STOP GATE: Before finalizing, verify: Does every session have a primary dramatic question? Are there at least 2 branching opportunities per session? Does the cross-session payoff plan connect at least 2 early choices to later sessions? Are there at least 3 defined branch dimensions? If any answer is no, revise before returning.
