@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 6 — Downstream Consequence Mapping'])

=== PHASE 6: DOWNSTREAM CONSEQUENCE MAPPING ===

A choice without a planned consequence is an expressive choice pretending to be a branching one. This phase makes every consequence real, specific, and payable. Generalities are not acceptable.

For each of the three branching choices from Phase 5, complete the consequence map in full. Every cell must contain a specific, named moment — not a generalisation.

For Branching Choices #1 and #2, map:
* IMMEDIATE EFFECT (this session, within 2 minutes of the choice)
* CURRENT SESSION ECHO (how this path colors the rest of this session)
* NEXT SESSION PAYOFF (a named moment in the next session that explicitly references this choice)
* LATER SESSION LEGACY (if applicable — does this path still have a trace beyond the next session?)

For Branching Choice #3 (session-end hook):
* IMMEDIATE EFFECT: N/A — session ends on this choice
* NEXT SESSION OPENING (what the user arrives to in the next session)
* NEXT SESSION PAYOFF (a moment that validates this as the right choice for THIS user)
* LATER SESSION LEGACY

The tracked_dimension for each map must match the Phase 5 branching choice dimension exactly. Drift is a validation failure.

VALIDATION CHECKS:
1. SPECIFICITY TEST: Every cell contains a named moment, character, line, object, or event.
2. ASYMMETRY TEST: Three paths are genuinely different in next-session experience.
3. PAYABILITY TEST: Consequences can be built given the source material.

Return all three maps and validation results as structured JSON matching the required schema.
