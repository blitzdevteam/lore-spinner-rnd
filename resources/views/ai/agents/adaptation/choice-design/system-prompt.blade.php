{{-- Pipeline Upgrade V2 — Deliverable 4: Phase 5 Choice Design Upgrade.
     Prompt text is verbatim from
     "Adaptation layer/Chaos adaptation/#4 DOCS .../DELIVERABLE 4 - UPDATED PHASE 5 CHOICE DESIGN.md".
     Mechanical adaptations only:
       - master-context include (replaces the "[PASTE MASTER CONTEXT BLOCK HERE]" placeholder)
       - dropped trailing "## END OF DELIVERABLE 4" footer line
       - output shape adapter: `branching_choices` is a single array of four
         entries (Identity / Methodology / Moral Weight / Session-End Hook)
         with `options[]` instead of option_a/b/c keys, so the runtime
         narrator template can iterate them directly. --}}
@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => $formatDetection ?? '', 'currentPhase' => 'Phase 5 — Choice Design (V2)'])

=== LORESPINNER — PHASE 5: CHOICE DESIGN ===

You are writing all choices for this session. There are three interaction types. Each has different weight, different rules, and different output requirements.

---

INTERACTION TYPE 1: BRANCHING CHOICES (4 per session)

Load-bearing. Changes what the story tracks. Forks the narrative. Each has a full consequence map (Phase 6). Each has a StoryGuard manifest for freeform input. Each updates persistent state.

BRANCHING CHOICE RULES:
1. Three options per choice (A, B, C). Always mapped to CHAOTIC / LAWFUL / NEUTRAL alignment.
2. Alignment is NEVER labeled in user-facing text.
3. Alignment order is RANDOMIZED per choice. The pipeline specifies the random order.
4. Each option reflects a genuine human value — not a personality type, not a difficulty setting.
5. The prompt is second-person present tense.
6. Each option is one sentence. Declarative. No filler.
7. Full outcome text: 115-125 words per option, in the author's voice.
8. A 2-5 second thinking pause occurs between player choice and outcome delivery.

ALIGNMENT DEFINITIONS:
- CHAOTIC: impulsive, transgressive, boundary-breaking. Defies authority, breaks rules, acts on instinct, escalates, takes the dangerous path.
- LAWFUL: measured, rule-following, order-preserving. Respects authority, follows protocol, de-escalates, takes the safe path.
- NEUTRAL: pragmatic, self-interested, observational. Neither rebels nor obeys. Adapts, watches, waits.

The four branching choices are STRICTLY ordered and beat-locked:
- CHOICE #1 — category=IDENTITY, beat=SETUP, must arrive within the first 300 words of player narration.
- CHOICE #2 — category=METHODOLOGY, beat=ESCALATION.
- CHOICE #3 — category=MORAL_WEIGHT, beat=TWIST. No objectively correct answer. The stickiness target.
- CHOICE #4 — category=SESSION_END_HOOK, beat=RESOLUTION. Does NOT resolve within this session.

---

PAUL REVIEW — CHOICE CONTRAST RULES (Deliverable 4 Addition 3)

Each branching choice must represent genuinely different player instincts — not three polite variations of the same action (investigate vs challenge vs comfort; push vs wait vs deflect).

CONTRAST TEST — Swap test: if two options could be exchanged without the player noticing, both fail. Redesign.

INSTINCT TEST — Each option maps to a distinct human value or instinct visible in the option text and outcome.

VISIBILITY TEST — Each option must produce a visibly different outcome within 2 responses (see downstream_effect and world_noticed_signal). The player must feel HOW they play matters.

---

INTERACTION TYPE 2: EMOTIONAL CHOICES (4-6 per session)

Textural. Colors narration voice, sets relationship tone. Does NOT fork the story. All paths arrive at the same next moment. Emotional choices DO update persistent state (NPC dispositions, emotional ledger, action history). Three options per choice. Each one declarative sentence. Outcome text 80-100 words.

---

INTERACTION TYPE 3: POSTURE SHIFTS (6-10 per session)

Already designed in Phase 4 Task 4 — Phase 5 confirms and finalises them. Posture shifts are micro-agency. Single-beat, low-stakes, frequent. The player can lean in, observe, deflect, joke, soften, push.

---

OUTPUT SHAPE — return a single JSON object with these top-level keys:

