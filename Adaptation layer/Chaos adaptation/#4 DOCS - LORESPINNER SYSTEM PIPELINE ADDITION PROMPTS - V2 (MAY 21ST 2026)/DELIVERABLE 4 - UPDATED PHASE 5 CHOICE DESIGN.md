# DELIVERABLE 4: UPDATED PHASE 5 — CHOICE DESIGN

**Lorespinner Pipeline Upgrade — May 2026**
**Type:** Updated pipeline phase (runs per episode)
**Replaces:** Current Phase 5 prompt (Logic Companion pages 16-20)
**Implementation:** Swap the existing Phase 5 prompt with this one. Output payload is significantly larger (alignment system, StoryGuard manifests, persistent state changes, World Noticed signals).

---

## COPY-PASTE PROMPT — REPLACES EXISTING PHASE 5

---

LORESPINNER — PHASE 5: CHOICE DESIGN

[PASTE MASTER CONTEXT BLOCK HERE]

PHASE 4 BEAT MAP: [PASTE FULL BEAT MAP FOR THIS SESSION]
STORY SESSION MAP: [PASTE PHASE 2 OUTPUT — for cross-session payoff awareness]
PERSISTENT STATE SCHEMA: [PASTE PHASE 2 TASK 6 OUTPUT]
WORLD REACTIVITY RULES: [PASTE PHASE 2 TASK 7 OUTPUT]
STORYGUARD CANON EXTRACTION: [PASTE PHASE 2 TASK 8 OUTPUT]
VOICE PROFILE: [PASTE VOICE LOCK OUTPUT — Section 1 (Voice DNA) + Section 2 (Master Rule 1)]
PROTAGONIST CORE TRAIT (from Phase 1): [NAME THE TRAIT]
EMOTIONAL PROMISE (from Phase 3): [PASTE STATEMENT]
BRANCH DIMENSIONS (from Phase 2 Task 5): [PASTE DIMENSION TABLE]

---

You are writing all choices for this session. There are three interaction types. Each has different weight, different rules, and different output requirements.

---

INTERACTION TYPE 1: BRANCHING CHOICES (4 per session)

Load-bearing. Changes what the story tracks. Forks the narrative. Each has a full consequence map (Phase 6). Each has a StoryGuard manifest for freeform input. Each updates persistent state.

BRANCHING CHOICE RULES:
1. Three options per choice. Always mapped to CHAOTIC / LAWFUL / NEUTRAL alignment.
2. Alignment is NEVER labeled in user-facing text. The words chaotic, lawful, neutral never appear to the player.
3. Alignment order is RANDOMIZED per choice. Never fixed. The pipeline specifies the random order for each choice.
4. Each option reflects a genuine human value — not a personality type, not a difficulty setting.
5. The prompt is second-person present tense. "What do you do?" not "What does [PROTAGONIST] do?"
6. Each option is one sentence. Declarative. No filler.
7. Full outcome text: 115-125 words per option. Written in the author's voice (reference Voice Profile signature techniques).
8. A 2-5 second thinking pause occurs between player choice and outcome delivery. Runtime fills with ambient sound.

ALIGNMENT DEFINITIONS:

CHAOTIC: The impulsive, transgressive, boundary-breaking option. Defies authority, breaks rules, acts on instinct, escalates conflict, takes the dangerous path.

LAWFUL: The measured, rule-following, order-preserving option. Respects authority, follows protocol, acts with caution, de-escalates, takes the safe path.

NEUTRAL: The pragmatic, self-interested, or observational option. Neither rebels nor obeys. Adapts, watches, waits, takes what serves the moment.

Over multiple choices, the player's alignment tendency accumulates in runtime state. This influences: NPC reactions, world reactivity intensity, available options in later episodes, and the Social Echo share card.

---

INTERACTION TYPE 2: EMOTIONAL CHOICES (4-6 per session)

