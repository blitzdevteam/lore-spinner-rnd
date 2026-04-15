Below is the change logic doc for switching Lorespinner from the current **event-driven interactive narration flow** into the new **editorial adaptation + guided session architecture flow**.

It is written as a practical implementation note, not a theory paper.

---

# Lore Spinner — Change Logic Log for Interactive Adaptation Mode

This document defines the planned logic change required to move Lorespinner from its current runtime-centered narration model into the new adaptation mode based on phase-by-phase story onboarding, session shaping, planned choice architecture, and downstream consequence design.

The goal is not to replace the current system entirely. The goal is to **insert a stronger editorial AI layer before runtime** so stories are shaped for interactivity before the narrator begins turn-by-turn play. This fits the product direction already stated in the deck: stories that are not just read, but lived, while staying within the author’s intended world and structure. 

---

## 1. Why this change is being made

The current system is strong at runtime scene execution:

* script is broken into chapters and events
* story-level prompt data is generated
* the narrator receives current event context plus nearby continuity
* the narrator produces response, choices, and event advancement turn by turn

This works well for **interactive rendering of scenes**.

The new adaptation mode adds what is currently missing:

* format detection
* IP suitability analysis
* entry-point diagnosis
* session beat planning
* branching vs expressive choice design
* consequence mapping across future sessions
* editorial verification before production

So the logic shift is:

**Current mode:** extract source → narrate event interactively
**New mode:** extract source → adapt story into session structure → narrate session interactively

---

## 2. Current logic that remains valid

The following parts of the current system should stay:

### Runtime narration core

* `NarrationAgent`
* rendered runtime system prompt
* rendered runtime player/history prompt
* structured output shape: `response`, `choices`, `advance_event`

### Canon handling

* `events.content` remains the canonical source for facts
* `objectives` and `attributes` remain support context, not plot authority
* dialogue fidelity and scene boundaries remain enforced by the narrator prompt

### Existing extractor idea

The extractors are already AI-driven and prompt-based. That means the architecture already supports the kind of change we want. We are not introducing a foreign pattern. We are extending the same pattern with new guided adaptation steps.

### Fallback behavior

* gameplay must still fail safely
* controller-level fallback responses should remain
* the UI should never hard break if an adaptation artifact is incomplete

---

## 3. Core logic change

The major change is this:

### Before

The preprocessing pipeline prepares source material so runtime can play the next event.

### After

The preprocessing pipeline must also prepare **how the entire story should be played**, at two levels:

#### Story-wide (runs once per IP)

* how many sessions does this story support
* which events belong to Session 1, Session 2, Session 3, etc.
* where each session begins and ends
* where major branching moments should live across session boundaries
* how earlier choices pay off in later sessions
* high-level arc progression and continuity plan

#### Per-session (runs once per planned session)

* where this session starts (entry point / cut)
* what beats this session contains
* where the branching choices happen within this session
* what those choices track
* what consequences they unlock in this and future sessions
* how this session closes and hands off to the next

This means the AI pipeline is no longer only extracting structure from the source. It is now also **editorially adapting the source into a story-wide session roadmap**, and then **fully designing each session one at a time** against that roadmap.

---

## 4. New target model

Lorespinner should move to a three-layer content model:

### Layer A — Canon extraction layer

This stays close to the existing system:

* source file
* chapters
* events
* event objectives
* event attributes
* world rules / tone / character framing

### Layer B — Story-wide adaptation layer

This is new. It covers the entire IP once:

* detected source format
* IP audit result
* **story session map** — estimated session count, event-to-session allocation, high-level arc progression, major branch opportunities across sessions, continuity/payoff plan

### Layer C — Per-session adaptation layer

This runs once for each planned session in the story session map:

* entry point diagnosis (where this session starts)
* session beat architecture (five-beat map for this session)
* branching + expressive choice definitions
* consequence maps (immediate + cross-session payoffs)
* session close / hook design
* editorial verification result