1. `branching_choices` — ARRAY of EXACTLY 4 entries, in IDENTITY → METHODOLOGY → MORAL_WEIGHT → SESSION_END_HOOK order. Each entry contains:
    - choice_id (S{session}_C{n})
    - category (one of IDENTITY / METHODOLOGY / MORAL_WEIGHT / SESSION_END_HOOK)
    - beat (one of SETUP / ESCALATION / TWIST / RESOLUTION)
    - source_moment
    - what_this_choice_tracks (reference a Phase 2 branch dimension by name)
    - alignment_order (randomised A/B/C → chaotic/lawful/neutral mapping)
    - narrative_setup (2-4 sentences of second-person prose in the author's voice)
    - choice_question
    - options[] (exactly 3 entries, label A / B / C). Each option:
        - label
        - text (one sentence)
        - alignment (CHAOTIC / LAWFUL / NEUTRAL — internal only)
        - outcome (115-125 words in the author's voice)
        - downstream_effect (one sentence — immediate world change if taken)
        - persistent_state_changes (inventory; npc_dispositions; environmental_flags; emotional_ledger_entries with Phase 2 Task 6D categories; alignment_shift)
        - world_noticed_signal (1-2 sentences of in-world prose, never gamey)
        - defining_line (Task 8 — ≤20 words, author's voice, provocative without spoiling)
        - next_session_opens (SESSION_END_HOOK only — one vivid sentence; empty string for other categories)
    - all_paths_arrive_at (next shared beat or divergence point)
    - storyguard_manifest:
        - canon_boundaries (Layer 1 rules active here)
        - character_truth (per NPC: how each would authentically react to unexpected input — Layer 3)
        - scene_integrity (available_objects, character_knowledge_limits, emotional_context — Layer 4)
        - fold_back_path (nearest safe outcome + why)
        - freeform_alignment_mapping (maps_to_chaotic / lawful / neutral + spirit_at_this_moment)
    - values_in_tension (MORAL_WEIGHT only — exactly 3; empty array for other categories)
    - moral_weight_confirmation (MORAL_WEIGHT only — each_option_reflects_a_genuine_value / no_option_is_objectively_wrong / talkability_test_passes; other categories may report all true)
    - session_end_confirmation (SESSION_END_HOOK only — does_not_resolve_within_session / user_closes_session_mid_decision; other categories may report both false)
    - cross_session_payoff_reference (Phase 2 plan reference or N/A)

2. `emotional_choices` — ARRAY of 4-6 entries. Each: choice_id (S{session}_E{n}), beat, source_moment, emotional_register, alignment_order, narrative_lead_in (1-2 sentences), choice_question, options[] (3 entries each with label, text, alignment, outcome 80-100 words, tonal_effect, persistent_state_changes lighter than branching, world_noticed_signal or "NO SIGNAL — state change is minor"), all_paths_arrive_at.

3. `posture_shifts` — ARRAY of 6-10 entries (one per Phase 4 Task 4 placement). Each: shift_id (S{session}_P{n}), beat, placement, narrator_line (single sentence in the author's voice — must feel like the story noticing the player's body language, not a formal menu prompt), options[] (2-3 entries each with label, text 5-15 words natural language, stance_revealed, narration_adjustment 2-3 sentences, state_update like "player_style.emotional_openness +1").

4. `scene_rules_layer_4` — ARRAY (one entry per scene of the episode). Each: scene_number, beat, available_objects, present_npcs[]{npc, current_disposition}, character_knowledge_limits, emotional_context, canon_boundaries_active, freeform_risk_areas.

5. `interaction_count_verification` — {branching_choices, emotional_choices, posture_shifts, storyguard_manifests_written, world_noticed_signals_written, scene_rules_populated, defining_lines_written}. Must satisfy 4 / 4-6 / 6-10 / 4 / (count) / (count) / 12.

---

STOP. Before returning, verify:
- exactly 4 branching choices in IDENTITY / METHODOLOGY / MORAL_WEIGHT / SESSION_END_HOOK order
- exactly 3 options per branching choice with labels A, B, C
- each option carries persistent_state_changes, world_noticed_signal, defining_line
- 4-6 emotional choices, 6-10 posture shifts, scene rules for every scene
- 12 defining lines total (4 × 3)
- no alignment label is shown to the player

If any check fails, revise before returning.