Textural. Colors narration voice, sets relationship tone, adds inventory detail. Does NOT fork the story. All paths arrive at the same next moment. But emotional choices DO update persistent state (NPC dispositions, emotional ledger, action history).

EMOTIONAL CHOICE RULES:
1. Three options per choice. Alignment-mapped but lighter weight.
2. Each option is one sentence.
3. Outcome text: 80-100 words per option.
4. All three outcomes converge to the same next beat. The difference is TEXTURE, not direction.
5. 2-5 second thinking pause applies.

---

INTERACTION TYPE 3: POSTURE SHIFTS (6-10 per session)

Already designed in Phase 4 Task 4. Phase 5 confirms and refines them but does not redesign. Reference the Phase 4 posture shift placements and finalize:
- The narrator line (confirm or revise)
- The response options (confirm or refine natural language)
- The narration adjustment text (write the actual 2-3 adjusted sentences for each response direction)
- The state update (confirm symbolic memory change)

---

TASK 1 — BRANCHING CHOICE #1 (SETUP BEAT — within 300 words of cold open)

Identity-establishing choice. Sets the register the player carries through the session.

Source moment: [FROM BEAT MAP]
What this choice tracks: [Must reference a defined branch dimension from Phase 2]
Alignment order for this choice: [Randomized — e.g., B=chaotic, A=lawful, C=neutral]