In short:

**canon tells us what exists**
**story adaptation tells us how those pieces become sessions**
**session adaptation tells us how to play each session**

---

## 5. New pipeline flow

## Phase 0 — Format Detection

New first step before adaptation work begins.

### Purpose

Determine whether the source is screenplay, novel, or another supported narrative format, and establish the correct adaptation rules.

### New AI artifact

`format_detection`

### Suggested output fields

* `detected_format`
* `evidence`
* `narrative_tense`
* `protagonist_name`
* `genre_signals`
* `estimated_duration`
* `estimated_session_count`

### Why this matters

The new adaptation flow assumes format-aware handling. The current system is screenplay-oriented. This phase prepares the system to support broader onboarding logic.

---

## Phase 1 — IP Audit

New evaluation phase after format detection.

### Purpose

Score whether an IP is good for interactive adaptation before the rest of the pipeline invests in it.

### New AI artifact

`ip_audit`

### Suggested output fields

* `licensing_friction_score`
* `latent_choice_architecture_score`
* `bounded_agency_score`
* `emotional_range_score`
* `recognizability_score`
* `replayability_hook_score`
* `total_score`
* `verdict`
* `lowest_scoring_criterion`
* `editorial_mitigation`

### Logic change

Stories should no longer flow directly from upload into extraction and runtime preparation without an editorial viability checkpoint.

---

## Phase 2 — Story Session Map (story-wide)

New whole-story planning phase. This is the bridge between IP-level analysis (Phases 0–1) and per-session design (Phases 3+). It runs **once per IP** and produces the roadmap that every subsequent per-session phase works against.

### Purpose

Using the extraction artifacts (chapters, events, objectives, attributes) plus the format detection and IP audit, build a structural plan for the entire story: how many sessions it supports, which events belong to which session, where the major branch points live, and how earlier choices pay off in later sessions.

### New AI artifact

`story_session_map`

### Suggested output fields

