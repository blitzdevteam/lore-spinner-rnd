# DELIVERABLE 2: UPDATED PHASE 2 — STORY SESSION MAP

**Lorespinner Pipeline Upgrade — May 2026**
**Type:** Updated pipeline phase (runs once per IP)
**Replaces:** Current Phase 2 prompt (Logic Companion pages 5-10)
**Implementation:** Swap the existing Phase 2 prompt with this one. Output payload is larger (3 new blocks added to existing output).

---

## WHAT STAYS FROM CURRENT PHASE 2

Everything the current Phase 2 produces is preserved:
- Task 1: Session Count and Allocation
- Task 2: Arc Progression
- Task 3: Major Branch Opportunities
- Task 4: Cross-Session Payoff Plan
- Task 5: Branch Dimension Definitions

These tasks remain UNCHANGED. The following three new tasks are APPENDED after Task 5.

---

## COPY-PASTE ADDITIONS BELOW THIS LINE

Append the following to the EXISTING Phase 2 prompt, after Task 5 (Branch Dimension Definitions) and before the OUTPUT FORMAT and STOP block. The existing output format should be expanded to include the three new task outputs.

---

TASK 6 — PERSISTENT STATE SCHEMA

Define ALL trackable world state for this IP. This is the master definition that the runtime reads and updates every turn. Nothing enters the persistent state at runtime that is not defined here. This schema is law.

A. OBJECT INVENTORY

List every findable, losable, usable, and breakable object in the story. Use the IP Trimming Agent's World Rules and the source text to identify every object that a player could interact with.

For each object:

```
OBJECT: [Name]
  Found: [Location/scene where it can be acquired]
  Function: [What it does — weapon, key, evidence, comfort object, trade item, etc.]
  Persistence: [CONSUMED on use / PERSISTENT until lost or broken / CONDITIONAL — explain]
  Forward references: [Which later scenes reference or use this object — list scene/chapter]
  State variations: [If applicable — broken, charged, empty, blood-stained, etc.]
```

If the IP Trimming Agent flagged objects in its Conversion Notes, include those objects here. Players who explore off-path may find objects that main-path players miss. Those objects must be in this schema.

B. NPC REGISTRY

List every named character the player can encounter. For each:

```
NPC: [Name]
  Role: [Protagonist ally / antagonist / neutral / ambient — one word]
  Default disposition toward protagonist: [Friendly / Wary / Hostile / Indifferent / Worshipful — one word + one sentence explaining why]
  Relationship axes:
    Trust: [Starting value: LOW / MEDIUM / HIGH] — [What raises it] / [What lowers it]
    Fear: [Starting value] — [Raises] / [Lowers]
    Respect: [Starting value] — [Raises] / [Lowers]
    Debt: [Starting value: NONE / OWES PLAYER / PLAYER OWES] — [What creates debt]
  Behavioral triggers:
    - If player [action], [NPC] responds by [specific behavioral change]
    - If player [action], [NPC] responds by [specific behavioral change]
    ... (3-5 triggers per major NPC, 1-2 per minor NPC)
  Dialogue tone by disposition:
    Friendly: [One sentence describing speech pattern at this level]
    Neutral: [One sentence]
    Hostile: [One sentence]
  Breakpoint: [The action that would cause this NPC to permanently change allegiance or refuse to interact — if applicable]
```

C. LOCATION REGISTRY

List every explorable location. For each:

```
LOCATION: [Name]
  Sensory signature:
    Visual: [One sentence — what it looks like]
    Sound: [One sentence — what the player hears]
    Smell/Temperature: [One sentence — ambient sensory detail]
  Environmental reactivity:
    - If player [action] here, [environmental response]
    - If player [action] here, [environmental response]
    ... (2-4 per location)
  Default occupants: [NPCs present when player arrives]
  Default objects: [Objects available when player arrives]
  Connected to: [Adjacent locations the player can move to from here]
  Discovery content: [If the IP Trimming Agent flagged explorable environment content for this location, reference it here]
```

D. EMOTIONAL LEDGER CATEGORIES

Define what emotional beats get tracked across the story. These are NOT individual events — they are CATEGORIES of behavior that accumulate over time.

```
EMOTIONAL LEDGER:

Category: ACTS OF MERCY
  Definition: [What counts as mercy in this world]
  Cumulative effect: [How the world shifts when this accumulates — specific]
  Threshold: [At N instances, trigger: specific world behavior change]

Category: ACTS OF CRUELTY
  Definition: [What counts as cruelty in this world]
  Cumulative effect: [Specific]
  Threshold: [At N instances, trigger: specific change]

Category: MOMENTS OF COURAGE
  Definition: [What counts]
  Cumulative effect: [Specific]
  Threshold: [Specific]

Category: MOMENTS OF COWARDICE
  Definition: [What counts]
  Cumulative effect: [Specific]
  Threshold: [Specific]

Category: BETRAYALS
  Definition: [What counts as betrayal in this world]
  Cumulative effect: [Specific]
  Threshold: [Specific]

Category: SACRIFICES
  Definition: [What counts]
  Cumulative effect: [Specific]
  Threshold: [Specific]

Category: LIES TOLD
  Definition: [What counts]
  Cumulative effect: [Specific]
  Threshold: [Specific]

Category: TRUTHS REVEALED
  Definition: [What counts]
  Cumulative effect: [Specific]
  Threshold: [Specific]
```

