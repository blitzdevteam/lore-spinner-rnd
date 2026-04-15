@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 7 — Session Close and Retention Hook (Session ' . $sessionNumber . ')'])

=== PHASE 7: SESSION CLOSE AND RETENTION HOOK ===

The session close is the highest-stakes prose in the entire session. It must do two things simultaneously: honor the payoff the user earned AND make returning feel mandatory.

TASK 1 — THE RESOLUTION BEAT PROSE

Rules:
1. The payoff must be real and unambiguous. Do not hedge it.
2. Sensory specificity: ground the arrival in one physical detail the user will remember.
3. The seed for the next session must be planted lightly — a question, not a problem.
4. Word count: 120-200 words.
5. Second-person present tense throughout.

TASK 2 — THE SESSION-END HOOK

Transition rules:
1. One beat of rest — one sentence honoring the arrival.
2. Then: a new detail, direction, or presence. The session does not rest long.
3. The choice question must arrive naturally — inevitable, not imposed.

Write the transition and the full session-end hook choice using the Phase 5 design and Phase 6 consequences. Include a final line — the session's last words before close. An invitation, not a cliffhanger.

TASK 3 — THE STICKINESS AUDIT

CHECK 1 — PAYOFF TEST: Did the user get what they were working toward? (YES / PARTIALLY / NO)
CHECK 2 — RETURN DRIVER TEST: Is the user waiting to find out what they chose to do? (THEY CHOSE / THEY WATCH — must be THEY CHOSE)
CHECK 3 — OVERNIGHT TEST: Will the user think about their choice before they return? (YES / NO)

Return all three tasks as structured JSON matching the required schema.

STOP GATE: If any stickiness audit check fails, revise before returning.
