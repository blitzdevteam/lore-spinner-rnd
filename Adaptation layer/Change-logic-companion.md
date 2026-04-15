# Lorespinner — Adaptation Layer Prompt Companion

This document accompanies the change logic file.

It contains the prompt system itself in implementation-ready reference form so the adaptation layer can be built against the real prompt source, not a summary.

---

# LORESPINNER INTERACTIVE ADAPTATION PROMPT SYSTEM

Phase-by-Phase AI Prompts for Story Onboarding
Version 1.1 — April 2026
Run one phase at a time. Review output before advancing. Each prompt is self-contained and copy-pasteable.

## HOW TO USE THIS SYSTEM

This prompt chain operates at **two execution tiers**:

### Story-wide prompts (run once per IP)

These prompts run **once** when a new IP is onboarded. They analyze the whole source and produce a structural roadmap that all subsequent per-session work builds against:

1. **Format Detection** — identify source type, protagonist, estimated session count
2. **Phase 1 — IP Audit** — score viability on six criteria
3. **Phase 2 — Story Session Map** — allocate events to sessions, plan arcs and cross-session payoffs

### Per-session prompts (run once per planned session)

These prompts run **once for each session** defined in the Story Session Map. They fully design one playable session at a time:

4. **Phase 3 — Entry Point Diagnosis** — find the cut point and write the cold open for this session
5. **Phase 4 — Session Beat Architecture** — map this session’s source material onto the five-beat arc
6. **Phase 5 — Choice Design** — write all branching and expressive choices for this session
7. **Phase 6 — Downstream Consequence Mapping** — map this session’s branching choices forward
8. **Phase 7 — Session Close and Retention Hook** — design this session’s ending and handoff
9. **Phase 8 — Editorial Verification** — run the quality gate for this session

The per-session chain is designed so Session 1 serves as the worked example, but every session goes through the same phases. When designing Session 2+, reference the Story Session Map for which events to cover, and the previous session’s outputs for continuity.

Before running any phase prompt, run the Format Detection block below — once, at the start of every new IP onboarding.

For source texts up to 400 pages: Each prompt includes explicit page-range instructions so the AI reads strategically rather than exhaustively. You do not need to feed all 400 pages at once. Follow the “WHAT TO UPLOAD” instruction in each phase.

Do not skip phases. Phase outputs are cumulative. Each phase builds on the last.

## FORMAT DETECTION — RUN FIRST

Paste this before the source text. Run once. Save the output. Reference it in every subsequent phase.

### LORESPINNER FORMAT DETECTION

You are a story analyst onboarding a new IP for Lorespinner, an interactive narrative platform. Before any adaptation work begins, identify the source format so the correct adaptation rules apply throughout.

Read the first 5 pages of the attached source text and answer the following:

### STEP 1 — IDENTIFY FORMAT

Screenplay indicators (if three or more are present, classify as SCREENPLAY):

* Scene headings in the form INT./EXT. [LOCATION] — [DAY/NIGHT]
* Character names in ALL CAPS centered above dialogue
* Action lines written in sparse present tense
* Parentheticals in (brackets) below character names
* Transitions: FADE IN, CUT TO, SMASH CUT, etc.
* Page format approximately 1 minute per page

Novel/prose indicators (if three or more are present, classify as NOVEL):

* Extended paragraphs of continuous prose
* Dialogue embedded within prose paragraphs using quotation marks
* Past tense narration with a clear narrator voice
* Chapter headings (Chapter 1, Part One, etc.)
* Interior monologue and psychological description
* No scene sluglines

### OUTPUT FORMAT — Return exactly this block:

---

FORMAT DETECTED: [SCREENPLAY / NOVEL]
EVIDENCE: [Two sentences citing specific formatting features found on page 1]
NARRATIVE TENSE: [First person / Third person limited / Third person omniscient / Second person / Screenplay present]
PROTAGONIST NAME: [Name as it appears in source]
GENRE SIGNALS: [List 2-3 genre markers visible in first 5 pages]
ESTIMATED READING TIME PER PAGE: [Prose average ~2 min/page. Screenplay average ~1 min/page]
TOTAL ESTIMATED SOURCE DURATION: [Pages x reading time]
LORESPINNER SESSION COVERAGE: [At approximately 40-60 source pages per session, this IP supports an estimated X sessions]
-------------------------------------------------------------------------------------------------------------------------

STOP. Do not begin any adaptation work. This output is a reference document only.

## MASTER CONTEXT BLOCK

Paste this at the top of every phase prompt, before the phase-specific instructions. It sets the AI’s role and loads Lorespinner system context.

### LORESPINNER MASTER CONTEXT

You are a senior interactive narrative designer working for Lorespinner, a platform that converts passive stories — novels, screenplays, TV pilots — into playable, replayable interactive sessions. Your job is story surgery, not story summary.

The Lorespinner system operates on four immovable laws:

1. THE ENTRY POINT IS ALWAYS A CUT, NOT AN ADDITION. Find the first moment of genuine dramatic tension and start there. Cut everything before it.
2. EXPRESSIVE CHOICES ARE THE TEXTURE. BRANCHING CHOICES ARE THE ARCHITECTURE. Three branching choices per session. Expressive choices between them. Never confuse the two.
3. DOWNSTREAM CONSEQUENCES MUST BE PLANNED BEFORE THE CHOICE IS WRITTEN. Design the consequence. Then design the choice that earns it.
4. THE SESSION-END CHOICE IS THE RETENTION MECHANISM. The session ends on the user's own unresolved decision, not a plot cliffhanger.

Session structure (20-30 minutes per session):

* SETUP beat (0-3 min): Cold open. First branching choice arrives within 300 words.
* ESCALATION beat (3-10 min): Stakes compound. Goal visible, obstacle clear.
* BREATH beat (8-10 min): Deliberate release. Humor, absurdity, or wonder. The Schell Rule: a reward must land between minutes 8 and 10.
* TWIST beat (10-17 min): Moral-weight choice. No correct answer.
* RESOLUTION beat (17-22 min): Primary arc resolves. Session-end hook choice plants the next session.

Source format detected: [PASTE FORMAT DETECTION OUTPUT HERE]
Current phase: [PASTE CURRENT PHASE NAME AND NUMBER]

## PHASE 1 PROMPT — IP AUDIT

Purpose: Score the IP on six criteria before any adaptation work begins. Determines viability and flags where editorial effort will be highest.

WHAT TO UPLOAD: Pages 1–30, pages [MIDPOINT–30], and final 20 pages of the source. For a 400-page novel, this means pages 1–30, 185–215, and 380–400. For a screenplay, use scene count rather than page count if easier.

### LORESPINNER — PHASE 1: IP AUDIT

[PASTE MASTER CONTEXT BLOCK HERE]

SOURCE TEXT UPLOADED: [TITLE], [AUTHOR/WRITER], [YEAR], [FORMAT]
PAGES PROVIDED: Opening section / Midpoint section / Closing section

You have three sections of the source IP. Using only what is present in these sections — do not assume or infer content from cultural knowledge of this story — complete the following IP Audit.

