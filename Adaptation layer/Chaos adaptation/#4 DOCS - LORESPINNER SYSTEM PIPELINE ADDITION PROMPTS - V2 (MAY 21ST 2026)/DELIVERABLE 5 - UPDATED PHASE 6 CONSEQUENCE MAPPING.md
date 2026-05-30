# DELIVERABLE 5: UPDATED PHASE 6 — CONSEQUENCE MAPPING

**Lorespinner Pipeline Upgrade — May 2026**
**Type:** Updated pipeline phase (runs per episode)
**Replaces:** Current Phase 6 prompt (Logic Companion pages 20-23)
**Implementation:** Swap the existing Phase 6 prompt with this one. Output payload is larger (3 new columns per path + freeform guidelines).

---

## COPY-PASTE PROMPT — REPLACES EXISTING PHASE 6

---

LORESPINNER — PHASE 6: DOWNSTREAM CONSEQUENCE MAPPING

[PASTE MASTER CONTEXT BLOCK HERE]

PHASE 5 CHOICE DESIGNS: [PASTE ALL BRANCHING AND EMOTIONAL CHOICES FOR THIS SESSION]
STORY SESSION MAP: [PASTE PHASE 2 OUTPUT — cross-session payoff plan]
PERSISTENT STATE SCHEMA: [PASTE PHASE 2 TASK 6 OUTPUT]
WORLD REACTIVITY RULES: [PASTE PHASE 2 TASK 7 OUTPUT]
PROTAGONIST CORE TRAIT: [FROM PHASE 1]
BRANCH DIMENSIONS: [FROM PHASE 2 TASK 5]

A choice without a planned consequence is an expressive choice pretending to be a branching one. This phase makes every consequence real, specific, and payable. Generalities are not acceptable. "The NPC behaves differently" is not a consequence. "Riven opens with 'You again' and does not make eye contact. The door she would have opened stays closed" is a consequence.

---

TASK 1 — BRANCHING CHOICE CONSEQUENCE MAPS

For each of the FOUR branching choices from Phase 5, complete the following table. Every cell must contain a specific, named moment. Vague language fails automatically.

```
CONSEQUENCE MAP — BRANCHING CHOICE #[N]
TRACKS: [Branch dimension from Phase 5]

                        | Path A ([ALIGNMENT])    | Path B ([ALIGNMENT])    | Path C ([ALIGNMENT])
------------------------|------------------------|------------------------|------------------------
IMMEDIATE EFFECT        | [Specific — what the   | [Specific]             | [Specific]
(this session, within   | player sees, hears,    |                        |
2 min of choice)        | receives differently]  |                        |
                        |                        |                        |
CURRENT SESSION ECHO    | [Specific — name the   | [Specific]             | [Specific]
(how this path colors   | scene and the          |                        |
the rest of the session)| difference]            |                        |
                        |                        |                        |
NEXT SESSION PAYOFF     | [Specific — name the   | [Specific]             | [Specific]
(a named moment in the  | character, the line,   |                        |
next session)           | the event, or object]  |                        |
                        |                        |                        |
LATER SESSION LEGACY    | [Specific or N/A]      | [Specific or N/A]      | [Specific or N/A]
(trace beyond the next  |                        |                        |
session)                |                        |                        |
                        |                        |                        |
WORLD STATE DELTA       | Inventory: [changes]   | Inventory: [changes]   | Inventory: [changes]
(NEW — persistent state | NPCs: [shifts]         | NPCs: [shifts]         | NPCs: [shifts]
changes per path)       | Environment: [flags]   | Environment: [flags]   | Environment: [flags]
                        | Alignment: [+1 to      | Alignment: [+1]        | Alignment: [+1]
                        | which]                 |                        |
                        | Emotional ledger:      | Emotional ledger:      | Emotional ledger:
                        | [entry]                | [entry]                | [entry]
                        |                        |                        |
REACTIVITY TRIGGERS     | [1-2 conditional world | [1-2 triggers]         | [1-2 triggers]
(NEW — world reactions  | reactions based on      |                        |
based on accumulated    | accumulated state]     |                        |
player state)           |                        |                        |
                        |                        |                        |
CROSS-EPISODE           | Resets: [what clears]  | Resets: [what clears]  | Resets: [what clears]
PROPAGATION             | Persists: [what        | Persists: [what        | Persists: [what
(NEW — what carries     | carries]               | carries]               | carries]
forward and what        | Escalates: [what       | Escalates: [what       | Escalates: [what
doesn't)               | compounds]             | compounds]             | compounds]
```

NOTE FOR BRANCHING CHOICE #4 (session-end hook):
- IMMEDIATE EFFECT = N/A (session ends on this choice)
- Replace with NEXT SESSION OPENING: tone, first image, first character, immediate stakes per path

---

TASK 2 — EMOTIONAL CHOICE CONSEQUENCE MAPS

Emotional choices converge — all paths arrive at the same next moment. But they DO update persistent state. For each emotional choice, provide a lighter consequence map:

```
CONSEQUENCE MAP — EMOTIONAL CHOICE #[N]

                        | Option A ([ALIGNMENT]) | Option B ([ALIGNMENT]) | Option C ([ALIGNMENT])
------------------------|----------------------|----------------------|----------------------
TONAL EFFECT            | [How narration voice  | [How narration voice  | [How narration voice
(how the next 200 words | shifts — specific]    | shifts]               | shifts]
of narration change)    |                      |                      |
                        |                      |                      |
STATE CHANGES           | NPCs: [disposition   | NPCs: [shifts]       | NPCs: [shifts]
                        | shifts]              |                      |
                        | Emotional ledger:    | Emotional ledger:    | Emotional ledger:
                        | [entry]              | [entry]              | [entry]
                        | Alignment: [+1]      | Alignment: [+1]      | Alignment: [+1]
                        |                      |                      |
CONVERGENCE POINT       | All paths arrive at: [Name the next shared moment]
```