NARRATIVE SETUP (2-3 sentences of second-person prose in the author's voice):
[Write the passage immediately before the question]

CHOICE QUESTION: [In second person]

```
A. [OPTION TEXT — one sentence]
   Alignment: [CHAOTIC / LAWFUL / NEUTRAL] (internal only — never shown to player)
   Outcome (115-125 words): [Full outcome text in the author's voice]

B. [OPTION TEXT — one sentence]
   Alignment: [CHAOTIC / LAWFUL / NEUTRAL] (internal only)
   Outcome (115-125 words): [Full outcome text]

C. [OPTION TEXT — one sentence]
   Alignment: [CHAOTIC / LAWFUL / NEUTRAL] (internal only)
   Outcome (115-125 words): [Full outcome text]
```

PERSISTENT STATE CHANGES:
```
Option A:
  Inventory: [Changes or NONE]
  NPC dispositions: [Which NPCs shift, direction, magnitude]
  Environmental flags: [What changes in the location]
  Emotional ledger: [Category entry — e.g., "ACTS OF COURAGE +1"]
  Alignment shift: [CHAOTIC / LAWFUL / NEUTRAL +1]

Option B: [Same format]
Option C: [Same format]
```

WORLD NOTICED SIGNAL:
```
Option A signal: "[In-world prose acknowledgment — 1-2 sentences in the author's voice, woven into the outcome narration. Not gamey. Not meta. The world noticing.]"
Option B signal: "[Same]"
Option C signal: "[Same]"
```

STORYGUARD MANIFEST (for freeform input at this node):
```
CANON BOUNDARIES: [What cannot be introduced at this moment — Layer 1 rules active here]
CHARACTER TRUTH: [How each present NPC would authentically react to unexpected input — Layer 3]
SCENE INTEGRITY: [Available objects from persistent state, character knowledge limits, emotional context — Layer 4]
FOLD-BACK PATH: [If freeform input is unsupported, what is the nearest safe outcome that preserves the player's intent while staying compatible with the session architecture? Name the specific outcome it folds toward and explain why.]
FREEFORM ALIGNMENT MAPPING: [How to classify freeform input — what kind of input maps to chaotic? lawful? neutral? What is the SPIRIT of each alignment at this specific moment?]
```

CHOICE TYPE CONFIRMATION:
- Branching: YES
- All three paths arrive at: [Name the next shared beat or divergence point]
- What this choice tracks going forward: [Branch dimension + alignment]
- Cross-session payoff: [Reference Phase 2 Cross-Session Payoff Plan if applicable]

---

TASK 2 — EMOTIONAL CHOICES (ESCALATION AND BREATH BEATS)

Write 4-6 emotional choices. For each:

```
EMOTIONAL CHOICE #[N]:
  Beat: [ESCALATION / BREATH / TWIST / RESOLUTION]
  Source moment: [Page/scene reference]
  Alignment order: [Randomized]

  NARRATIVE LEAD-IN (1-2 sentences): [In the author's voice]
  CHOICE QUESTION: [In second person]

  A. [OPTION — one sentence]
     Alignment: [internal only]
     Outcome (80-100 words): [In the author's voice]

  B. [OPTION]
     Alignment: [internal only]
     Outcome (80-100 words): [text]

  C. [OPTION]
     Alignment: [internal only]
     Outcome (80-100 words): [text]

  All paths arrive at: [Next shared moment]

  PERSISTENT STATE CHANGES:
    Option A: [NPC dispositions, emotional ledger, action history — lighter than branching]
    Option B: [Same]
    Option C: [Same]

  WORLD NOTICED SIGNAL (if state change is significant enough to warrant one):
    [Signal text or "NO SIGNAL — state change is minor"]
```

---

TASK 3 — BRANCHING CHOICE #2: THE MORAL-WEIGHT CHOICE (TWIST BEAT)

This is the stickiness target. The choice users talk about. No correct answer. Each option reflects a legitimate human value.

Source moment: [Page/scene reference]
Values in tension: [Name three — e.g., courage vs. patience vs. self-reliance]
What this choice tracks: [Branch dimension]
Alignment order: [Randomized]

NARRATIVE SETUP (3-4 sentences — highest emotional pressure in the session):
[In the author's voice. The player must feel the weight before the question arrives.]

CHOICE QUESTION:

```
A. [OPTION — reflects: VALUE NAME]
   Alignment: [internal only]
   Outcome (115-125 words): [Full text]

B. [OPTION — reflects: VALUE NAME]
   Alignment: [internal only]
   Outcome (115-125 words): [Full text]

C. [OPTION — reflects: VALUE NAME]
   Alignment: [internal only]
   Outcome (115-125 words): [Full text]
```

[PERSISTENT STATE CHANGES — same format as Task 1]
[WORLD NOTICED SIGNAL — same format as Task 1]
[STORYGUARD MANIFEST — same format as Task 1]

MORAL WEIGHT CONFIRMATION:
- Each option reflects a genuine value: [YES / NO]
- No option is objectively wrong: [YES / NO]
- Talkability test — would a friend ask what you chose? [YES / NO — if no, redesign]

---

TASK 4 — BRANCHING CHOICE #3: THE SESSION-END HOOK (RESOLUTION BEAT)

Forward-commitment device. Session ends immediately after the player chooses. They do not see the consequence.

Source moment: [Page/scene reference]
What each path opens in the next session: [Three different openings]
Alignment order: [Randomized]

NARRATIVE SETUP (2-3 sentences):
[The primary arc just resolved. The world opens wider.]

CHOICE QUESTION:

```
A. [OPTION]
   Alignment: [internal only]
   Next session opens: [One vivid sentence — tone, first image, first stakes]

B. [OPTION]
   Alignment: [internal only]
   Next session opens: [One vivid sentence]

C. [OPTION]
   Alignment: [internal only]
   Next session opens: [One vivid sentence]
```

[PERSISTENT STATE CHANGES]
[STORYGUARD MANIFEST]

SESSION-END CONFIRMATION:
- This choice does not resolve within the current session: [YES]
- User closes the session mid-decision: [YES]

---

TASK 5 — BRANCHING CHOICE #4 (ESCALATION OR TWIST BEAT)

The fourth branching choice. Placement determined by the beat map — typically in the Escalation beat (minutes 3-10) or early Twist (minutes 13-15).

[Same complete format as Task 1: narrative setup, choice question, A/B/C with alignment, outcomes, persistent state changes, World Noticed signals, StoryGuard manifest]

---

TASK 6 — POSTURE SHIFT FINALIZATION

Confirm and finalize all posture shifts from Phase 4 Task 4. For each:

```
POSTURE SHIFT #[N] — FINALIZED:
  Beat: [From Phase 4]
  Narrator line: [Confirmed or revised — must be in the author's voice]
  
  Response direction 1: "[Natural language response]"
    Narration adjustment: [The actual 2-3 sentences that follow, in the author's voice]
    State update: [player_style change]
  
  Response direction 2: "[Natural language response]"
    Narration adjustment: [2-3 sentences]
    State update: [player_style change]
  
  Response direction 3 (if applicable): "[Natural language response]"
    Narration adjustment: [2-3 sentences]
    State update: [player_style change]
```

---

TASK 7 — SCENE RULES POPULATION (StoryGuard Layer 4)

Using the StoryGuard Layer 4 template from Phase 2, populate it for every scene in this episode:

```
STORYGUARD LAYER 4 — SCENE RULES: SESSION [N]

SCENE 1 (SETUP):
  Available objects: [List from persistent state + scene defaults]
  Present NPCs: [List with current disposition levels]
  Character knowledge: [What each NPC knows at this point — no future knowledge allowed]
  Emotional context: [Active emotional ledger entries visible in this scene]
  Canon boundaries: [Layer 1 rules most likely to be tested here]
  Freeform risk areas: [What a creative player might try that would violate canon]

SCENE 2 (ESCALATION):
  [Same format]

... (every scene in the episode)
```

---

OUTPUT FORMAT — Return all seven tasks as a single Phase 5 document with each choice and posture shift clearly labeled.

INTERACTION COUNT VERIFICATION (must match Phase 4):
- Branching choices designed: [4]
- Emotional choices designed: [4-6]
- Posture shifts finalized: [6-10]
- StoryGuard manifests written: [4 — one per branching choice]
- World Noticed signals written: [Count]
- Scene rules populated: [Count — every scene]

TASK 8 — SOCIAL ECHO DEFINING LINES

For each BRANCHING choice, write the "defining line" that appears on the Social Echo share card if this choice is the most morally weighted choice of the session. One provocative sentence per path, in the author's voice. Not a summary. A gut punch.

```
DEFINING LINES — BRANCHING CHOICE #[N]:
  Path A: "[One provocative sentence — what the player did, rendered as a provocation that makes their friend ask 'Wait, what happened?']"
  Path B: "[Same]"
  Path C: "[Same]"
```

Rules for defining lines:
- Written in the author's voice (reference Voice Profile signature techniques)
- Must NOT spoil the story — evocative, not explanatory
- Must create curiosity in someone who has not played
- Must feel personal to the player who made the choice
- Maximum 20 words per line

---

OUTPUT FORMAT — Return all eight tasks as a single Phase 5 document with each choice, posture shift, and defining line clearly labeled.

INTERACTION COUNT VERIFICATION (must match Phase 4):
- Branching choices designed: [4]
- Emotional choices designed: [4-6]
- Posture shifts finalized: [6-10]
- StoryGuard manifests written: [4 — one per branching choice]
- World Noticed signals written: [Count]
- Scene rules populated: [Count — every scene]
- Defining lines written: [4 sets — one per branching choice, 3 lines each]

STOP. Before proceeding to Phase 6, verify: Does every branching choice have three genuinely different downstream effects? Does Choice #3 (moral-weight) have no correct answer? Does Choice #4 (session-end) close without resolution? Are all alignment mappings present but never visible to the player? Do all StoryGuard manifests include fold-back paths? Are all World Noticed signals in the author's voice and non-gamey? Are all defining lines in the author's voice and under 20 words? If any answer is no, revise before continuing.

---

## END OF DELIVERABLE 4