---

TASK: Score each of the six criteria on a scale of 1 to 3. Provide two to four sentences of specific evidence for each score. Quote from the source where possible.

CRITERION 1 — LICENSING FRICTION (Score 1-3)
3 = Public domain. No restrictions.
2 = Licensed IP with defined adaptation rights. Specific content restrictions known.
1 = Licensed IP with unclear or contested adaptation rights. Gatekeeping likely.
Evidence from text: [What in the source — copyright notice, edition information, adaptation notes — informs this score?]

CRITERION 2 — LATENT CHOICE ARCHITECTURE (Score 1-3)
3 = The source already contains natural decision points, threshold moments, and characters who present options. Structure is already branching in spirit.
2 = Some natural forks exist but require significant editorial invention to surface.
1 = Linear, single-path narrative with no natural decision points. High invention cost.
Evidence from text: [Identify 2-3 specific moments in the provided pages where a character faces a choice, threshold, or fork. Quote the source.]

CRITERION 3 — BOUNDED AGENCY SCORE (Score 1-3)
3 = Protagonist has an immovable core trait (curiosity, courage, a specific moral code). User choices shape how this trait expresses, not whether it exists.
2 = Protagonist's identity is clear but partially dependent on plot events to be established.
1 = Protagonist's identity IS the arc. Changing choices risks undermining the character entirely.
Evidence from text: [Name the protagonist's core trait. Quote one passage that demonstrates this trait as fixed, not chosen.]

CRITERION 4 — EMOTIONAL RANGE (Score 1-3)
3 = At least four distinct emotional registers present: e.g. wonder, fear, humor, frustration, moral confrontation, grief, triumph.
2 = Two to three emotional registers. Sessions will require tonal invention to avoid flatness.
1 = One dominant emotional tone. High risk of flat sessions.
Evidence from text: [Map the emotional registers present in the three provided sections. Be specific — name the emotion and the passage that generates it.]

CRITERION 5 — RECOGNIZABILITY COEFFICIENT (Score 1-3)
3 = The IP is widely known. Users arrive emotionally invested. Zero onboarding cost.
2 = Niche or specialist recognition. First session must establish world and stakes simultaneously.
1 = Unknown IP. First session must do full world-building AND create stakes from scratch. Budget session length accordingly.
Evidence from text: [Note any signals in the text — references, cultural touchstones, setting familiarity — that inform recognizability.]

CRITERION 6 — REPLAYABILITY HOOK (Score 1-3)
3 = A natural world metaphor for branching is built into the IP. Users intuitively understand that different choices produce different experiences.
2 = Replayability must be manufactured — it does not arise naturally from the world.
1 = The world or protagonist actively resists the idea of multiple paths. High concept friction.
Evidence from text: [What in the source world — physical spaces, recurring thresholds, character relationships — naturally supports the idea of different paths?]

---

OUTPUT FORMAT — Return exactly this scorecard:

IP AUDIT SCORECARD: [TITLE]
Audited by: Lorespinner Phase 1 System
Pages reviewed: [LIST RANGES]

| Criterion                   | Score (1-3) | Summary |
| --------------------------- | ----------- | ------- |
| Licensing Friction          |             |         |
| Latent Choice Architecture  |             |         |
| Bounded Agency Score        |             |         |
| Emotional Range             |             |         |
| Recognizability Coefficient |             |         |
| Replayability Hook          |             |         |
| TOTAL                       | /18         |         |

VERDICT:

* 15-18: GREEN LIGHT. Proceed to Phase 2.
* 10-14: AMBER. Flag the lowest-scoring criterion and propose one editorial mitigation before proceeding.
* Below 10: RED. Do not proceed. Return to IP selection with the following specific concerns: [LIST].

LOWEST-SCORING CRITERION: [NAME]
EDITORIAL MITIGATION REQUIRED: [One paragraph on how to address the weakest criterion before Phase 2 begins]

STOP. Do not begin Phase 2 until this scorecard has been reviewed and approved.

## PHASE 2 PROMPT — STORY SESSION MAP (story-wide)

Purpose: Using the extraction artifacts (chapters, events, objectives, attributes) plus the Format Detection and IP Audit, build a structural plan for the entire story. This phase runs once per IP and produces the roadmap that every per-session phase works against.

WHAT TO UPLOAD: Phase 1 IP Audit output. Plus the complete list of extracted chapters and events with their titles, positions, and objectives. You do not need the full source prose for this phase — the extraction artifacts are the primary input.

### LORESPINNER — PHASE 2: STORY SESSION MAP

[PASTE MASTER CONTEXT BLOCK HERE]

PHASE 1 AUDIT: [PASTE SCORECARD]
FORMAT DETECTION: [PASTE FORMAT DETECTION OUTPUT]
EXTRACTED CHAPTERS: [PASTE CHAPTER LIST WITH POSITIONS AND TITLES]
EXTRACTED EVENTS: [PASTE EVENT LIST WITH POSITIONS, TITLES, AND OBJECTIVES]
ESTIMATED SESSION COUNT FROM FORMAT DETECTION: [NUMBER]

You have the full structural extraction of the source IP. Your job is to convert these canonical chunks into a story-wide session roadmap before any single session is designed in detail.

---

TASK 1 — SESSION COUNT AND ALLOCATION

Using the estimated session count from Format Detection as a starting point, confirm or revise the session count based on the actual event structure. Then allocate events to sessions.

Rules:
* Each session covers a natural dramatic arc (rising tension, climax/choice, resolution)
* Session boundaries should fall at natural breaks — chapter boundaries, location shifts, or time jumps
* No session should contain fewer than 5 events or more than 20
* The first session must open with the highest-energy material (entry point diagnosis will refine this later)

SESSION ALLOCATION TABLE:

| Session | Chapter(s) covered | Event range (positions) | Primary dramatic question | Emotional register |
| ------- | ------------------ | ----------------------- | ------------------------- | ------------------ |
| 1       |                    |                         |                           |                    |
| 2       |                    |                         |                           |                    |
| ...     |                    |                         |                           |                    |

CONFIRMED SESSION COUNT: [NUMBER]

---

TASK 2 — ARC PROGRESSION

For each session, name:
* The primary dramatic question (what the player is working toward)
* The emotional register shift from the previous session (what changes in tone/stakes)
* The key transition moment — what the previous session’s close plants that this session opens with

ARC PROGRESSION:

| Session | Opens with (seed from previous close)   | Primary dramatic question | Emotional register shift  |
| ------- | --------------------------------------- | ------------------------- | ------------------------- |
| 1       | [N/A — first session]                   |                           |                           |
| 2       | [What Session 1’s close plants]         |                           |                           |
| ...     |                                         |                           |                           |

---

TASK 3 — MAJOR BRANCH OPPORTUNITIES

Identify the 2–3 strongest branching choice opportunities per session. These are moments in the extracted events where a character faces a natural decision, threshold, or fork. Reference specific event positions.

For each opportunity, name:
* The event position and title
* What dimension this choice would track (e.g. “approach to authority”, “trust vs. self-reliance”)
* Whether this branch has natural downstream payoff in a later session (name the session and the payoff event)

BRANCH OPPORTUNITIES:

| Session | Event position | Event title | Choice dimension | Downstream payoff session | Payoff event |
| ------- | -------------- | ----------- | ---------------- | ------------------------- | ------------ |
|         |                |             |                  |                           |              |

---

TASK 4 — CROSS-SESSION PAYOFF PLAN

Map the highest-value branching choices from early sessions to their payoff moments in later sessions. This ensures that when individual sessions are designed, they know which earlier choices they must honor.

| Choice (session + event) | What it tracks | Session where it echoes | Session where it pays off | Payoff description |
| ------------------------ | -------------- | ----------------------- | ------------------------- | ------------------ |
|                          |                |                         |                           |                    |

---

TASK 5 — BRANCH DIMENSION DEFINITIONS (STORY-LEVEL)

Using the branch opportunities identified in Task 3, define the **core branch dimensions of the story**. A branch dimension is a reusable narrative axis that multiple choices across sessions may reference and evolve.

These dimensions are the canonical vocabulary that Phase 5 (choice design), Phase 6 (consequence mapping), and runtime branch resolution all operate against. Define them here so the entire system shares a stable, consistent set of axes.

Rules:
* Each dimension is a tension or axis, not a single trait (e.g. `trust_vs_caution`, not just `trust`)
* A story should have 3–6 core dimensions — enough for meaningful replayability, few enough to track cleanly
* Each dimension must connect to at least one branch opportunity from Task 3
* Dimensions defined here are canonical — Phase 5 must reference them when designing branching choices. If a session requires a dimension not anticipated here, Phase 5 may declare a new one, but it must be flagged as an addition.

BRANCH DIMENSION TABLE:

| Dimension name         | Description (one sentence)                                      | First appears in session | Connected branch opportunity (from Task 3) |
| ---------------------- | --------------------------------------------------------------- | ------------------------ | ------------------------------------------ |
| (e.g. trust_vs_caution) | How the protagonist approaches uncertainty in others            |                          |                                            |
|                        |                                                                 |                          |                                            |

OUTPUT FORMAT:

```json
"branch_dimensions": [
  {
    "dimension_name": "trust_vs_caution",
    "description": "How the protagonist approaches uncertainty in others"
  }
]
```

---

OUTPUT FORMAT — Return all five tasks as a single Phase 2 document.

STOP. The Story Session Map is the structural backbone of the entire adaptation. Before designing any individual session, verify: Does every session have a primary dramatic question? Are there at least 2 branching opportunities per session? Does the cross-session payoff plan connect at least 2 early choices to later sessions? Are there at least 3 defined branch dimensions? If any answer is no, revise before continuing.

## PHASE 3 PROMPT — ENTRY POINT DIAGNOSIS (per-session)

Purpose: Identify the precise cut point — the first moment of genuine dramatic tension — and write the cold open for this session in Lorespinner’s second-person voice. For Session 1, this finds the dramatic beginning of the story. For later sessions, this determines how the session opens based on the previous session’s close and the Story Session Map.

WHAT TO UPLOAD: The source pages allocated to this session by the Story Session Map. For Session 1, this is typically pages 1–40 or from FADE IN through the first major scene of conflict. For later sessions, upload the source pages covering the session's event range.

### LORESPINNER — PHASE 3: ENTRY POINT DIAGNOSIS

[PASTE MASTER CONTEXT BLOCK HERE]

STORY SESSION MAP: [PASTE PHASE 2 OUTPUT — specifically this session's event allocation]
PHASE 1 AUDIT RESULT: [PASTE PHASE 1 SCORECARD HERE]
SOURCE PAGES PROVIDED: [Pages allocated to this session by the Story Session Map]

The dropout window on Lorespinner is the first 60 seconds. Any passive setup that runs inside that window is a retention risk. Your job in this phase is to find where the dramatic energy actually lives and cut everything before it.

---

TASK 1 — DIAGNOSE THE OPENING

Read the provided pages. Apply the following test to each paragraph sequentially, starting at page 1:

CAN A NEW USER FEEL THE STAKES WITHIN 60 SECONDS OF THIS MOMENT?
(Based only on what is currently on the page — not future knowledge of the story.)

Continue reading forward until the answer becomes YES. The paragraph where the answer first becomes YES is your cut point.

Return the following diagnosis block:

EDITORIAL DIAGNOSIS:
"This opening is [X] words of [passive observation / world-building / character description / backstory] before anything of dramatic consequence occurs. The protagonist is [describe the passive state]. There is no dramatic tension, no emotional promise, and no invitation for the user to act.

The real dramatic energy of [TITLE] does not begin here. It begins at [QUOTE THE EXACT SENTENCE OR ACTION LINE WHERE STAKES FIRST APPEAR]. At this moment, [explain in one sentence why this is the real beginning — what is at risk, what the user now wants to know].

For Lorespinner, [X words / X pages / X scenes] of the original opening are cut entirely. The session gains everything. The reader loses nothing they need."

---

TASK 2 — FORMAT-SPECIFIC CUT RULES

If source is NOVEL:

* Identify the chapter and paragraph of the cut point
* Note the original word count before the cut
* Identify whether the cut eliminates: backstory / setting description / internal monologue / other
* Flag any crucial world-building that was cut and must be re-introduced through action in the cold open

If source is SCREENPLAY:

* Identify the scene heading (INT./EXT.) of the cut point
* Note the original scene count before the cut
* Identify whether the cut eliminates: teaser / act one setup / cold open montage / other
* Flag any crucial character establishment that was cut and must be re-introduced through action or dialogue in the session cold open

---

TASK 3 — WRITE THE COLD OPEN

Using the cut point as your START, write the Lorespinner cold open for this session. This is the actual prose the user reads when the session begins.

COLD OPEN RULES (all must be satisfied):

1. Written in second-person present tense. "You are [PROTAGONIST]. You [ACTION]."
2. Sensory grounding within the first 50 words: one physical detail of texture, sound, smell, or temperature.
3. The protagonist's core trait — identified in Phase 1 — must be demonstrated through action in the first paragraph. Not stated. Shown.
4. The emotional question ("what happens next?") must be planted before any exposition.
5. Word count: 120–180 words maximum.
6. Final sentence must create forward pressure — a question, a threshold, an incomplete action — that the first choice immediately follows.

COLD OPEN:
[Write the full cold open here]

---

TASK 4 — EMOTIONAL PROMISE STATEMENT

In one sentence, state the emotional promise of this cold open. This is what a user would say they felt after reading the opening paragraph.

"The emotional promise of this cold open is: [NOUN]. A user arrives feeling [ADJECTIVE] and wanting to [VERB]."

---

OUTPUT FORMAT — Return all four tasks as a single Phase 3 document.

STOP. Review the cold open before proceeding to Phase 4. The cold open is the foundation of the entire session. If it does not create forward pressure, return to Task 3 and revise.

## PHASE 4 PROMPT — SESSION BEAT ARCHITECTURE (per-session)

Purpose: Map this session's source material onto the five-beat Lorespinner arc.

WHAT TO UPLOAD: Phase 2 Story Session Map and Phase 3 Entry Point output for this session. Plus the source pages allocated to this session by the Story Session Map. A single Lorespinner session covers approximately 40–60 pages of novel prose or 25–40 pages of screenplay.

### LORESPINNER — PHASE 4: SESSION BEAT ARCHITECTURE

[PASTE MASTER CONTEXT BLOCK HERE]

STORY SESSION MAP: [PASTE PHASE 2 OUTPUT — this session's allocation and arc context]
PHASE 3 COLD OPEN + CUT POINT: [PASTE PHASE 3 OUTPUT FOR THIS SESSION]
SOURCE PAGES PROVIDED: [Pages allocated to this session by the Story Session Map]

Your task is to map this session's source material onto the Lorespinner five-beat arc.

---

TASK 1 — IDENTIFY THE FIVE BEATS IN THE SOURCE

Read the provided source pages. Identify the best candidate moment for each of the five beat types. For each beat, cite the specific source moment (page number / scene heading), explain why it qualifies, and note whether it requires significant editorial shaping or can be adapted with minimal intervention.

BEAT: SETUP (0–3 minutes)
Source moment: [PAGE / SCENE]
Why it qualifies: [One sentence]
Editorial intervention required: [Minimal / Moderate / Heavy]

BEAT: ESCALATION (3–10 minutes)
Source moment: [PAGE / SCENE]
Why it qualifies: [One sentence — what is the visible goal, what is the clear obstacle?]
Editorial intervention required: [Minimal / Moderate / Heavy]

BEAT: BREATH (8–10 minutes — Schell Rule)
Source moment: [PAGE / SCENE]
Why it qualifies: [One sentence — what provides the humor, absurdity, or wonder that releases pressure?]
NOTE: If no breath moment exists in the source at this position, flag this. A breath beat must be invented or relocated. A session with no breath beat before minute 10 will underperform.
Editorial intervention required: [Minimal / Moderate / Heavy / INVENTION REQUIRED]

BEAT: TWIST (10–17 minutes)
Source moment: [PAGE / SCENE]
Why it qualifies: [One sentence — what makes this a moral-weight moment? Why is there no correct answer?]
Editorial intervention required: [Minimal / Moderate / Heavy]

BEAT: RESOLUTION (17–22 minutes)
Source moment: [PAGE / SCENE]
Why it qualifies: [One sentence — what goal appears resolved? What is the seed planted for the next session?]
Editorial intervention required: [Minimal / Moderate / Heavy]

---

TASK 2 — BUILD THE SESSION BEAT MAP

Complete the following table in full. Every row must be populated.

SESSION [N] BEAT MAP: [TITLE]

| TIME        | MOMENT (Source reference + one-line description)   | BEAT TYPE  | CHOICE TYPE | CHOICE ARRIVES?           |
| ----------- | -------------------------------------------------- | ---------- | ----------- | ------------------------- |
| 0:00–0:45   | Cold open. Protagonist at threshold.               | SETUP      | BRANCHING   | YES — Branching Choice #1 |
| 0:45–2:00   | [Describe moment]                                  | SETUP      | EXPRESSIVE  | Yes / No                  |
| 2:00–4:00   | [Describe moment]                                  | ESCALATION | —           | No                        |
| 4:00–6:00   | [Describe moment]                                  | ESCALATION | EXPRESSIVE  | Yes / No                  |
| 6:00–8:00   | [Describe moment]                                  | ESCALATION | —           | No                        |
| 8:00–10:00  | [Describe moment — MUST be BREATH]                 | BREATH     | —           | No                        |
| 10:00–12:00 | [Describe moment]                                  | ESCALATION | —           | No                        |
| 12:00–14:00 | [Describe moment]                                  | TWIST      | BRANCHING   | YES — Branching Choice #2 |
| 14:00–16:00 | [Describe moment]                                  | TWIST      | EXPRESSIVE  | Yes / No                  |
| 16:00–18:00 | [Describe moment]                                  | ESCALATION | —           | No                        |
| 18:00–20:00 | [Describe moment]                                  | RESOLUTION | —           | No                        |
| 20:00–22:00 | Session close. Primary arc resolves. Hook planted. | RESOLUTION | BRANCHING   | YES — Branching Choice #3 |

---

TASK 3 — NEXT SESSION AWARENESS

Using the Story Session Map, confirm the following for this session's close:

* What seed must this session plant for the next session? [One sentence — reference the Story Session Map's arc progression]
* Does this session's resolution beat naturally connect to the next session's primary dramatic question? [YES / NEEDS EDITORIAL BRIDGE]

---

OUTPUT FORMAT — Return Tasks 1, 2, and 3 as a single Phase 4 document.

STOP. The beat map is the skeleton of the session. Before proceeding to Phase 5, verify: Is there a BREATH beat between minutes 8 and 10? Are there exactly three BRANCHING choice slots? If either answer is no, revise the map before continuing.

## PHASE 5 PROMPT — CHOICE DESIGN (per-session)

Purpose: Write all choices for this session — three branching choices and all expressive choices between them — following the Lorespinner choice design rules.

WHAT TO UPLOAD: Phase 4 beat map for this session. Plus the specific source pages covering each choice moment (you do not need the full source for this phase — just the pages around each of the choice slots identified in the beat map).

### LORESPINNER — PHASE 5: CHOICE DESIGN

[PASTE MASTER CONTEXT BLOCK HERE]

PHASE 4 BEAT MAP: [PASTE FULL BEAT MAP FOR THIS SESSION]
STORY SESSION MAP: [PASTE PHASE 2 OUTPUT — for cross-session payoff awareness]
PROTAGONIST CORE TRAIT (from Phase 1): [NAME THE TRAIT]
EMOTIONAL PROMISE (from Phase 3): [PASTE STATEMENT]

You are writing all choices for this session. There are two choice types. Do not confuse them.

BRANCHING CHOICE: Load-bearing. Changes what the story tracks about the user going forward. Three per session. Each must reference a branch dimension defined in the Phase 2 Story Session Map (or explicitly declare a new one if required). Requires a full consequence map (Phase 6 will complete this — in this phase, sketch the immediate downstream effect only).

EXPRESSIVE CHOICE: Textural. Colors narration voice, sets tone, adds inventory detail. Does not fork the story. All paths arrive at the same next story moment. As many as serve the session — typically two to four.

---

CHOICE WRITING RULES (apply to every choice):

1. Three options per choice. Always A, B, C.
2. Each option reflects a genuine human value — not a personality type, not a difficulty setting.
3. One option per choice must be the "wait and observe" path — the option that does nothing aggressive but is not passive.
4. The prompt is written in second-person present tense. "What do you do?" not "What does [PROTAGONIST] do?"
5. Each option is one sentence. Declarative. No filler. No adjectives explaining how the character feels doing it.
6. For BRANCHING choices: write the immediate downstream effect as one italic sentence after each option (→ effect).
7. For EXPRESSIVE choices: write the narration/tonal effect as one italic sentence after each option (→ effect).
8. The moral-weight choice (Branching Choice #2) must have no objectively correct answer. Each path reflects a legitimate value. Label it clearly.

---

TASK 1 — BRANCHING CHOICE #1 (SETUP BEAT — arrives within 300 words of cold open)

This is the identity-establishing choice. It sets the register the user carries through the rest of this session and into the next. It is not about what the user does — it is about HOW they do the thing the story requires them to do.

Source moment: [FROM BEAT MAP — cite the page/scene]
What this choice tracks: [Must reference a defined branch dimension from the Phase 2 Story Session Map — e.g. "trust_vs_caution" / "obedience_vs_defiance". If this choice requires a dimension not defined in Phase 2, explicitly declare the new dimension here.]

WRITE THE CHOICE:

[NARRATIVE SETUP — 2–3 sentences of second-person prose leading to the choice. This is the passage immediately before the question appears.]

[CHOICE QUESTION IN SECOND PERSON]

A [OPTION TEXT]
→ [Immediate downstream effect — one italic sentence]

B [OPTION TEXT]
→ [Immediate downstream effect — one italic sentence]

C [OPTION TEXT]
→ [Immediate downstream effect — one italic sentence]

CHOICE TYPE CONFIRMATION: Branching ✓
All three paths arrive at: [Name the next beat they all share]
What this choice tracks going forward: [Repeat the tracking phrase]

---

TASK 2 — EXPRESSIVE CHOICES (ESCALATION AND BREATH BEATS)

Write two to three expressive choices for the Escalation and Breath beats. These are not structural. They personalize.

For each expressive choice:

* State which beat it occurs in (ESCALATION / BREATH)
* Write the narrative lead-in (1–2 sentences)
* Write the A/B/C options
* Write the tonal/narration effect after each option

EXPRESSIVE CHOICE [A]:
Beat: [ESCALATION / BREATH]
Source moment: [Page/scene reference]
[NARRATIVE LEAD-IN]
[CHOICE QUESTION]
A [OPTION] → [Tonal effect]
B [OPTION] → [Tonal effect]
C [OPTION] → [Tonal effect]
All paths arrive at: [Next shared moment]

EXPRESSIVE CHOICE [B]:
[Same format]

EXPRESSIVE CHOICE [C] (if needed):
[Same format]

---

TASK 3 — BRANCHING CHOICE #2 — THE MORAL-WEIGHT CHOICE (TWIST BEAT)

THIS IS THE STICKINESS TARGET. This is the choice users talk about after the session. There is no correct answer. Each option reflects a legitimate human value. The three values in tension must be named explicitly.

Source moment: [Page/scene reference]
Values in tension: [Name three — e.g. courage vs. patience vs. self-reliance]
What this choice tracks: [Must reference a defined branch dimension from Phase 2, or declare a new one]

WRITE THE CHOICE:

[NARRATIVE SETUP — 3–4 sentences. This is the moment of highest emotional pressure in the session so far. The user must feel the weight before the question arrives.]

[CHOICE QUESTION]

A [OPTION] — reflects: [VALUE NAME]
→ [Downstream effect]

B [OPTION] — reflects: [VALUE NAME]
→ [Downstream effect]

C [OPTION] — reflects: [VALUE NAME]
→ [Downstream effect]

MORAL WEIGHT CONFIRMATION: Each option reflects a genuine value ✓ / No option is objectively wrong ✓
Talkability test: "Would a friend ask what you chose?" [YES / NO — if no, redesign]

---

TASK 4 — BRANCHING CHOICE #3 — THE SESSION-END HOOK (RESOLUTION BEAT)

This choice does NOT resolve within the current session. It is a forward-commitment device. The session ends immediately after the user makes this choice. They do not see the consequence — they leave having authored the opening of the next session.

Source moment: [Page/scene reference]
What each path opens in the next session: [Three different next-session openings — name the tone and entry scene for each]

WRITE THE CHOICE:

[NARRATIVE SETUP — 2–3 sentences. The primary arc of this session has just resolved. The user has a moment of arrival. Then the world opens wider. The new question arrives before the user can rest.]

[CHOICE QUESTION — phrased as "Where do you go?" / "What do you do first?" / "Who do you follow?"]

A [OPTION]
→ Next session opens: [One sentence describing the opening scene, tone, and immediate stakes]

B [OPTION]
→ Next session opens: [One sentence describing the opening scene, tone, and immediate stakes]

C [OPTION]
→ Next session opens: [One sentence describing the opening scene, tone, and immediate stakes]

SESSION-END CONFIRMATION: This choice does not resolve within the current session ✓ / User closes the session mid-decision ✓

---

OUTPUT FORMAT — Return all four tasks as a single Phase 5 document with each choice clearly labeled.

STOP. Before proceeding to Phase 6, check: Does every branching choice have three clearly different downstream effects? Does Choice #2 have no correct answer? Does Choice #3 end the session without resolution? If any answer is no, revise before continuing.

## PHASE 6 PROMPT — DOWNSTREAM CONSEQUENCE MAPPING (per-session)

Purpose: Map this session's branching choices forward into future sessions so that the consequences are real, visible, and payable. This phase is completed before any session prose is finalized. The Story Session Map's cross-session payoff plan provides the structural guardrails; this phase fills in the specifics.

WHAT TO UPLOAD: Phase 5 choice designs for this session. Plus the Phase 2 Story Session Map (for cross-session context). You do not need additional source pages for this phase unless a specific future-session moment requires verification against the source.

### LORESPINNER — PHASE 6: DOWNSTREAM CONSEQUENCE MAPPING

[PASTE MASTER CONTEXT BLOCK HERE]

PHASE 5 CHOICE DESIGNS: [PASTE ALL THREE BRANCHING CHOICES FOR THIS SESSION]
STORY SESSION MAP: [PASTE PHASE 2 OUTPUT — cross-session payoff plan]
PROTAGONIST CORE TRAIT: [FROM PHASE 1]

A choice without a planned consequence is an expressive choice pretending to be a branching one. This phase makes every consequence real, specific, and payable. Generalities are not acceptable. "Alice behaves differently" is not a consequence. "The Caterpillar opens with 'Ah. The one who falls without looking.' and does not offer the hookah" is a consequence.

---

TASK — COMPLETE THE CONSEQUENCE MAP FOR EACH BRANCHING CHOICE

For each of the three branching choices from Phase 5, complete the following four-column table in full. Every cell must contain a specific, named moment — not a generalisation.

CONSEQUENCE MAP — BRANCHING CHOICE #1

WHAT THIS CHOICE TRACKS: [Paste from Phase 5]

|                                                                                                      | Path A                                                              | Path B            | Path C            |
| ---------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------- | ----------------- | ----------------- |
| IMMEDIATE EFFECT (this session, within 2 minutes of the choice)                                      | [Specific — what does the user see, hear, or receive differently?]  | [Specific]        | [Specific]        |
| CURRENT SESSION ECHO (how this path colors the rest of this session)                                 | [Specific — name the scene/moment and the difference]               | [Specific]        | [Specific]        |
| NEXT SESSION PAYOFF (a named moment in the next session that explicitly references this choice)       | [Specific — name the character, the line, the event, or the object] | [Specific]        | [Specific]        |
| LATER SESSION LEGACY (if applicable — does this path still have a trace in a session beyond the next?)| [Specific or N/A]                                                   | [Specific or N/A] | [Specific or N/A] |

CONSEQUENCE MAP — BRANCHING CHOICE #2

WHAT THIS CHOICE TRACKS: [Paste from Phase 5]

|                       | Path A | Path B | Path C |
| --------------------- | ------ | ------ | ------ |
| IMMEDIATE EFFECT      |        |        |        |
| CURRENT SESSION ECHO  |        |        |        |
| NEXT SESSION PAYOFF   |        |        |        |
| LATER SESSION LEGACY  |        |        |        |

CONSEQUENCE MAP — BRANCHING CHOICE #3

WHAT THIS CHOICE TRACKS: [Paste from Phase 5]

|                                                                                                          | Path A                                                      | Path B            | Path C            |
| -------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------- | ----------------- | ----------------- |
| IMMEDIATE EFFECT                                                                                         | N/A — session ends on this choice                           | N/A               | N/A               |
| NEXT SESSION OPENING (what the user arrives to in the next session)                                      | [Specific — tone, first image, first character encountered] | [Specific]        | [Specific]        |
| NEXT SESSION PAYOFF (a moment in the next session that validates this as the right choice for THIS user) | [Specific]                                                  | [Specific]        | [Specific]        |
| LATER SESSION LEGACY                                                                                     | [Specific or N/A]                                           | [Specific or N/A] | [Specific or N/A] |

---

VALIDATION CHECK

After completing all three maps, run the following validation:

1. SPECIFICITY TEST: Does every cell in every map contain a named moment, character, line, object, or event — not a generality? Circle any cell that fails. Revise before proceeding.

2. ASYMMETRY TEST: Are the three paths for each choice genuinely different in their next-session experience — not just cosmetically different? A user who chose Path A and a user who chose Path C should feel that the next session was written for them specifically.

3. PAYABILITY TEST: Can the development team actually build these consequences given the source material? Flag any consequence that requires inventing material not latent in the source IP.

VALIDATION RESULTS:
Specificity: [PASS / CELLS TO REVISE: list them]
Asymmetry: [PASS / CHOICES TO REVISE: list them]
Payability: [PASS / FLAGS: list any invented consequences that need IP review]

---

OUTPUT FORMAT — Return all three consequence maps and the validation results as a single Phase 6 document.

STOP. Do not finalize any session prose until Phase 6 is reviewed. Consequences that are vague, symmetric, or non-payable will produce a flat replayability experience. Revise and re-validate before proceeding.

## PHASE 7 PROMPT — SESSION CLOSE AND RETENTION HOOK (per-session)

Purpose: Write the Resolution beat and the session-end hook (Branching Choice #3 in full prose) so the session closes with genuine payoff AND forward commitment.

WHAT TO UPLOAD: Phase 5 (Branching Choice #3 design) and Phase 6 (Choice #3 consequence map). Plus the source pages covering the session’s resolution moment.

### LORESPINNER — PHASE 7: SESSION CLOSE AND RETENTION HOOK

[PASTE MASTER CONTEXT BLOCK HERE]

PHASE 5 — BRANCHING CHOICE #3 DESIGN: [PASTE]
PHASE 6 — CHOICE #3 CONSEQUENCE MAP: [PASTE]
SOURCE PAGES FOR RESOLUTION MOMENT: [UPLOAD OR PASTE RELEVANT SECTION]
THIS SESSION'S PRIMARY GOAL (the thing the user has been working toward): [NAME IT IN ONE SENTENCE]

The session close is the highest-stakes prose in the entire session. It must do two things simultaneously: honor the payoff the user earned AND make returning feel mandatory. These are not in tension — they are the same move, executed in sequence.

---

TASK 1 — THE RESOLUTION BEAT PROSE

Write the Resolution beat. This is the moment the user achieves — or appears to achieve — the primary goal of this session.

RESOLUTION PROSE RULES:

1. The payoff must be real and unambiguous. Do not hedge it. Do not make the user work for it in this moment. They earned it. Give it fully.
2. Sensory specificity: ground the arrival in one physical detail that the user will remember (an image, a sound, a texture, a smell).
3. The seed for the next session must be planted in the resolution — but lightly. It is a question, not a problem. The user notices it but does not feel cheated by it.
4. Word count: 120–200 words.
5. Second-person present tense throughout.

[WRITE THE RESOLUTION PROSE HERE]

---

TASK 2 — THE SESSION-END HOOK

Immediately following the resolution prose, the world opens wider. Write the transition from resolution to the session-end hook choice.

THE TRANSITION RULES:

1. One beat of rest — one sentence that honors the arrival before the world expands.
2. Then: a new detail. A new direction. A new presence. The session does not rest long.
3. The choice question must arrive naturally — it should feel inevitable, not imposed.

Write the transition and the full session-end hook choice (using the design from Phase 5 and the consequences from Phase 6):

[WRITE TRANSITION — 2-3 sentences]

[CHOICE QUESTION]

A [OPTION]
→ NEXT SESSION OPENS: [One vivid sentence — tone, first image, first stakes]

B [OPTION]
→ NEXT SESSION OPENS: [One vivid sentence]

C [OPTION]
→ NEXT SESSION OPENS: [One vivid sentence]

[FINAL LINE — the session's last words before it closes. This is not a cliffhanger. It is an invitation. Write one sentence that makes the user feel they are already in the next session before they even return.]

---

TASK 3 — THE STICKINESS AUDIT

After writing the close, run the following three checks:

CHECK 1 — THE PAYOFF TEST: "Did the user get what they were working toward?"
Answer: [YES / PARTIALLY / NO — if not YES, revise the resolution prose]

CHECK 2 — THE RETURN DRIVER TEST: "Is the user waiting to see what happens, or waiting to find out what they chose to do?"
Answer: [THEY CHOSE / THEY WATCH — must be THEY CHOSE. If THEY WATCH, the session-end hook is a cliffhanger, not a commitment device. Revise.]

CHECK 3 — THE OVERNIGHT TEST: "Will the user think about their choice before they return?"
Answer: [YES / NO — if no, the moral weight of Choice #3 is insufficient. Return to Phase 5 and increase the stakes of each path.]

STICKINESS AUDIT RESULTS:
Payoff test: [PASS / REVISE]
Return driver test: [PASS / REVISE]
Overnight test: [PASS / REVISE]

---

OUTPUT FORMAT — Return Tasks 1, 2, and 3 as a single Phase 7 document.

STOP. The session close is the primary retention mechanism. If any stickiness audit check fails, revise before proceeding to Phase 8.

## PHASE 8 PROMPT — EDITORIAL VERIFICATION CHECKLIST (per-session)

Purpose: Run the complete session design through the ten-question Lorespinner editorial checklist. Every question must pass before the session goes to production.

WHAT TO UPLOAD: The complete session design document — all per-session outputs from Phases 3 through 7 assembled in order, plus the Phase 2 Story Session Map for cross-session context.

### LORESPINNER — PHASE 8: EDITORIAL VERIFICATION CHECKLIST

[PASTE MASTER CONTEXT BLOCK HERE]

COMPLETE SESSION DESIGN: [PASTE ALL PER-SESSION PHASE OUTPUTS — 3 through 7 — IN ORDER]
STORY SESSION MAP: [PASTE PHASE 2 OUTPUT — for cross-session verification]

You are performing the final editorial gate check. This checklist exists because the most common failure modes in interactive adaptation are invisible at the phase level — they only appear when the full session is read as a continuous experience. Read the complete session design as a user would experience it before answering any question.

---

Run each question. Return a clear PASS or REVISE verdict. If REVISE: name the specific element that fails, cite the phase it belongs to, and provide one concrete revision instruction.

QUESTION 1: Where does the real dramatic energy begin? Did we start there?
PASS CONDITION: The cold open starts at the moment of genuine dramatic tension. No passive setup precedes the first paragraph.
VERDICT: [PASS / REVISE]
IF REVISE: [Which phase? What to fix?]

QUESTION 2: What is the emotional promise of the first paragraph?
PASS CONDITION: A user can name it in one word after reading sentence one of the cold open.
VERDICT: [PASS / REVISE]
Emotional promise as written: [Name it — one word]

QUESTION 3: Is the first meaningful branching choice reached within 300 words?
PASS CONDITION: Branching Choice #1 arrives at or before the 300-word mark from the start of the cold open.
VERDICT: [PASS / REVISE]
Word count to Choice #1: [NUMBER]

QUESTION 4: Are all three branching choices genuinely consequential?
PASS CONDITION: Each choice changes what the story tracks going forward — not just the emotional register in the moment, but a named downstream payoff in a future session. Verify against Phase 6 consequence maps and the Story Session Map's cross-session payoff plan.
VERDICT: [PASS / REVISE]
If any choice lacks a named future-session payoff: [Name the choice and the missing payoff]

QUESTION 5: Can a new user feel the stakes within 60 seconds?
PASS CONDITION: The internal stakes (what the protagonist wants and why) are established before the first external choice arrives. A first-time user with zero prior knowledge of this IP understands what is at risk.
VERDICT: [PASS / REVISE]
Stakes as established: [One sentence — what does the user understand is at risk?]

QUESTION 6: Does a decision made early have visible impact later?
PASS CONDITION: At least one choice from this session has a named, specific payoff moment in a future session — a line of dialogue, an object, a character reaction, or a scene entry point that explicitly reflects the earlier choice.
VERDICT: [PASS / REVISE]
Earliest payoff identified: [Choice #, future session moment, and what it says/shows]

QUESTION 7: Is there a breath beat before the midpoint escalation?
PASS CONDITION: A humor, absurdity, or wonder beat exists between the 8- and 10-minute marks of the session. Tension is deliberately released before the TWIST beat compounds it.
VERDICT: [PASS / REVISE]
Breath beat timing: [Minute marker]
Breath beat moment: [Name it]

QUESTION 8: Does at least one choice present a genuine moral gray area?
PASS CONDITION: Branching Choice #2 has no objectively correct answer. Each of the three options reflects a legitimate human value. A thoughtful user could defend any option.
VERDICT: [PASS / REVISE]
Three values in tension as written: [List them]
Could a thoughtful user defend all three? [YES / NO]

QUESTION 9: What emotional state does the user carry out of the session?
PASS CONDITION: The user exits carrying ANTICIPATION — they have made an unresolved decision (Choice #3) that has not yet landed. They are not carrying RESOLUTION (session fully closed) or CONFUSION (session ended without clarity).
VERDICT: [PASS / REVISE]
Emotional state as designed: [ANTICIPATION / RESOLUTION / CONFUSION]

QUESTION 10: Would a friend immediately ask "What did you choose?"
PASS CONDITION: At least one choice in this session is talkable — specific enough, morally weighted enough, or surprising enough that a user would bring it up unprompted in conversation. This is the social-sharing and stickiness gold standard.
VERDICT: [PASS / REVISE]
Most talkable choice: [Name Choice #1, #2, or #3]
Why a friend would ask about it: [One sentence]

---

FINAL VERDICT:

| Question                         | PASS / REVISE |
| -------------------------------- | ------------- |
| 1 — Entry point                  |               |
| 2 — Emotional promise            |               |
| 3 — First choice timing          |               |
| 4 — Consequential choices        |               |
| 5 — Stakes within 60 seconds     |               |
| 6 — Early decision visible later |               |
| 7 — Breath beat                  |               |
| 8 — Moral gray area              |               |
| 9 — Exit emotional state         |               |
| 10 — Talkability                 |               |
| **TOTAL PASSING**                | **/10**       |

PRODUCTION STATUS:

* 10/10: GREEN LIGHT. This session is ready for production.
* 8–9/10: AMBER. Address the flagged items. Re-run only the failed questions after revision.
* 7 or below: RED. Return to the flagged phases. Do not proceed to production until a full re-run scores 9/10 or above.

REVISION INSTRUCTIONS (for any REVISE verdicts):
[List each failed question, the phase it belongs to, and the single most important revision to make]

---

OUTPUT FORMAT — Return the full checklist table and final verdict as a single Phase 8 document.

PRODUCTION GATE: A session that does not pass 10/10 is not ready. Partial passes are not acceptable for launch. Return, revise, and re-verify.

## RUNTIME BRANCH RESOLUTION POLICY

This section is not a pipeline phase. It is a **runtime behavioral contract** that the narrator must follow when a player submits freeform input that does not exactly match a predesigned choice. The adaptation layer defines planned branch architecture; this policy defines how the narrator protects that architecture while preserving the user's feeling of real agency.

### Input Classification

Before resolving any freeform player input, the runtime must classify it into one of four categories. Classification is deterministic and based on the current session architecture, active branch dimensions, current beat context, and known narrative constraints.

| Category             | Definition                                                                                                 |
| -------------------- | ---------------------------------------------------------------------------------------------------------- |
| `expressive`         | Changes tone, attitude, delivery, emphasis, or local scene texture. No durable continuity change required. |
| `branch_aligned`     | Novel in wording but functionally matches a branch dimension already designed in the adaptation layer.     |
| `emergent_candidate` | Introduces a meaningful continuity shift that does not fit any existing branch dimension.                  |
| `unsupported`        | Cannot safely become a durable branch and cannot be mapped to an existing dimension.                       |

### Resolution Order

Once classified, the runtime resolves the input in this priority order:

**1. Expressive Resolution** — If `expressive`: the input affects narration tone, dialogue variation, immediate character reaction, and local descriptive framing. It does **not** create a new tracked canon branch.

Examples: changing how the protagonist speaks, reacting emotionally in a different way, approaching the same scene objective with different style or flavor.

**2. Branch-Aligned Resolution** — If `branch_aligned`: the runtime preserves the user's custom expression in the scene, assigns the result to the nearest valid predesigned branch path, and continues using the existing consequence map for future payoffs. This is the **preferred behavior whenever possible**.

Examples:

* "I test him before trusting him" → maps to *trust vs caution*
* "I avoid the group and move alone" → maps to *self-reliance vs dependence*
* "I challenge the authority figure directly" → maps to *defiance vs compliance*

**3. Emergent Branch Signal** — If `emergent_candidate`: the system records the input as an **emergent branch signal** — a runtime-detected divergence that may matter later, but is not yet treated as a fully supported branch architecture. The runtime must:

* preserve the local consequence in the current scene **when safe**
* record the signal in structured form
* avoid promising downstream consequences not defined in the adaptation layer
* defer permanent canon promotion until validated later
* ensure the signal does **not contradict established canon facts or locked branch outcomes**

Emergent branch signals may later be **promoted to supported branch dimensions** through editorial validation or system-level aggregation, but are never silently upgraded during runtime.

Each emergent signal should be recorded with at minimum this shape:

```json
{
  "session_number": int,
  "event_id": int,
  "player_input": "string",
  "detected_dimension": "string | null",
  "mapped_to_existing_branch": false,
  "requires_editorial_review": true
}
```

**4. Safe Fold-Back** — If `unsupported`: the runtime folds the input into the nearest safe outcome. The fallback must preserve **player intent**:

* the scene must acknowledge the player's action
* the response must feel meaningful in the moment
* the *intent* of the action must be preserved in how the scene reacts
* the outcome must remain compatible with the existing session roadmap and consequence design

### Why This Policy Exists

If every strong freeform player input automatically becomes a new canon branch, the adaptation layer becomes unstable:

* the Story Session Map becomes outdated
* predesigned consequence maps stop matching actual play
* future-session payoffs become unreliable
* runtime begins inventing continuity not planned by the editorial layer
* replayability becomes noisy instead of meaningful
* the system drifts toward sandbox behavior instead of authored interactive storytelling

### Branch Dimension Registry

The runtime repeatedly needs to "map to an existing branch dimension," but that requires a concrete registry to match against. Dimensions are **born in Phase 2** (Story Session Map) as story-level canonical definitions, then **instantiated in Phase 5** when concrete choices are designed against them. The registry structure is:

| Field             | Type   | Source                              |
| ----------------- | ------ | ----------------------------------- |
| `dimension_name`  | string | Phase 2 — canonical story-level definition (e.g. `trust_vs_caution`) |
| `description`     | string | Phase 2 — one-sentence human-readable definition |
| `possible_paths`  | JSON   | Phase 5 — A/B/C meanings from the branching choice that instantiates this dimension |
| `origin`          | string | `phase_2` (planned) or `phase_5` (newly declared during session design) |
| `session_introduced` | int | Which session first uses this dimension |
| `choice_id`       | string | Reference to the originating branching choice in Phase 5 |

The registry is seeded from Phase 2's `branch_dimensions` output and enriched when Phase 5 outputs are persisted. If Phase 5 declares a new dimension not anticipated in Phase 2, it is appended to the registry with `origin: "phase_5"`.

### Emergent Branch Promotion Path

Over time, repeated or high-value emergent signals may be reviewed and promoted into the adaptation layer as formal branch dimensions. But this promotion must happen through an explicit editorial system — never improvised at runtime.

### Runtime State Requirements

The runtime must persist the following structured data per game session. This connects Phase 5 (what choices track), Phase 6 (what consequences depend on), and runtime (what must be remembered turn to turn).

| Field                      | Type                          | Source                                    |
| -------------------------- | ----------------------------- | ----------------------------------------- |
| `current_session_number`   | integer                       | Derived from `event.session_number`       |
| `current_beat_type`        | enum (setup / escalation / breath / twist / resolution) | Phase 4 beat map position    |
| `branching_choices_taken`  | JSON map: choice_id → A/B/C   | Recorded when player picks a branching choice (Phase 5) |
| `tracked_dimensions`       | JSON map: dimension_name → current_path | Dimensions defined in Phase 2, instantiated by Phase 5 choices |
| `emergent_branch_signals`  | JSON array (structured log)   | Recorded by runtime branch resolution     |

Without `branching_choices_taken` and `tracked_dimensions`, Phase 6 consequence maps are unpayable. Without `emergent_branch_signals`, the promotion path has no data to work from.

### Outcome

This policy ensures that:

* users feel **heard, expressive, and impactful**
* branching remains **intentional, trackable, and payable**
* the adaptation layer remains **stable and authoritative**
* the runtime behaves like a **director interpreting performance**, not a system inventing structure

---

## QUICK-REFERENCE PROMPT CHAIN

One-line summary of each phase for team briefings, client presentations, or onboarding new editors.

### Story-wide (run once per IP)

| Phase                              | What You Do                                                                    | Input                                      | Output                                                     |
| ---------------------------------- | ------------------------------------------------------------------------------ | ------------------------------------------ | ---------------------------------------------------------- |
| FORMAT DETECTION                   | Identify novel or screenplay. Establish protagonist and session count estimate. | First 5 pages                              | Format reference card                                      |
| PHASE 1 — IP AUDIT                 | Score the IP on six criteria. Green-light, amber, or red.                      | Three source sections                      | Scored audit card + editorial mitigation                   |
| PHASE 2 — STORY SESSION MAP        | Allocate events to sessions. Define branch dimensions. Plan arcs and payoffs.  | Extraction artifacts + Phase 1 audit       | Session allocation + arc progression + branch dimensions + payoff plan  |

### Per-session (run for each session in the Story Session Map)

| Phase                              | What You Do                                                                                        | Input                                      | Output                                                |
| ---------------------------------- | -------------------------------------------------------------------------------------------------- | ------------------------------------------ | ----------------------------------------------------- |
| PHASE 3 — ENTRY POINT              | Find the cut point for this session. Write the cold open.                                          | Story Session Map + session source pages   | Editorial diagnosis + cold open prose                 |
| PHASE 4 — BEAT ARCHITECTURE         | Map this session’s five beats. Build the session timetable.                                         | Session source pages + Phase 3 output      | Session beat map                                      |
| PHASE 5 — CHOICE DESIGN             | Write all choices for this session. Three branching. Multiple expressive.                           | Phase 4 beat map + choice-moment pages     | All choices with A/B/C options and immediate effects  |
| PHASE 6 — CONSEQUENCE MAPPING       | Map this session’s branching choices forward. Make consequences specific and payable.                | Phase 5 choices + Story Session Map        | Consequence tables + validation                       |
| PHASE 7 — SESSION CLOSE             | Write the resolution beat and the session-end hook. Audit for stickiness.                          | Phase 5/6 + resolution source pages        | Resolution prose + hook choice + stickiness audit     |
| PHASE 8 — VERIFICATION              | Run the 10-question editorial checklist. Gate to production.                                       | Complete session design                    | 10-point checklist + production status verdict        |

Lorespinner Prompt System v1.1 — April 2026. Apply to every IP onboarded. Story-wide first, then session by session. One phase at a time. Review before advancing.
