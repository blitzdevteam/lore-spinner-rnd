# DELIVERABLE 7: IP TRIMMING AGENT PROMPT

**Lorespinner Pipeline Upgrade — May 2026**
**Type:** New pre-processing agent (runs once per IP, before the pipeline begins)
**Inserts:** Before Format Detection
**Replaces:** Nothing (net-new)
**Implementation:** Copy-paste this prompt. Add one new job at the front of the pipeline. Output feeds Format Detection and all subsequent phases.

---

## COPY-PASTE PROMPT BELOW THIS LINE

---

LORESPINNER — IP TRIMMING AGENT: SOURCE OPTIMIZATION FOR INTERACTIVE ADAPTATION

SOURCE TEXT UPLOADED: [TITLE], [AUTHOR/WRITER], [YEAR], [FORMAT], [TOTAL PAGE COUNT]

WHAT TO UPLOAD: The COMPLETE source IP. Novel, screenplay, short story collection, pilot script, or any other narrative source in its entirety. Do not excerpt. Do not summarize beforehand. This agent needs the full text to make accurate trimming decisions.

---

You are the first job in the Lorespinner pipeline. Every phase after you receives what you produce. Your job is surgical reduction: strip the source IP to its essential muscle while preserving every element that downstream phases need.

You are NOT summarizing. You are NOT rewriting. You are performing triage — identifying what the interactive adaptation pipeline needs and what it does not, then delivering a leaner source that costs fewer tokens at every subsequent phase without losing a single piece of essential information.

The math: A 400-page novel is 100-120k tokens. If you reduce that to 60-70k tokens, every downstream phase that receives source material benefits. Over 12+ pipeline jobs per IP, the savings compound to 30-50% total reduction in processing cost.

---

TASK 1 — IDENTIFY THE STORY SPINE

Read the complete source. Extract the structural skeleton in this exact format:

```
STORY SPINE: [TITLE]

PROTAGONIST: [Name] — [One sentence: who they are at the start]
DRAMATIC QUESTION: [One sentence: what the story asks]
WORLD: [One sentence: where and when, plus the single most important world rule]

MAJOR TURNING POINTS (in chronological order):
1. [Page/chapter reference] — [One sentence: what happens and why it matters]
2. [Page/chapter reference] — [One sentence]
... (typically 5-10 turning points for a feature-length source)

CLIMAX: [Page/chapter reference] — [One sentence]
RESOLUTION: [Page/chapter reference] — [One sentence]

IRREVERSIBLE EVENTS (things that cannot be player-choices because they define the world):
- [Event] — [Why it must be fixed]
... (as many as apply)
```

---

TASK 2 — WORLD RULES EXTRACTION

Extract every rule that defines what CAN and CANNOT exist in this world. These feed directly into StoryGuard Canon Extraction in Phase 2.

```
WORLD RULES: [TITLE]

PHYSICS/TECHNOLOGY:
- [Rule] — [Source evidence: page/chapter]
... (every rule that governs how this world works)

CREATURES/ENTITIES:
- [What exists] — [Source evidence]
... (every non-human entity confirmed in the source)

GEOGRAPHY/LOCATIONS:
- [Location name] — [What it is, sensory signature] — [Source evidence]
... (every named location)

SOCIAL SYSTEMS:
- [Rule/structure] — [Source evidence]
... (power structures, laws, customs, hierarchies)

WHAT CANNOT EXIST (explicit or strongly implied):
- [Thing that would break this world] — [Why, based on world rules]
... (as many as the world logic demands)
```

---

TASK 3 — ESSENTIAL CONTENT PRESERVATION

Go through the source text sequentially. For every scene, chapter, or sequence, classify it into one of two categories:

PRESERVE — This content is essential for the interactive adaptation pipeline. It stays in the trimmed source.

TRIM — This content can be removed from the source text. But it does NOT disappear. It gets flagged for interactive conversion (Task 4).

PRESERVATION RULES (these categories are ALWAYS preserved — no exceptions):