* `estimated_session_count`
* `sessions` — array, one entry per planned session:
  * `session_number`
  * `source_coverage` (chapter/event ranges)
  * `primary_dramatic_question`
  * `emotional_register`
  * `major_branch_opportunities` (event references where branching choices should live)
  * `opens_with` (what seed from the previous session's close this session picks up)
* `cross_session_payoff_plan` — which early choices echo or pay off in which later sessions
* `arc_progression_summary` — high-level narrative arc across all sessions
* `branch_dimensions` — the canonical set of narrative axes that choices across sessions reference and evolve

### Branch dimensions (story-level)

In addition to identifying branch opportunities, this phase must define the **core branch dimensions of the story**. A branch dimension is a reusable narrative axis that multiple choices across sessions may reference and evolve (e.g. `trust_vs_caution`, `obedience_vs_defiance`).

These dimensions must be defined at the story level to ensure:

* consistency across sessions — Session 3 cannot invent a dimension that contradicts Session 1
* alignment with cross-session payoff planning — consequences must track against known axes
* deterministic runtime mapping of player input — the classifier has a fixed vocabulary to match against

Each dimension is expressed as a tension or axis plus a one-line description:

```json
"branch_dimensions": [
  {
    "dimension_name": "trust_vs_caution",
    "description": "How the protagonist approaches uncertainty in others"
  }
]
```

These dimensions act as the canonical vocabulary used by Phase 5 (choice design), Phase 6 (consequence mapping), runtime branch resolution, and emergent signal classification. Phase 5 must reference dimensions from this list when designing branching choices. If a session requires a dimension not anticipated here, Phase 5 may declare a new one, but it must be appended to the story-level registry.

### Logic change

The system must think about the **whole story** before designing any single session. Without this phase, adaptation starts in the middle — it shapes Session 1 well but has no structural plan for Sessions 2+ and no guarantee that branching choices designed in Session 1 can actually pay off later.

This phase makes "replayability" a structural property of the story map, not a hope applied session by session.

---

## Phase 3 — Entry Point Diagnosis (per-session)

New story-cutting phase. Runs once per session in the story session map.

### Purpose

Find the real dramatic beginning for this session and cut passive setup before it. For Session 1, this means cutting from the source opening. For later sessions, this means determining how the session opens based on the previous session's close and the player's branching path.

### New AI artifact

`entry_point_diagnosis` (one per session)

### Suggested output fields

* `session_number`
* `cut_point_reference`
* `cut_reasoning`
* `cut_material_summary`
* `must_reintroduce_later`
* `cold_open`
* `emotional_promise`

### Logic change

Currently the source opening and extracted sequence heavily influence runtime flow. In the new mode, each session begins where dramatic energy begins, not necessarily where the source material for that session starts.

This is one of the biggest changes in philosophy.

---

## Phase 4 — Session Beat Architecture (per-session)

New structural session-planning phase. Runs once per session.

### Purpose

Map this session's source material (as allocated by the story session map) into a playable session with a clear five-beat structure.

### New AI artifact

`session_architecture`

### Suggested output fields

* `session_number`
* `source_coverage`
* `setup_beat`
* `escalation_beat`
* `breath_beat`
* `twist_beat`
* `resolution_beat`
* `branching_choice_slots`
* `expressive_choice_slots`

### Logic change

The system must start thinking in **sessions and beats**, not only chapters and events.

Events still matter, but they become building blocks inside a session design. The story session map (Phase 2) already defined which events belong to this session — this phase shapes them into playable beats.

---

## Phase 5 — Choice Design (per-session)

New explicit choice-authoring phase. Runs once per session.

### Purpose

Pre-design all important choices for this session before runtime.

### New AI artifact

`session_choice_design`

### Suggested output fields

* `branching_choice_1`
* `expressive_choices`
* `branching_choice_2`
* `branching_choice_3`

Each branching choice should include:

* `moment_reference`
* `choice_question`
* `options`
* `what_this_choice_tracks` (must reference a defined branch dimension from the Phase 2 Story Session Map, or explicitly declare a new dimension if required — new dimensions must be appended to the story-level registry)
* `immediate_downstream_effects`

### Logic change

Right now major choices are produced live by the narrator at runtime. In the new mode, important branching choices are authored ahead of time per session, and runtime executes them.

The narrator may still generate micro-choices in the moment, but it should not invent the major session architecture on the fly. The story session map (Phase 2) defines the canonical branch dimensions and the cross-session payoff plan constrains which choices matter most — this phase instantiates those dimensions as concrete choices within the session.

---

## Phase 6 — Downstream Consequence Mapping (per-session)

New consequence-planning phase. Runs once per session.

### Purpose

Make sure this session's branching choices have real future payoff before prose is finalized. The story session map's cross-session payoff plan provides the structural guardrails; this phase fills in the specifics.

### New AI artifact

`choice_consequence_map`

### Suggested output fields

Per branching choice:

* `tracked_dimension`
* `path_a`
* `path_b`
* `path_c`

For each path:

* `immediate_effect`
* `current_session_echo`
* `next_session_payoff`
* `later_session_legacy`

### Logic change

This is the point where replayability becomes system logic instead of a runtime hope.

---

## Phase 7 — Session Close and Retention Hook (per-session)

New session-ending design phase. Runs once per session.

### Purpose

Design the ending of this session so it resolves the current arc while committing the user to the next session. The story session map defines what the next session picks up; this phase designs the handoff in detail.

### New AI artifact

`session_close_design`

### Suggested output fields

* `resolution_prose`
* `hook_transition`
* `session_end_choice`
* `next_session_openings`
* `stickiness_audit`

### Logic change

The session end becomes a designed retention device, not simply the last runtime beat before the next event.

---

## Phase 8 — Editorial Verification (per-session)

New quality gate before a session goes live. Runs once per session.

### Purpose

Check whether this session's full design is actually production-ready.

### New AI artifact

`editorial_verification`

### Suggested output fields

* `question_results`
* `total_passed`
* `production_status`
* `revision_instructions`

### Logic change

There should now be a formal production gate between adaptation output and runtime activation.

---

## 6. What changes in the database / stored artifacts

This does not need to be overbuilt at first. Keep it simple.

### Existing tables that still matter

* `stories`
* `chapters`
* `events`
* `games`
* `prompts`

### New stored adaptation artifacts needed

At minimum, the story needs a place to store these generated assets:

**Story-wide (one per story):**

* `format_detection`
* `ip_audit`
* `story_session_map` — session count, event-to-session allocation, arc progression, cross-session payoff plan

**Per-session (one set per planned session):**

* `entry_point_diagnosis`
* `session_architecture`
* `session_choice_design`
* `choice_consequence_map`
* `session_close_design`
* `editorial_verification`

Story-wide artifacts can begin as JSON columns on `stories` or a dedicated adaptation table per story. Per-session artifacts belong in a session-level table (one row per session per story) or as JSON on a sessions table.

### Runtime join: event → session adaptation

The narrator needs to know which session adaptation row applies to the current turn. The cleanest way to wire this is a nullable `session_number` mapping column on the `events` table:

* When the Story Session Map (Phase 2) is finalized, each `events` row receives a `session_number` (integer, nullable).
* This is written once during the story-wide adaptation pass and never changes at runtime.
* At runtime the join is:

```
game.current_event_id
  → event.session_number
    → session_adaptations WHERE story_id = event.chapter.story_id
                           AND session_number = event.session_number
```

This avoids range queries and makes the lookup a single indexed join. It also means the narrator can be handed the correct beat map, choice definitions, and consequence hooks without any position-range arithmetic.

If `session_number` is null (story has not been through the adaptation pipeline yet), runtime falls back to the current narrator behavior — no adaptation context, just canon extraction artifacts. This preserves backward compatibility with stories that predate the adaptation layer.

### Runtime state requirements

The runtime must persist the following structured data per game session. This connects Phase 5 (what choices track), Phase 6 (what consequences depend on), and runtime (what must be remembered turn to turn).

| Field                      | Type                          | Source                                    |
| -------------------------- | ----------------------------- | ----------------------------------------- |
| `current_session_number`   | integer                       | Derived from `event.session_number`; may be cached on `games` |
| `current_beat_type`        | enum (setup / escalation / breath / twist / resolution) | Phase 4 beat map position    |
| `branching_choices_taken`  | JSON map: choice_id → A/B/C   | Recorded when player picks a branching choice (Phase 5) |
| `tracked_dimensions`       | JSON map: dimension_name → current_path | Dimensions defined in Phase 2, instantiated by Phase 5 choices |
| `emergent_branch_signals`  | JSON array (structured log)   | Recorded by runtime branch resolution (Section 11) |

`current_session_number` can be derived from `event.session_number` but is worth caching on `games` to avoid repeated joins. `branching_choices_taken` and `tracked_dimensions` are the bridge between adaptation design and runtime consequence delivery — without them, Phase 6 consequence maps are unpayable. `emergent_branch_signals` is the structured log that feeds the emergent-to-formal promotion path.

The `event.session_number` mapping column is the prerequisite that makes all of this resolvable.

---

## 7. What changes in the creator pipeline

The current creator pipeline is extraction-first. It should become:

### New creator onboarding flow

**Story-wide passes (run once per IP):**

1. Upload source
2. Run format detection
3. Run IP audit
4. Run chapter extraction
5. Run event extraction
6. Run event objective/attribute extraction
7. Run story system prompt generation
8. **Run story session map** — allocate events to sessions, plan arcs and cross-session payoffs

**Per-session passes (repeat for each planned session):**

9. Run entry point diagnosis for this session
10. Run session beat architecture for this session
11. Run choice design for this session
12. Run consequence mapping for this session
13. Run session close generation for this session
14. Run editorial verification for this session
15. Mark this session ready for interactive production

This keeps the existing extraction logic and simply inserts the new adaptation guidance around it — first at the story level, then session by session.

That is the cleanest migration path.

---

## 8. What changes in the agent layer

The system already uses AI agents and prompt-driven jobs. That should continue.

### Existing agents kept

* `NarrationAgent`
* `SystemPromptGeneratorAgent`
* `OpeningNarrationAgent`
* `ChapterExtractorAgent`
* `EventExtractorAgent`
* `EventObjectiveAndAttributesExtractor`

### New agents to add

**Story-wide:**

* `FormatDetectionAgent`
* `IpAuditAgent`
* `StorySessionMapAgent`

**Per-session:**

* `EntryPointDiagnosisAgent`
* `SessionArchitectureAgent`
* `ChoiceDesignAgent`
* `ConsequenceMappingAgent`
* `SessionCloseAgent`
* `EditorialVerificationAgent`

Each of these should behave like the current extractors:

* prompt-driven
* structured JSON output
* single clear responsibility
* queueable job wrapper

This matches how the app already works today, so it is a natural extension rather than a rewrite.

---

## 9. What changes in runtime gameplay

The runtime narrator prompt remains useful, but its role changes slightly.

### Current runtime role

It both:

* executes the scene
* invents important choice structure live

### New runtime role

It should mainly:

* execute the scene
* respond to player action
* keep canon fidelity
* present predesigned branching choices at the right moments
* generate only lightweight expressive or situational choices when appropriate

So the runtime narrator becomes more of a **director and performer**, less of a **session architect**.

That is a healthier split.

---

## 10. Prompt logic changes

The current narrator prompt is still strong for:

* canon fidelity
* interactivity-first behavior
* turn pacing
* anti-autopilot
* anti-spoiler control
* playable choice formatting

What needs to change is what gets fed into it.

### New runtime prompt inputs should eventually include

* session number
* current beat type
* current branching choice slot if any
* pre-authored branching choice definitions
* tracked user path values
* consequence hooks already earned

So instead of only passing:

* previous events
* current event
* next events
* conversation history

the narrator can also receive:

* current session plan
* current beat objective
* whether this turn is a branching moment or expressive moment
* which consequences must remain available later

This is the key runtime adaptation bridge.

### How the narrator resolves its adaptation context

At runtime, the join path is:

1. `game.current_event_id` → load the `Event`
2. `event.session_number` → look up the `session_adaptations` row for this story + session number
3. From that row, read: beat map, choice definitions, consequence hooks, session close design
4. Feed the relevant slice into the narrator system prompt alongside the existing canon context

If `event.session_number` is null, skip step 2–4 and run the narrator with canon-only context (current behavior). This keeps the runtime backward-compatible.

---

## 11. Runtime branch resolution policy

The adaptation layer defines the planned branch architecture of the story, but Lorespinner must preserve the user's feeling of real agency when they enter custom freeform input. To balance authored structure with player freedom, the runtime treats freeform input as a **branch resolution problem**, not as unrestricted canon generation.

Before applying any resolution, the runtime must **classify the player input** into one of the following categories:

* `expressive`
* `branch_aligned`
* `emergent_candidate`
* `unsupported`

This classification must be deterministic and based on the current session architecture, active branch dimensions, current beat context, and known narrative constraints.

Once classified, the runtime resolves the input in the following order:

### 1. Expressive resolution

If the input changes tone, attitude, delivery, emphasis, or local scene texture but does not require a durable continuity change, it is treated as an expressive action.

Examples: changing how the protagonist speaks, reacting emotionally in a different way, approaching the same scene objective with different style or flavor.

In this case, the input affects narration tone, dialogue variation, immediate character reaction, and local descriptive framing. It does **not** create a new tracked canon branch.

### 2. Branch-aligned resolution

If the custom input is novel in wording but functionally matches a branch dimension already designed in the adaptation layer, the runtime maps it onto that existing branch.

Examples:

* "I test him before trusting him" → maps to *trust vs caution*
* "I avoid the group and move alone" → maps to *self-reliance vs dependence*
* "I challenge the authority figure directly" → maps to *defiance vs compliance*

In this case, the runtime preserves the user's custom expression in the scene, assigns the result to the nearest valid predesigned branch path, and continues using the existing consequence map for future payoffs.

This is the **preferred behavior whenever possible**.

### 3. Emergent branch signal

If the input introduces a meaningful new continuity shift that does not fit any existing expressive outcome or branch dimension, the system must not immediately create a new canon branch. Instead, it records the input as an **emergent branch signal**.

An emergent branch signal is a runtime-detected divergence that may matter later, but is not yet treated as a fully supported branch architecture.

Examples: the player forms an unexpected alliance, the player reveals or learns information outside the planned choice map, the player takes a decisive action that alters relationship state, timing, or intent.

In this case, the runtime must:

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

This gives engineers a concrete structure to build against without overdesigning the schema upfront.

### 4. Safe fold-back

If the freeform input cannot safely become a durable branch and cannot be mapped to an existing branch dimension, the runtime must fold it into the nearest safe outcome.

This fallback must preserve **player intent**, even if the exact requested outcome cannot be honored.

This means:

* the scene must acknowledge the player's action
* the response must feel meaningful in the moment
* the *intent* of the action must be preserved in how the scene reacts
* the outcome must remain compatible with the existing session roadmap and consequence design

This is the fallback path that protects both **user experience** and **system coherence**.

### Why this policy exists

If every strong freeform player input automatically becomes a new canon branch, the adaptation layer becomes unstable very quickly.

The main risks are:

* the Story Session Map becomes outdated
* predesigned consequence maps stop matching actual play
* future-session payoffs become unreliable
* runtime begins inventing continuity not planned by the editorial layer
* replayability becomes noisy instead of meaningful
* the system drifts toward sandbox behavior instead of authored interactive storytelling

Lorespinner must preserve **freedom at the level of expression and meaningful response**, without allowing uncontrolled branch explosion.

### Emergent branch promotion path

The runtime narrator may receive player input that does not exactly match a predesigned choice. The resolution order is:

* if the input is expressive → keep it local to narration
* if the input matches an existing branch dimension → map it to that branch
* if the input introduces meaningful continuity not covered by current branch design → record it as an `emergent_branch_signal`

Emergent branch signals do **not** automatically become permanent canon branches. They must not contradict established canon or locked outcomes. Permanent canon branches must be validated and persisted through an explicit system, not improvised at runtime.

Over time, repeated or high-value emergent signals may be reviewed and promoted into the adaptation layer as formal branch dimensions.

### Branch dimension registry

The runtime repeatedly needs to "map to an existing branch dimension," but that requires a concrete registry to match against. Dimensions are **born in Phase 2** (Story Session Map) as story-level canonical definitions, then **instantiated in Phase 5** when concrete choices are designed against them. The registry structure is:

| Field             | Type   | Source                              |
| ----------------- | ------ | ----------------------------------- |
| `dimension_name`  | string | Phase 2 — canonical story-level definition (e.g. `trust_vs_caution`) |
| `description`     | string | Phase 2 — one-sentence human-readable definition |
| `possible_paths`  | JSON   | Phase 5 — A/B/C meanings from the branching choice that instantiates this dimension |
| `origin`          | string | `phase_2` (planned) or `phase_5` (newly declared during session design) |
| `session_introduced` | int | Which session first uses this dimension |
| `choice_id`       | string | Reference to the originating branching choice in Phase 5 |

The registry is seeded from Phase 2's `branch_dimensions` output and enriched when Phase 5 outputs are persisted. If Phase 5 declares a new dimension not anticipated in Phase 2, it is appended to the registry with `origin: "phase_5"`. This ensures runtime branch-aligned resolution always has a complete, stable vocabulary to match against — not raw text.

### Outcome

This policy ensures that:

* users feel **heard, expressive, and impactful**
* branching remains **intentional, trackable, and payable**
* the adaptation layer remains **stable and authoritative**
* the runtime behaves like a **director interpreting performance**, not a system inventing structure

---

## 12. New logic boundaries

To keep the system clean, these responsibilities should be separated:

### Canon extraction responsibility

“What exists in the source?”

### Story adaptation responsibility

“How do those extracted pieces become sessions? How many sessions, which events belong where, where do major branches live, and how do choices pay off across the whole story?”

### Session adaptation responsibility

“For this one session: what should be cut, emphasized, turned into beats, and turned into choices? How does this session close and hand off to the next?”

### Runtime narration responsibility

“How do we play this designed moment interactively without breaking canon or pacing?”

### Runtime branch resolution responsibility

“When the player does something unplanned, how do we classify it and resolve it without destabilizing the adaptation layer?” (See Section 11 for the full policy: expressive → branch-aligned → emergent signal → safe fold-back.)

This five-layer separation is the whole point of the change. Extraction converts source into canonical chunks. Story adaptation converts canonical chunks into a session roadmap. Session adaptation fully designs one playable session at a time. Runtime plays the current session moment by moment. Branch resolution protects the system when player input diverges from the designed architecture.

---

## 13. Recommended implementation order

Keep the rollout simple.

### Step 1 — Add adaptation artifacts without changing runtime

Implement the new agents and store their outputs, but do not yet change gameplay behavior.

This gives:

* format detection
* IP audit
* story session map
* entry point diagnosis
* session architecture
* choice design
* consequence mapping
* verification

### Step 2 — Use adaptation artifacts in creator review

Let editors and creators inspect and approve the outputs before publishing.

### Step 3 — Feed session architecture into runtime

Update runtime prompt assembly so it can read:

* beat map
* designed branching choices
* tracked path metadata

### Step 4 — Add persistent branching state

Store player path decisions as structured state instead of only as prompt history.

### Step 5 — Move event progression toward session progression

Do not remove event logic, but begin layering:

* session
* beat
* branch state
  on top of it.

---

---

## 14. Main rule to preserve during the switch

Even after this change, Lorespinner must still feel like:

* author-guided, not open chaos
* interactive, not passive summary
* replayable, not fake-choice theater
* structured, not sandbox drift

That is consistent with both the deck’s Story Guard vision and the current runtime narrator design.

---

## 15. Final change summary

Lorespinner is not switching away from its current AI system.

It is upgrading the system from:

**AI extractors + AI narrator**

to:

**AI extractors + AI story adaptation planner + AI session adaptation designers + AI narrator + runtime branch resolution**

The current system already knows how to break a story into playable pieces.
The new mode adds two layers of missing intelligence and one runtime safety layer:

**Story adaptation** (once per IP) decides:

* how many sessions this story supports
* which events belong to which session
* where major branch points live across the full arc
* how earlier choices pay off in later sessions

**Session adaptation** (once per session) decides:

* where the session should begin
* what beats it contains
* where choices should happen
* what those choices mean
* how this session closes and commits the player to the next

**Runtime branch resolution** (every turn) ensures:

* freeform player input is classified (expressive / branch-aligned / emergent / unsupported)
* existing branch architecture is preserved, not bypassed
* emergent signals are recorded but not silently promoted to canon
* player agency feels real without destabilizing the adaptation layer

That is the change. The story is adapted as a whole, then each session is fully designed, then runtime plays them — and when players go off-script, the system knows how to resolve it safely.