Add or remove categories based on the IP. Not every IP needs all eight. Some IPs may need categories not listed here (e.g., a heist story might track "RISKS TAKEN" and "PLANS FOLLOWED"). The categories must be AUTHENTIC to the story's moral landscape.

E. ACTION HISTORY CATEGORIES

Define what action types get logged for world-posture purposes:

```
ACTION HISTORY:

Type: VIOLENT ACTIONS
  Definition: [What counts in this world]
  World posture shift: [How the world treats a player who accumulates these — specific NPC behavior changes, environmental changes]

Type: DECEPTIVE ACTIONS
  Definition: [What counts]
  World posture shift: [Specific]

Type: PROTECTIVE ACTIONS
  Definition: [What counts]
  World posture shift: [Specific]

Type: DESTRUCTIVE ACTIONS
  Definition: [What counts]
  World posture shift: [Specific]

Type: CURIOUS ACTIONS
  Definition: [What counts — exploring, asking, investigating]
  World posture shift: [Specific — the world opens more, reveals more, rewards looking]
```

Adjust categories to the IP. A noir story might track "CORRUPT ACTIONS" and "HONEST ACTIONS." A sci-fi story might track "SYNTHETIC ACTIONS" and "ORGANIC ACTIONS." Match the world.

---

TASK 7 — WORLD REACTIVITY RULES

Define 5-8 behavioral trigger/response pairs that govern how THIS world reacts to the player's accumulated posture. These are not individual NPC reactions (those are in the NPC Registry). These are WORLD-LEVEL responses — the atmosphere, the environment, the ambient reality of the story shifting based on the kind of player this person is becoming.

Each rule must be:
- GROUNDED in the IP's world logic (not generic game design)
- SPECIFIC enough that the runtime narrator can execute it without interpretation
- AUTHENTIC to the author's world — the rule should feel like something the author would write, not something a game designer would impose

Format per rule:

```
WORLD REACTIVITY RULE [N]:

TRIGGER: "If the player [specific accumulated behavioral pattern], ..."
RESPONSE: "... the world responds by [specific reactive behavior authentic to this world]."
EVIDENCE: [Source passage or world rule that justifies this reaction]
INTENSITY SCALING: [How this reaction escalates with continued behavior — mild at first, pronounced later]
NARRATIVE EXECUTION: [One sentence instruction for how the narrator weaves this into prose — physical detail, not gamey language]
```

EXAMPLES (for reference only — generate rules specific to the IP being processed):

Anima Machina example: "If Nora consistently avoids emotional vulnerability, the technology around her starts glitching harder, as if the machines are trying to force the connection she will not make herself."

Alice example: "If Alice insists on logic, Wonderland answers with escalating nonsense; the more she demands sense, the more aggressively senseless everything becomes."

Horror IP example: "If the player repeatedly investigates danger instead of fleeing, the house begins leaving doors open. Things that were locked are now ajar. The house is inviting them deeper."

The rules must span the range of player behavior. Include at least:
- 1 rule for aggressive/chaotic players
- 1 rule for cautious/lawful players
- 1 rule for observant/neutral players
- 1 rule for emotionally vulnerable players
- 1 rule for emotionally closed players
- 2-3 rules specific to this world's unique reactive properties

---

TASK 8 — STORYGUARD CANON EXTRACTION

Extract and codify the four StoryGuard protection layers for this IP. These become the constitutional boundaries that the runtime narrator enforces. Nothing outside these boundaries enters the experience. Nothing inside them can be violated.

LAYER 1 — CANON RULES (What can and cannot exist)

Using the IP Trimming Agent's World Rules output (Task 2) and the source text, codify every boundary of the sandbox:

```
STORYGUARD LAYER 1 — CANON RULES: [TITLE]

WORLD PHYSICS:
- [Rule] — CANNOT BE VIOLATED
... (every physical law of this world)

TECHNOLOGY LEVEL:
- [What technology exists] — CONFIRMED IN SOURCE
- [What technology does NOT exist] — CANNOT BE INTRODUCED
... 

TIMELINE:
- [Key dates/periods/sequences] — FIXED
...

GEOGRAPHY:
- [Named locations and their relationships] — CONFIRMED
- [Locations that do NOT exist] — CANNOT BE INTRODUCED
...

CREATURES/ENTITIES:
- [What exists] — CONFIRMED
- [What does NOT exist in this world] — CANNOT APPEAR
...

SOCIAL RULES:
- [How power works, who is in charge, what is legal/illegal] — ESTABLISHED
...
```

LAYER 2 — STORY RULES (Narrative destinations that must be protected)