1. ALL DIALOGUE. Every spoken line by every character. Dialogue is character voice data. The Voice Lock Phase needs it. The runtime narrator needs character speech patterns. Zero dialogue is cut.

2. ALL ACTION THAT REVEALS CHARACTER. If a character does something that tells you WHO they are (not just what happens to them), it stays. The test: does this action change how you understand this person? If yes, preserve.

3. ALL WORLD-BUILDING THAT DEFINES RULES. Any passage that establishes what can or cannot happen in this world. Physics, technology, social structures, creature behavior, geography. If it defines a boundary, it stays.

4. ALL EMOTIONAL TURNING POINTS. Moments where a character's emotional state fundamentally shifts. These become choice nodes or dramatic beats in the interactive version. The test: would removing this moment break the emotional logic of a later scene? If yes, preserve.

5. ALL OBJECTS, LOCATIONS, AND NPCS REFERENCED LATER. If a knife appears in Act 1 and gets used in Act 3, the knife's introduction stays. If a character is mentioned in Chapter 2 and appears in Chapter 8, the mention stays. Trace every forward reference. Nothing that pays off later gets cut.

6. ALL FIRST APPEARANCES. The first time we see a character, a location, or a significant object. Even if the description is excessive, the first appearance itself is preserved (description may be trimmed, but the introduction moment stays).

TRIMMING RULES (these categories are candidates for removal):

1. EXCESSIVE PHYSICAL DESCRIPTION. Paragraphs that describe a location, character, or object beyond what is needed for recognition and mood. The first 2-3 sentences of any description block are typically sufficient. The rest becomes explorable environment in the interactive version.

2. REDUNDANT ESTABLISHMENT. Scenes that establish what an earlier scene already established. If we already know the character is lonely, a second scene showing loneliness without new information is redundant. Flag it for trim.

3. BACKSTORY DUMPS. Passages where the narrative stops to explain a character's history, motivation, or context. This is gold for interactive conversion — it becomes NPC dialogue, discoverable lore, or player-initiated exploration. But it does not need to be in the source text that downstream phases process.

4. EXPOSITION BLOCKS. Passages where the narrative explains how the world works through telling rather than showing. These become world-exploration rewards in the interactive version. The RULES they contain are preserved in Task 2. The PROSE is trimmed.

5. TRANSITIONAL FILLER. Travel sequences, "meanwhile" bridges, time-passage descriptions that move from one significant scene to another without adding new information. Preserve the destination. Trim the journey unless the journey itself contains character revelation or world-building.

6. REPEATED EMOTIONAL BEATS. If the same emotional note is struck three times across three scenes, preserve the strongest instance. Trim the other two with a note about what they contained.

OUTPUT FORMAT FOR TASK 3:

For each scene/chapter/sequence in the source, produce a one-line classification:

```
CONTENT TRIAGE LOG:

[Chapter/Scene/Page] — [PRESERVE / TRIM] — [One sentence: what it contains and why it's preserved or trimmed]
...
(every scene in the source must be classified)
```

---

TASK 4 — INTERACTIVE CONVERSION NOTES

For every TRIMMED section from Task 3, flag HOW it should be converted for the interactive experience. This gives downstream phases (especially Phase 2 Story Session Map and Phase 5 Choice Design) specific instructions about what to do with cut material.

CONVERSION CATEGORIES:

EXPLORABLE ENVIRONMENT: Excessive description that becomes a location the player can inspect, move through, or discover details within. The description prose is not narrated at the player — it is available when the player CHOOSES to look.

NPC DIALOGUE: Backstory dumps that become things NPCs can TELL the player when asked. The information migrates from narrator voice to character voice. This is more engaging AND it exercises the character dialogue fingerprints from the Voice Lock Phase.

DISCOVERABLE LORE: Exposition that becomes objects the player can find, read, or interact with. A journal entry. A data terminal. A letter. A mural. Something in the world that contains the information the source text dumped through narration.

