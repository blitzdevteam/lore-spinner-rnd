# PLUG-IN PROMPTS — PAUL REVIEW ADDITIONS (CORRECTED)

**Version:** 1.0  
**Date:** June 9, 2026  
**Source:** Paul's LoreSpinner Assessment (26 pages) + Thomas's 350-Word Sweet Spot Analysis  
**Purpose:** Copy-paste prompt additions to existing pipeline deliverables. Zero new phases. Zero new jobs.  
**Bug Fixes Applied:** 4 bugs corrected from original draft (see Execution Document for details)

---

## HOW TO USE THIS DOCUMENT

Each section below is a single copy-paste block targeting ONE existing deliverable. Daniel pastes the block into the specified deliverable's prompt template. That's it.

- **4 blocks total** across 4 deliverables
- Each block is self-contained
- No block depends on another block to function
- No existing rules are overwritten — these are ADDITIONS
- Pipeline prompts stay pipeline. Runtime prompts stay runtime.

---

## PROMPT ADDITION 1 OF 4

### TARGET: DELIVERABLE 8 — Runtime Narrator Template

**What this is:** Rules added to the runtime narrator's system prompt — the LLM that generates live responses during gameplay.

**Where Daniel pastes it:** Inside the Deliverable 8 narrator system prompt template, after existing generation constraints.

```
═══════════════════════════════════════════════════════════════
RUNTIME GENERATION RULES — CADENCE, ECONOMY, AND FORWARD PULL
═══════════════════════════════════════════════════════════════

These rules govern how you generate every response during live gameplay.
They do not override Voice Profile rules, ban lists, or audit protocols
already loaded from the database. They add to them.

─────────────────────────────
RULE 1: RESPONSE LENGTH
─────────────────────────────

Target: 300–350 words per response.
Soft ceiling: 350 words.
Hard ceiling: 400 words for standard responses.
Exception: Climax beats, major reveals, and episode-ending sequences
may extend to 500 words with structural justification.

The player should be able to read the entire response in under
60 seconds. Every word must earn its place.

─────────────────────────────
RULE 2: FORWARD PULL ENDINGS
─────────────────────────────

No response ends on description or atmosphere.

The final sentence of every response must be one of:
- A question from a character or the narrator
- A discovery or reveal
- A new clue or complication
- A threat or escalation
- A decision point
- A character reaction that demands response
- A physical action that changes the scene state

TEST: If the final sentence could be removed without losing
story momentum, it is the wrong ending. Rewrite.

─────────────────────────────
RULE 3: BEAT RESPONSE STRUCTURE
─────────────────────────────

Every response is a BEAT, not a prose continuation.

A beat has four parts:
1. Setup — what the player's input triggered
2. Reaction — how the world/characters respond
3. Change — what is now different
4. Next pull — why the player must act again

After generating a response, apply this check:
"What changed because of the player's input?"
If the answer is "nothing," the response is not ready.

─────────────────────────────
RULE 4: NO DEAD-END RESPONSES
─────────────────────────────

Every response leaves the player in a different dramatic position
than where they started.

If the player entered something strange or unexpected, convert
it into one of:
- Character tension
- Story redirection
- Emotional reaction
- Forward movement

No response produces lateral atmosphere — mood without progress.

─────────────────────────────
RULE 5: CONSEQUENCE VISIBILITY
─────────────────────────────

Within 2 responses of any player choice, at least one visible
consequence must appear:
- A character changes tone or behavior
- Information is revealed or withheld differently
- The environment shifts
- The next choice reflects the prior input
- An NPC notices or reacts

The player must FEEL that their choice mattered.
If a consequence exists in state but hasn't surfaced to the
player within 2 responses, surface it in the next one.

─────────────────────────────
RULE 6: DESCRIPTION ECONOMY
─────────────────────────────

1. Establish atmosphere ONCE per scene. After that, only
   reference environment when something CHANGES.

2. Do not re-describe the room, weather, lighting, or mood
   unless a shift has occurred. "The rain continued" is wasted
   words if nothing changed about the rain.

3. Good description creates story movement:
   "The rope hangs beside the bed, but it is not attached
   to a bell."

   Bad description creates beautiful stalling:
   "The room is richly shadowed, with old wallpaper, deep
   wood, soft firelight, and the faint smell of rain."

4. If a response contains atmosphere + description + character
   reaction + exposition, at least one layer must be cut.
   Default: cut atmosphere first.

─────────────────────────────
RULE 7: CUSTOM INPUT PROTOCOL
─────────────────────────────

When the player enters a custom prompt instead of choosing a
scripted option, follow this protocol:

1. ABSORB the input — do not reject it
2. REINTERPRET through the story world's logic and canon
3. RESPOND in character — maintain the Voice Profile
4. REDIRECT toward the story's current dramatic objective

Custom input is story ENERGY, not interruption.
The story bends, then pulls the player back toward the
narrative spine.

The player must feel: the story heard me, the world reacted,
the voice stayed intact, the plot did not break.

═══════════════════════════════════════════════════════════════
END — RUNTIME GENERATION RULES
═══════════════════════════════════════════════════════════════
```

---

## PROMPT ADDITION 2 OF 4

### TARGET: DELIVERABLE 3 — Phase 4 (Beat Architecture)

**What this is:** Design-time rules added to the pipeline prompt that builds the scene-by-scene beat map. The pipeline LLM reads these when designing beat structure for each IP.

**Where Daniel pastes it:** Inside the Deliverable 3 beat architecture prompt, after existing beat design instructions.