```
STORYGUARD LAYER 2 — STORY RULES: [TITLE]

MAIN NARRATIVE ARC: [One sentence — the story's spine that the player bends but cannot break]

IRREVERSIBLE PLOT POINTS (the player can influence HOW they happen, not WHETHER):
- [Plot point] — MUST OCCUR by [episode/session]
- [Plot point] — MUST OCCUR by [episode/session]
...

DRAMATIC DESTINATIONS (the story must eventually reach these emotional/narrative places):
- [Destination] — [Why it is essential to the story's meaning]
...

PROTECTED REVELATIONS (information the player cannot access before the story delivers it):
- [Revelation] — LOCKED until [episode/session/condition]
...
```

LAYER 3 — CHARACTER RULES (Behavioral boundaries per character)

For each major character:

```
STORYGUARD LAYER 3 — CHARACTER RULES: [CHARACTER NAME]

BACKSTORY: [2-3 sentences — the facts about this character's past that cannot change]
CORE MOTIVATION: [One sentence — what drives them]
RELATIONSHIP TO PROTAGONIST: [One sentence — the truth of this relationship]
BEHAVIORAL BOUNDARIES:
  - [Character] would NEVER [action] because [reason from source]
  - [Character] would NEVER [action] because [reason from source]
  ... (3-5 absolute boundaries per major character)
WHAT WOULD BREAK THIS CHARACTER: [The action or revelation that would shatter their established truth — if it exists in the story, it is a protected dramatic destination. If it does not exist, it cannot be manufactured.]
```

LAYER 4 — SCENE RULES TEMPLATE

This layer is a TEMPLATE that gets populated per episode by Phase 5 (Choice Design). Define the template structure here. Phase 5 fills it for each episode.

```
STORYGUARD LAYER 4 — SCENE RULES TEMPLATE

For each episode/session, Phase 5 must populate:

SCENE [N]:
  Available objects: [From Persistent State — what the player can interact with HERE]
  Character knowledge: [What each NPC in this scene knows at this point — no future knowledge]
  Emotional context: [What emotional ledger entries are active and visible in this scene]
  Canon boundaries active: [Which Layer 1 rules are most relevant to enforce in this scene]
  Freeform risk areas: [What a freeform player input might try to introduce that would violate canon HERE]
```

---

UPDATED OUTPUT FORMAT

The Phase 2 output now includes the original 5 tasks PLUS the 3 new tasks:

```
PHASE 2 OUTPUT: STORY SESSION MAP — [TITLE]

[Tasks 1-5: Existing output format — unchanged]

TASK 6: PERSISTENT STATE SCHEMA
  A. Object Inventory
  B. NPC Registry
  C. Location Registry
  D. Emotional Ledger Categories
  E. Action History Categories

TASK 7: WORLD REACTIVITY RULES
  [5-8 rules with triggers, responses, evidence, scaling, narrative execution]

TASK 8: STORYGUARD CANON EXTRACTION
  Layer 1: Canon Rules
  Layer 2: Story Rules
  Layer 3: Character Rules (per character)
  Layer 4: Scene Rules Template
```

TASK 9 — STORY-NATIVE ALIGNMENT LABELS

The player's alignment tendency (chaotic/lawful/neutral) is tracked internally but NEVER shown to the player using those words. Instead, each IP uses story-native language that feels like it belongs in the world.

Define the alignment label mapping for this IP:

```
ALIGNMENT LABELS: [TITLE]

Chaotic-dominant → "[STORY_NATIVE_LABEL]"
  Description: "[One sentence — what this type of player does in this world]"
  Visual association: "[Color or visual motif for share card spectrum]"

Lawful-dominant → "[STORY_NATIVE_LABEL]"
  Description: "[One sentence]"
  Visual association: "[Color or visual motif]"

Neutral-dominant → "[STORY_NATIVE_LABEL]"
  Description: "[One sentence]"
  Visual association: "[Color or visual motif]"

Mixed (no clear dominant) → "[STORY_NATIVE_LABEL]"
  Description: "[One sentence]"
  Visual association: "[Color or visual motif]"
```

Rules for alignment labels:
- Labels must feel native to the story's world, not borrowed from RPG terminology
- Labels should be evocative and aspirational — a player should WANT to be any of these
- No label should feel like punishment or failure
- Labels feed the Social Echo Layer (Deliverable 9) for share cards and alignment profiles

---

UPDATED OUTPUT FORMAT

The Phase 2 output now includes the original 5 tasks PLUS 4 new tasks:

```
PHASE 2 OUTPUT: STORY SESSION MAP — [TITLE]

[Tasks 1-5: Existing output format — unchanged]

TASK 6: PERSISTENT STATE SCHEMA
TASK 7: WORLD REACTIVITY RULES
TASK 8: STORYGUARD CANON EXTRACTION
TASK 9: STORY-NATIVE ALIGNMENT LABELS
```

STOP. Before proceeding to Phase 3, verify the new blocks: Does the Persistent State Schema account for every object, NPC, and location in the source? Do the World Reactivity Rules span the full range of player behavior? Do the StoryGuard layers cover every boundary the runtime will need to enforce? Does the NPC Registry include behavioral triggers that the runtime can execute without interpretation? If any answer is no, revise before continuing.

---

## END OF DELIVERABLE 2
