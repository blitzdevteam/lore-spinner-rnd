@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 5 — Choice Design'])

=== PHASE 5: CHOICE DESIGN ===

You are writing all choices for this session. There are two choice types. Do not confuse them.

BRANCHING CHOICE: Load-bearing. Changes what the story tracks about the user going forward. Three per session. Each must reference a branch dimension defined in the Phase 2 Story Session Map (or explicitly declare a new one if required). Requires a full consequence map (Phase 6 will complete this — in this phase, sketch the immediate downstream effect only).

EXPRESSIVE CHOICE: Textural. Colors narration voice, sets tone, adds inventory detail. Does not fork the story. All paths arrive at the same next story moment. Typically two to four per session.

CHOICE WRITING RULES (apply to every choice):
1. Three options per choice. Always A, B, C.
2. Each option reflects a genuine human value — not a personality type, not a difficulty setting.
3. One option per choice must be the "wait and observe" path.
4. Written in second-person present tense.
5. Each option is one sentence. Declarative. No filler.
6. For BRANCHING choices: write the immediate downstream effect as one italic sentence after each option.
7. For EXPRESSIVE choices: write the narration/tonal effect after each option.
8. Branching Choice #2 (moral-weight) must have no objectively correct answer.

TASK 1 — BRANCHING CHOICE #1 (SETUP BEAT)
Identity-establishing choice within 300 words of cold open. What this choice tracks must reference a Phase 2 branch dimension.

TASK 2 — EXPRESSIVE CHOICES (ESCALATION AND BREATH BEATS)
Write 2-3 expressive choices.

TASK 3 — BRANCHING CHOICE #2 — THE MORAL-WEIGHT CHOICE (TWIST BEAT)
Stickiness target. Name three values in tension. Must reference a Phase 2 branch dimension.

TASK 4 — BRANCHING CHOICE #3 — THE SESSION-END HOOK (RESOLUTION BEAT)
Does NOT resolve within this session. Forward-commitment device. Must reference a Phase 2 branch dimension.

Choice IDs must follow the format S{session}_C{number} (e.g. S1_C1, S1_C2, S1_C3).

Return all four tasks as structured JSON matching the required schema.

STOP GATE: Does every branching choice have three clearly different downstream effects? Does Choice #2 have no correct answer? Does Choice #3 end the session without resolution? If any answer is no, revise.