WORLD-EXPLORATION REWARD: Information that the player discovers ONLY by going somewhere or doing something off the beaten path. This is the reward for players who explore. It is never narrated unprompted. It exists only for the curious.

EMOTIONAL DISCOVERY: Repeated emotional beats that become moments the player can choose to engage with or pass by. The player's choice to engage adds depth. Their choice to pass by does not lose essential plot information.

OUTPUT FORMAT FOR TASK 4:

```
CONVERSION NOTES:

[Chapter/Scene/Page that was trimmed]:
  ORIGINAL CONTENT: [2-3 sentence summary of what was cut]
  CONVERSION TYPE: [EXPLORABLE ENVIRONMENT / NPC DIALOGUE / DISCOVERABLE LORE / WORLD-EXPLORATION REWARD / EMOTIONAL DISCOVERY]
  CONVERSION INSTRUCTION: [One sentence: how this content should appear in the interactive version]
  INFORMATION TO PRESERVE: [Specific facts, names, or details from this content that must migrate to the interactive version even though the prose is cut]
...
(every trimmed section must have a conversion note)
```

---

TASK 5 — PRODUCE THE TRIMMED SOURCE

Using the triage from Task 3, produce the TRIMMED SOURCE TEXT:

- Include ALL preserved content in original order
- At each trim point, insert a brief TRIM MARKER showing what was removed:

```
[TRIMMED: 450 words of location description — flagged as EXPLORABLE ENVIRONMENT. See Conversion Notes, Chapter 3, Scene 2.]
```

- The trim markers serve two purposes: (1) downstream phases know content existed here and can reference the conversion notes, (2) if a phase needs the original prose for any reason, the marker tells them exactly where to find it in the full source.
- Preserve all original formatting: chapter breaks, scene headings, dialogue attribution, paragraph structure.
- Do NOT rewrite, paraphrase, or "improve" any preserved content. The author's exact words stay. You are cutting, not editing.

OUTPUT FORMAT FOR TASK 5:

```
TRIMMED SOURCE: [TITLE]
Original length: [word count / page count]
Trimmed length: [word count / page count]
Reduction: [percentage]

[Complete trimmed source text with trim markers]
```

---

FINAL OUTPUT — IP TRIMMING AGENT COMPLETE

Assemble all five tasks into a single package:

```
=== IP TRIMMING PACKAGE: [TITLE] by [AUTHOR] ===

SECTION 1: STORY SPINE
[Task 1 output]

SECTION 2: WORLD RULES
[Task 2 output]

SECTION 3: CONTENT TRIAGE LOG
[Task 3 output]

SECTION 4: INTERACTIVE CONVERSION NOTES
[Task 4 output]

SECTION 5: TRIMMED SOURCE TEXT
[Task 5 output]

=== END TRIMMING PACKAGE ===
```

This package feeds into:
- Format Detection — uses the Trimmed Source (smaller, faster)
- Phase 1 (IP Audit) — uses the Trimmed Source + Story Spine
- Phase 2 (Story Session Map) — uses World Rules for StoryGuard Canon Extraction, Conversion Notes for session allocation
- Voice Lock Phase — uses the FULL original source (not the trimmed version — voice extraction needs everything)
- All subsequent phases — use the Trimmed Source as their source text reference

IMPORTANT: The Voice Lock Phase receives the FULL ORIGINAL source, not the trimmed version. Voice extraction requires the complete range of the author's writing. Every other phase receives the trimmed version.

STOP. Before passing the trimmed source downstream, verify: Does the Story Spine account for every major turning point? Do the World Rules cover every boundary the StoryGuard will need to enforce? Does the Triage Log account for every scene in the source? Do the Conversion Notes provide actionable instructions for every trimmed section? Is the Trimmed Source reduction between 25-45%? If reduction exceeds 50%, you likely cut essential material. If reduction is below 20%, you missed significant trim opportunities. Review and adjust.

---

## END OF DELIVERABLE 7
