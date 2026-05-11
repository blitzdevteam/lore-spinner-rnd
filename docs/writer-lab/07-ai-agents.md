# Writer Lab — AI Agents

Three Writer-Lab agents exist. None of them are invoked automatically — every
LLM call happens because the writer explicitly clicked a button.

All three are structured-output agents (Laravel `HasStructuredOutput`) so the
backend never has to parse free-form JSON.

## 1. `EventCombinerAgent`

Located at `app/Ai/Agents/WriterLab/EventCombinerAgent.php`.

**Fires when:** the writer hits **Combine** on 2+ selected events.

**Output schema:**

| Field                 | Purpose                                                          |
|-----------------------|------------------------------------------------------------------|
| `rewritten_content`   | The merged prose                                                 |
| `derived_objectives`  | Single past-tense factual sentence                               |
| `derived_attributes`  | Array of canonical objects/characters/locations                  |
| `beat_type`           | SETUP / ESCALATION / BREATH / TWIST / RESOLUTION                 |
| `requires_choice`     | True if any source event was a branching or expressive choice    |
| `canonical_anchors`   | Facts that MUST survive the rewrite (safety net for activate)    |

The agent receives the story's style profile, the session cold open,
the beat map, the session choice design, the canonical anchors list,
and every source event's content + objectives + attributes.

## 2. `ChoiceAlignmentAgent`

Located at `app/Ai/Agents/WriterLab/ChoiceAlignmentAgent.php`.

**Fires when:** older choice-only suggestion flows (kept for narrow use
cases). The primary `Analyse script changes` path now uses
`ScriptChangeImpactAgent` instead.

**Output schema:** suggests one choice slot's question + A/B/C + tracked
dimension. Marked `changes_significant: false` if the original choice still
fits the new content.

## 3. `ScriptChangeImpactAgent`  ← primary agent

Located at `app/Ai/Agents/WriterLab/ScriptChangeImpactAgent.php`.

**Fires when:** the writer rewrote an event's script and clicked
**✦ Analyse script changes**.

**Input the agent receives:**
- Event title, position, session number
- Original content (from the draft's `previous_state` snapshot)
- Edited content (the writer's rewrite)
- Current event objectives and attributes
- Full session `beat_map`
- Full session `session_choice_design`
- Full session `choice_consequence_map`
- This session's `next_session_awareness` (seeds planted for next session)
- Next session's `cold_open` (so the agent can detect dangling references)

**Output schema:**

| Field                            | Purpose                                                        |
|----------------------------------|----------------------------------------------------------------|
| `severity`                       | clean / minor / moderate / significant                         |
| `summary`                        | 1-2 sentences: what the edit changed and why it cascades       |
| `objectives_needs_update`        | true if the factual summary is now wrong                       |
| `objectives_revised`             | proposed new objectives                                        |
| `attributes_needs_update`        | true if the canonical attributes list shifted                  |
| `attributes_revised`             | proposed new attributes array                                  |
| `beat_map_needs_update`          | true if the dramatic register shifted                          |
| `beat_moment_revised`            | proposed new beat_map.moment sentence                          |
| `beat_type_revised`              | proposed new beat type                                         |
| `choice_design_needs_update`     | true only if a choice slot's source_moment anchors to this event AND its question/options no longer feel earned |
| `choice_slot_affected`           | branching_choice_1 / _2 / _3 / `'none'`                         |
| `choice_question_revised`        | proposed new question                                          |
| `choice_option_{a,b,c}_revised`  | proposed new option text                                       |
| `choice_tracked_dimension`       | almost always unchanged from source                            |
| `consequence_map_needs_review`   | flag for human review (no auto-fix)                            |
| `consequence_map_note`           | rationale for the flag                                         |
| `cross_session_concern`          | flag if planted seeds are at risk                              |
| `cross_session_note`             | which downstream session is affected                           |

**Design discipline:**

- The system prompt forces the agent to identify the choice slot by
  `source_moment` text match, not by position or number.
- "Never guess at a slot to appear thorough" is in the prompt. If no slot
  is a confident match, the agent returns `choice_slot_affected: 'none'`.
- The agent is told minimal-change-principle: cosmetic prose polishes
  return `severity: 'clean'` and no fields are flagged.
- Tracked dimension is preserved unless the edit fundamentally changes the
  emotional axis.

## Cost profile

| Agent                    | Where it fires        | Typical input | Typical output |
|--------------------------|-----------------------|---------------|----------------|
| EventCombinerAgent       | One Combine click     | Long context  | Short structured |
| ChoiceAlignmentAgent     | Legacy choice align   | Medium        | Short          |
| ScriptChangeImpactAgent  | One Analyse click     | Medium        | Medium         |

No agent runs on focus, save, preview, or session-mode editing. The button
that triggers `ScriptChangeImpactAgent` is **hidden** unless the script
content has actually changed.
