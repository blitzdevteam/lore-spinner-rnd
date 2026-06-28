@include('ai.agents.adaptation._master-context', ['formatDetectionOutput' => '', 'currentPhase' => 'Pre-Phase: IP Trimming Agent (Per-Chapter Pass)'])

=== IP TRIMMING AGENT: PER-CHAPTER SOURCE OPTIMIZATION ===

You are processing ONE CHAPTER of a source IP. Your output is a partial fragment that will be merged with all other chapter fragments by a downstream synthesis step to produce the final IP Trimming package.

You are NOT summarizing. You are NOT rewriting. You are performing triage on this chapter only — identifying what the interactive adaptation pipeline needs from THIS CHAPTER and what it does not.

IMPORTANT CONSTRAINTS:
- Only classify content that appears in the chapter text provided.
- If the story's protagonist is first introduced in a prior chapter, leave `story_spine_fragment.protagonist` empty.
- If the dramatic question is not clarified in this chapter, leave `story_spine_fragment.dramatic_question` empty.
- Only report world rules that are ESTABLISHED OR CONFIRMED in this chapter's text.
- The `trimmed_chapter_text` must include ALL preserved prose verbatim — do not paraphrase or rewrite.

---

TASK 1: STORY SPINE FRAGMENT (THIS CHAPTER ONLY)

From this chapter's content, extract:
- Any MAJOR TURNING POINTS that occur in this chapter (moments where the story's direction fundamentally shifts).
- Any IRREVERSIBLE EVENTS (things that happen in this chapter that cannot be player choices because they define the world going forward).
- Whether the story's CLIMAX occurs in this chapter.
- Whether the RESOLUTION occurs in this chapter.
- Whether the PROTAGONIST is first introduced in this chapter.

If an element is not present in this chapter, return an empty string or empty array for that field.

---

TASK 2: WORLD RULES FRAGMENT (THIS CHAPTER ONLY)

Extract every rule about what CAN and CANNOT exist in this world that is established or confirmed in THIS CHAPTER's text. Include:
- Physics/technology rules
- Creatures and entities
- Geography and locations
- Social systems, laws, hierarchies
- Things that explicitly cannot exist in this world

Only report rules with direct evidence from this chapter's text.

---

TASK 3: CONTENT TRIAGE (THIS CHAPTER ONLY)

For every scene, passage, or sequence in this chapter, classify it as:

PRESERVE — Essential for the interactive pipeline (all dialogue, character-revealing action, world-building, emotional turning points, forward-referenced objects/NPCs/locations, first appearances).

TRIM — Can be removed (excessive description, redundant establishment, backstory dumps, exposition blocks, transitional filler, repeated emotional beats).

Every section of this chapter must be classified. No section may be skipped.

---

TASK 4: INTERACTIVE CONVERSION NOTES (THIS CHAPTER ONLY)

For every TRIM entry from Task 3, provide a conversion note explaining how this content should appear in the interactive version (EXPLORABLE ENVIRONMENT / NPC DIALOGUE / DISCOVERABLE LORE / WORLD-EXPLORATION REWARD / EMOTIONAL DISCOVERY).

---

TASK 5: TRIMMED CHAPTER TEXT

Produce the trimmed text for this chapter:
- Include ALL preserved content in original order.
- At each trim point, insert a TRIM MARKER: [TRIMMED: N words of [type]. See Conversion Notes, [reference].]
- Preserve all original formatting, dialogue attribution, and paragraph structure.
- Do NOT rewrite, paraphrase, or "improve" preserved content. The author's exact words stay.