---

TASK 3 — REACTIVITY TRIGGER SPECIFICATIONS

For each reactivity trigger referenced in Task 1, provide the full specification:

```
REACTIVITY TRIGGER #[N]:

CONDITION: "If player alignment tendency is [chaotic/lawful/neutral] by this point in the session..."
OR: "If player holds [object] from [earlier scene/episode]..."
OR: "If player's emotional ledger shows [pattern] (e.g., three or more acts of cruelty)..."

DEFAULT BEHAVIOR: [What happens if the condition is NOT met]
TRIGGERED BEHAVIOR: [What happens instead if the condition IS met — specific, named, in-world]

NARRATIVE EXECUTION: [How the narrator weaves this into the prose — one sentence instruction. Must be in the author's voice. Must not feel gamey.]

AFFECTED ELEMENTS:
  - NPC reactions: [Who changes, how]
  - Environmental details: [What shifts]
  - Dialogue variations: [What lines change]
  - Available options: [Do any future choices change?]
```

---

TASK 4 — CROSS-EPISODE STATE PROPAGATION RULES

Define explicit rules for how state carries between episodes:

```
CROSS-EPISODE PROPAGATION: SESSION [N]

RESETS BETWEEN EPISODES (temporary, cleared at episode boundary):
- [State element] — WHY: [It's scene-specific, not story-level]
...

PERSISTS ACROSS FULL STORY (permanent until explicitly changed):
- [State element] — WHY: [It defines the player's relationship with the world]
...

ESCALATES WITH ACCUMULATION (compounds over repeated instances):
- [Behavioral pattern] — ESCALATION RULE: [At N instances, trigger specific world change]
  - At 2 instances: [Mild shift — describe]
  - At 4 instances: [Moderate shift — describe]
  - At 6+ instances: [Pronounced shift — describe]
...
```

ESCALATION IS THE REPLAY DRIVER. A player who keeps betraying NPCs faces escalating consequences that a trusting player never sees. A player who shows mercy consistently unlocks NPC behaviors that ruthless players cannot access. This asymmetry creates replay value without requiring branching story structure.

---

TASK 5 — FREEFORM CONSEQUENCE GUIDELINES

For each BRANCHING choice, provide guidelines for how the runtime resolves freeform input:

```
FREEFORM GUIDELINES — BRANCHING CHOICE #[N]:

BRANCH DIMENSION: [What this choice operates on]

SPIRIT OF EACH OPTION:
  Option A (CHAOTIC): [The spirit — what this option REALLY means beneath the surface]
  Option B (LAWFUL): [The spirit]
  Option C (NEUTRAL): [The spirit]

FREEFORM ALIGNMENT MAPPING:
  Input that maps to CHAOTIC: [Types of freeform input — aggressive, defiant, impulsive, transgressive]
  Input that maps to LAWFUL: [Types — cautious, respectful, procedural, protective]
  Input that maps to NEUTRAL: [Types — observational, pragmatic, self-interested, adaptive]

HARD LIMITS (StoryGuard violations at this node):
  - [What freeform input would violate canon — specific]
  - [What would break character truth — specific]
  - [What would introduce elements that don't exist — specific]

FOLD-BACK EXECUTION:
  If input is classified as UNSUPPORTED by the Runtime Branch Resolution Policy:
  1. ACKNOWLEDGE: [How the narrator acknowledges the player's intent without executing it]
  2. REDIRECT: [The specific in-world reason the action doesn't succeed — must feel like the world responding, not a game boundary]
  3. ARRIVE AT: [Which existing outcome the fold-back connects to — must be the outcome whose SPIRIT is closest to the player's intent]
```

---

TASK 6 — VALIDATION

After completing all maps, run the following checks:

SPECIFICITY TEST: Does every cell in every branching choice consequence map contain a named moment, character, line, object, or event? Circle any cell that contains a generality. Revise.

ASYMMETRY TEST: Are the three paths for each branching choice genuinely different in their next-session experience? A player who chose Path A and a player who chose Path C should feel the next session was written for them.

PAYABILITY TEST: Can every consequence be paid given the source material? Flag any that require inventing material not latent in the source.

STATE CONSISTENCY TEST: Do the World State Deltas in Task 1 match the Persistent State Changes specified in Phase 5? Any mismatch is a data integrity failure.

REACTIVITY COHERENCE TEST: Do the Reactivity Triggers in Task 3 align with the World Reactivity Rules from Phase 2 Task 7? Triggers should INSTANTIATE the rules, not contradict them.

```
VALIDATION RESULTS:
  Specificity: [PASS / CELLS TO REVISE]
  Asymmetry: [PASS / CHOICES TO REVISE]
  Payability: [PASS / FLAGS]
  State consistency: [PASS / MISMATCHES]
  Reactivity coherence: [PASS / CONFLICTS]
```

---

OUTPUT FORMAT — Return all six tasks as a single Phase 6 document.

STOP. Do not finalize any session prose until Phase 6 is reviewed. Consequences that are vague, symmetric, or non-payable produce a flat experience. State inconsistencies between Phase 5 and Phase 6 will crash the runtime state manager. Fix everything before continuing.

---

## END OF DELIVERABLE 5
