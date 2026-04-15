@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 1 — IP Audit'])

=== PHASE 1: IP AUDIT ===

You have three sections of the source IP. Using only what is present in these sections — do not assume or infer content from cultural knowledge of this story — complete the following IP Audit.

Score each of the six criteria on a scale of 1 to 3. Provide two to four sentences of specific evidence for each score. Quote from the source where possible.

CRITERION 1 — LICENSING FRICTION (Score 1-3)
3 = Public domain. No restrictions.
2 = Licensed IP with defined adaptation rights. Specific content restrictions known.
1 = Licensed IP with unclear or contested adaptation rights. Gatekeeping likely.

CRITERION 2 — LATENT CHOICE ARCHITECTURE (Score 1-3)
3 = The source already contains natural decision points, threshold moments, and characters who present options. Structure is already branching in spirit.
2 = Some natural forks exist but require significant editorial invention to surface.
1 = Linear, single-path narrative with no natural decision points. High invention cost.

CRITERION 3 — BOUNDED AGENCY SCORE (Score 1-3)
3 = Protagonist has an immovable core trait. User choices shape how this trait expresses, not whether it exists.
2 = Protagonist's identity is clear but partially dependent on plot events to be established.
1 = Protagonist's identity IS the arc. Changing choices risks undermining the character entirely.

CRITERION 4 — EMOTIONAL RANGE (Score 1-3)
3 = At least four distinct emotional registers present.
2 = Two to three emotional registers. Sessions will require tonal invention to avoid flatness.
1 = One dominant emotional tone. High risk of flat sessions.

CRITERION 5 — RECOGNIZABILITY COEFFICIENT (Score 1-3)
3 = The IP is widely known. Users arrive emotionally invested. Zero onboarding cost.
2 = Niche or specialist recognition.
1 = Unknown IP. First session must do full world-building AND create stakes from scratch.

CRITERION 6 — REPLAYABILITY HOOK (Score 1-3)
3 = A natural world metaphor for branching is built into the IP.
2 = Replayability must be manufactured.
1 = The world or protagonist actively resists the idea of multiple paths.

Verdict thresholds:
* 15-18: GREEN LIGHT. Proceed to Phase 2.
* 10-14: AMBER. Flag lowest-scoring criterion and propose editorial mitigation.
* Below 10: RED. Do not proceed.

Return your analysis as structured JSON matching the required schema.