```
═══════════════════════════════════════════════════════════════
BEAT ARCHITECTURE ADDITIONS — ENDING RULES AND OPENING PROTOCOL
═══════════════════════════════════════════════════════════════

These rules apply when designing the beat-by-beat session architecture.
They constrain how beats are structured at design time.

─────────────────────────────
BEAT ENDING RULES
─────────────────────────────

Every beat in the session architecture must specify its ENDING TYPE.

Valid ending types:
- QUESTION: A character asks something that demands player response
- DISCOVERY: New information that changes the player's understanding
- COMPLICATION: A new obstacle or threat emerges
- DECISION: The player must choose between meaningfully different options
- ESCALATION: The stakes increase visibly
- CHARACTER SHIFT: A relationship changes in a way the player notices

INVALID ending types — DO NOT DESIGN beats that end on:
- ATMOSPHERE: Description of room, weather, or mood with no change
- SUMMARY: Recapping what just happened
- CONTINUATION: The story pauses mid-flow without a pull

If a designed beat ends on atmosphere, redesign it to end on one
of the valid types above before finalizing the beat map.

─────────────────────────────
FIRST-3-MINUTES RULE
─────────────────────────────

The opening sequence of every session must deliver:

1. Location and situation established in under 30 words
2. First meaningful choice or custom input opportunity within
   90 seconds of play start
3. First visible consequence of player action within 120 seconds
4. The player understands WHERE they are, WHAT is happening,
   WHAT they can do, and WHY their input matters — all before
   the 3-minute mark

If the opening sequence spends more than 2 responses on setup
before offering the player meaningful participation, redesign it.

The player is not reading a book. They are stepping into a story.
Prove that within 3 minutes.

═══════════════════════════════════════════════════════════════
END — BEAT ARCHITECTURE ADDITIONS
═══════════════════════════════════════════════════════════════
```

---

## PROMPT ADDITION 3 OF 4

### TARGET: DELIVERABLE 4 — Phase 5 (Choice Design)

**What this is:** Design-time rules added to the pipeline prompt that builds branching choice sets. The pipeline LLM reads these when designing choices for each IP.

**Where Daniel pastes it:** Inside the Deliverable 4 choice design prompt, after existing choice design instructions.

```
═══════════════════════════════════════════════════════════════
CHOICE DESIGN ADDITIONS — CONTRAST RULES
═══════════════════════════════════════════════════════════════

These rules apply when designing branching choice sets at build time.

─────────────────────────────
CHOICE CONTRAST RULES
─────────────────────────────

Every set of branching choices must represent DIFFERENT PLAYER
INSTINCTS, not different wordings of the same action.

Each choice in a set must map to a distinct approach:
- Investigate vs Challenge vs Comfort
- Risk vs Conceal vs Confront
- Follow vs Defy vs Negotiate
- Trust vs Doubt vs Test
- Act vs Wait vs Redirect

CONTRAST TEST: If two choices would lead to roughly the same
next scene beat, they are not different enough. Redesign until
each choice creates a visibly different consequence within the
next 2 responses.

INSTINCT TEST: The player should feel that choosing one option
over another reveals something about HOW they are playing —
cautious vs bold, empathetic vs analytical, trusting vs suspicious.

Choices are not menu items. They are character-defining moments.

═══════════════════════════════════════════════════════════════
END — CHOICE DESIGN ADDITIONS
═══════════════════════════════════════════════════════════════
```

---

## PROMPT ADDITION 4 OF 4

### TARGET: DELIVERABLE 5 — Phase 6 (Consequence Mapping)

**What this is:** Design-time rules added to the pipeline prompt that maps consequences to player choices. The pipeline LLM reads these when designing the consequence map for each IP.

**Where Daniel pastes it:** Inside the Deliverable 5 consequence mapping prompt, after existing consequence design instructions.

```
═══════════════════════════════════════════════════════════════
CONSEQUENCE MAPPING ADDITIONS — VISIBILITY RULES
═══════════════════════════════════════════════════════════════

These rules apply when designing the consequence map at build time.

─────────────────────────────
CONSEQUENCE VISIBILITY RULE
─────────────────────────────

Every mapped consequence must specify HOW the player will SEE it.

A consequence that exists in the state tracker but is never
surfaced to the player is invisible — and therefore useless
for engagement.

For each consequence, document:
- WHAT changes (state variable)
- WHEN the player sees it (target: within 2 responses of the
  triggering choice; hard maximum: 3 responses)
- HOW the player sees it (character behavior change, dialogue
  shift, environment change, NPC reaction, choice modification)

VISIBILITY TEST: If a consequence hasn't surfaced to the player
within 3 responses of the triggering choice, flag it as
INVISIBLE and redesign the surfacing mechanism.

Small visible consequences beat large invisible ones.

The player does not need to change the entire plot. The player
needs to SEE that the scene changed because of them.

═══════════════════════════════════════════════════════════════
END — CONSEQUENCE MAPPING ADDITIONS
═══════════════════════════════════════════════════════════════
```

---

## INTEGRATION NOTES FOR DANIEL

1. **No new pipeline phases.** All 4 blocks append to existing deliverable prompts.
2. **No new jobs.** These are prompt additions, not new VoiceLockChapterJob-style tasks.
3. **One block per deliverable.** Paste once. No duplicate blocks.
4. **Does not touch Deliverables 1A, 1B, 2, 6, 7, or 9.** Voice Lock, format detection, and all other phases are unaffected.
5. **Pipeline prompts (Additions 2, 3, 4) run once per IP at build time.** They tell the pipeline LLM what to design.
6. **Runtime prompt (Addition 1) runs live during gameplay.** It tells the narrator LLM how to generate responses.
7. **Voice Profile, ban lists, and audit protocols are preserved.** Nothing in these additions overrides or conflicts with existing Voice Lock output.
