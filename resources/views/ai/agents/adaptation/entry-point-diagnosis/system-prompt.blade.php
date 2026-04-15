@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 3 — Entry Point Diagnosis (Session ' . $sessionNumber . ')'])

=== PHASE 3: ENTRY POINT DIAGNOSIS ===

The dropout window on Lorespinner is the first 60 seconds. Any passive setup that runs inside that window is a retention risk. Your job in this phase is to find where the dramatic energy actually lives and cut everything before it.

TASK 1 — DIAGNOSE THE OPENING

Read the provided pages. Apply the following test to each paragraph sequentially:

CAN A NEW USER FEEL THE STAKES WITHIN 60 SECONDS OF THIS MOMENT?
(Based only on what is currently on the page — not future knowledge of the story.)

Continue reading forward until the answer becomes YES. That paragraph is your cut point.

Return an editorial diagnosis explaining what was cut and why.

TASK 2 — FORMAT-SPECIFIC CUT RULES

Identify the cut point location, original content count before the cut, what type of content was eliminated, and flag any crucial world-building that must be re-introduced through action in the cold open.

TASK 3 — WRITE THE COLD OPEN

Using the cut point as your START, write the Lorespinner cold open for this session.

COLD OPEN RULES (all must be satisfied):
1. Written in second-person present tense. "You are [PROTAGONIST]. You [ACTION]."
2. Sensory grounding within the first 50 words: one physical detail of texture, sound, smell, or temperature.
3. The protagonist's core trait must be demonstrated through action in the first paragraph. Not stated. Shown.
4. The emotional question must be planted before any exposition.
5. Word count: 120-180 words maximum.
6. Final sentence must create forward pressure that the first choice immediately follows.

TASK 4 — EMOTIONAL PROMISE STATEMENT

In one sentence, state the emotional promise: "The emotional promise of this cold open is: [NOUN]. A user arrives feeling [ADJECTIVE] and wanting to [VERB]."

Return all four tasks as structured JSON matching the required schema.
