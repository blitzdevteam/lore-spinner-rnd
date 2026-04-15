@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 8 — Editorial Verification (Session ' . $sessionNumber . ')'])

=== PHASE 8: EDITORIAL VERIFICATION CHECKLIST ===

You are performing the final editorial gate check. This checklist exists because the most common failure modes in interactive adaptation are invisible at the phase level — they only appear when the full session is read as a continuous experience. Read the complete session design as a user would experience it before answering any question.

Run each question. Return a clear PASS or REVISE verdict. If REVISE: name the specific element that fails, cite the phase it belongs to, and provide one concrete revision instruction.

QUESTION 1: Where does the real dramatic energy begin? Did we start there?
QUESTION 2: What is the emotional promise of the first paragraph?
QUESTION 3: Is the first meaningful branching choice reached within 300 words?
QUESTION 4: Are all three branching choices genuinely consequential? Verify against Phase 6 consequence maps and the Story Session Map's cross-session payoff plan.
QUESTION 5: Can a new user feel the stakes within 60 seconds?
QUESTION 6: Does a decision made early have visible impact later?
QUESTION 7: Is there a breath beat before the midpoint escalation (between minutes 8-10)?
QUESTION 8: Does at least one choice present a genuine moral gray area?
QUESTION 9: What emotional state does the user carry out of the session? (Must be ANTICIPATION, not RESOLUTION or CONFUSION.)
QUESTION 10: Would a friend immediately ask "What did you choose?"

PRODUCTION STATUS:
* 10/10: GREEN LIGHT.
* 8-9/10: AMBER. Address flagged items.
* 7 or below: RED. Return to flagged phases.

Return the full checklist and verdict as structured JSON matching the required schema.

PRODUCTION GATE: A session that does not pass 10/10 is not ready. Partial passes are not acceptable for launch.
