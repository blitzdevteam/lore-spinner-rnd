# DELIVERABLE 9: SOCIAL ECHO LAYER SPECIFICATION

**Lorespinner Pipeline Upgrade — May 2026**
**Type:** Product/feature specification (for Daniel and the product team)
**Replaces:** Nothing (net-new)
**Implementation:** This is a spec, not a prompt. Engineering implementation by Daniel + product team (Shervin, Mike, Spencer). Ships after the core pipeline and runtime are working.

---

## WHAT THIS IS

A post-episode social sharing system that turns private choices into social currency. The goal: every player who finishes an episode becomes a marketing channel. Their share card is different from their friend's share card. That difference creates conversation. Conversation creates replay. Replay creates retention.

This is the Candy Crush mechanic applied to narrative: the comparison, the competition, the curiosity about the road not taken.

---

## A. THE SHARE CARD

After each episode closes (SESSION_COMPLETE signal), the player sees a share card. The card is generated from runtime state data. It is NOT pre-authored — it is assembled dynamically from the player's specific choices.

### SHARE CARD CONTENTS

1. **STORY IDENTITY**: Episode title + story name + episode number. Clean. Branded. Recognizable.

2. **ALIGNMENT TENDENCY VISUAL**: A visual spectrum showing where this player's choices landed across the chaotic/lawful/neutral axis FOR THIS EPISODE. NOT labeled with those words. Shown as a position on a visual bar or gradient using story-specific theming.
   - The spectrum uses STORY-NATIVE visual language, not RPG terms
   - Example (Anima Machina): A gradient from glitch-red to circuit-blue to static-gray
   - Example (Alice): A gradient from madness-purple to order-gold to curiosity-green
   - The player's position on the spectrum is a single marker

3. **THE DEFINING LINE**: One provocative sentence about the choice that most defined this player's session. Written in the author's voice. Not a summary. A provocation.
   - This line is selected by the runtime from a set of pre-authored lines in the Session Packet (one per branching choice, per path)
   - The runtime selects the line from the choice with the HIGHEST moral weight (typically Branching Choice #3)
   - Example: "You left him at the gate. He was still calling your name when the door closed."
   - Example: "You chose the machine over the memory. The light behind her eyes went out."
   - The line must make the player's friend ask: "Wait, what? What happened?"

4. **COMPARISON HOOK**: A statistical comparison showing how this player's defining choice compared to all other players.
   - Format: "47% of players made the same choice. 12% chose what you almost did."
   - The "what you almost did" percentage references the option the player hovered on or considered longest (if tap data is available) or the second-most-popular option (if hover data is not available)
   - The comparison is factual, not judgmental. No "you're in the minority" framing. Just the numbers.

5. **SHARE BUTTON**: Standard social platform sharing (Instagram Stories, Twitter/X, TikTok, iMessage). The card is a static image optimized for each platform's aspect ratio.

### SHARE CARD DATA REQUIREMENTS

The runtime must persist the following per session to generate the card:
```
{
  "episode_id": "string",
  "story_id": "string",
  "player_id": "string",
  "alignment_tendency": {
    "chaotic": int,
    "lawful": int,
    "neutral": int
  },
  "defining_choice": {
    "choice_id": "string",
    "path_selected": "A/B/C",
    "defining_line": "string (pre-authored)"
  },
  "aggregate_comparison": {
    "same_choice_pct": float,
    "almost_choice_pct": float,
    "almost_choice_path": "A/B/C"
  }
}
```

### SHARE CARD DESIGN PRINCIPLES

- The card must look good enough to share without embarrassment
- The card must create curiosity in the viewer ("I want to play this and see MY card")
- The card must NOT spoil the story — the defining line is evocative, not explanatory
- The card must feel personal to the player, not generic
- The card must be different from their friend's card in a visible, conversation-starting way

---

## B. THE ALIGNMENT PROFILE

Across multiple episodes, the player builds a cumulative alignment profile. This is their "player identity" within this story.

### PROFILE RULES

1. **STORY-NATIVE LANGUAGE**: The profile does NOT use "chaotic/lawful/neutral." It uses language native to the story's world.
   - The pipeline produces the story-native alignment labels as part of the Voice Lock or Phase 2 output
   - Example (Anima Machina): "Disruptor / Architect / Ghost"
   - Example (Alice): "Wild Card / Logician / Observer"
   - Example (noir IP): "Loose Cannon / By-the-Book / Street Smart"
   - The three labels must feel like they belong in the story, not in a game

2. **POST-EPISODE ONLY**: The alignment profile is NEVER visible during gameplay. It appears only after SESSION_COMPLETE. During the experience, the player has no awareness of being tracked. The alignment is subliminal.

3. **EVOLUTION**: The profile evolves across episodes. A player who was "Disruptor" in Episodes 1-2 might shift to "Architect" by Episode 4 if their choices change. The profile reflects cumulative tendency, not permanent label.

4. **SHAREABLE**: The profile card is shareable as a standalone image. It shows:
   - Story name
   - Player's current alignment label
   - Visual representation of tendency across episodes (e.g., a small graph showing how they shifted over time)
   - Episodes completed count

### ALIGNMENT PROFILE DATA

```
{
  "story_id": "string",
  "player_id": "string",
  "episodes_completed": int,
  "cumulative_alignment": {
    "chaotic": int,
    "lawful": int,
    "neutral": int
  },
  "current_label": "string (story-native)",
  "label_history": [
    {"episode": 1, "label": "Disruptor"},
    {"episode": 2, "label": "Disruptor"},
    {"episode": 3, "label": "Architect"}
  ]
}
```

### ALIGNMENT LABEL MAPPING

The pipeline must produce a mapping table per IP:

```
ALIGNMENT LABELS: {{IP_TITLE}}

Chaotic-dominant → "{{STORY_NATIVE_CHAOTIC_LABEL}}"
  Description: "{{One sentence — what this type of player does in this world}}"
Lawful-dominant → "{{STORY_NATIVE_LAWFUL_LABEL}}"
  Description: "{{One sentence}}"
Neutral-dominant → "{{STORY_NATIVE_NEUTRAL_LABEL}}"
  Description: "{{One sentence}}"
Mixed (no clear dominant) → "{{STORY_NATIVE_MIXED_LABEL}}"
  Description: "{{One sentence}}"
```

---

## C. THE COMPARISON HOOK

After each major branching choice, the system tracks aggregate player decisions. These aggregates are surfaced ONLY post-episode. They NEVER influence the experience during play.

### COMPARISON DATA POINTS

For each branching choice across all players:
```
{
  "choice_id": "string",
  "total_players": int,
  "path_A_pct": float,
  "path_B_pct": float,
  "path_C_pct": float,
  "freeform_pct": float,
  "correlation_data": {
    "players_who_chose_A_here_then_chose": {
      "next_choice_A_pct": float,
      "next_choice_B_pct": float,
      "next_choice_C_pct": float
    }
  }
}
```

### COMPARISON HOOK FORMATS

The share card and alignment profile can surface comparisons in these formats:

- **Rarity hook**: "Only 8% of players chose to trust Riven at this point."
- **Correlation hook**: "Players who chose [Option A] in Episode 1 were 3x more likely to choose [Option C] here."
- **Friend comparison** (if friend data available): "You and [Friend] diverged at the gate. They went left. You went through."
- **Alignment population**: "23% of all players share your Disruptor profile."

### COMPARISON INTEGRITY RULES

- Percentages must be accurate and updated in real time (or near-real-time)
- Comparisons must not shame or judge. "Only 8% chose this" is curiosity, not punishment
- Correlations must be genuine (statistically significant), not manufactured
- Friend comparisons require mutual opt-in
- All comparison data collection is disclosed in the app's privacy policy

---

## D. THE REPLAY INVITATION

The share card includes a subtle replay hook. This is the mechanism that turns social sharing into replay behavior.

### REPLAY HOOK FORMATS

1. **Divergence hook** (when friend data available): "Your story diverged from [Friend]'s at [choice moment]. Play again to see their path."

2. **Hidden content hook**: "There were [N] moments in this episode you never saw." (N = total branching outcomes minus the ones the player saw. For 4 branching choices with 3 options each, a single playthrough sees 4 of 12 outcomes. N = 8.)

3. **Alignment curiosity hook**: "What would a [opposite alignment label] do at the gate?" (e.g., "What would an Architect do at the gate?" for a Disruptor player)

4. **Consequence preview**: "Players who chose differently at [choice moment] saw something you didn't in Episode [N+1]." (Only shown after the player has completed enough episodes to have missed downstream consequences.)

### REPLAY INVITATION RULES

- The replay invitation is NEVER aggressive. It is a whisper, not a shout.
- It appears BELOW the share card, smaller, as an afterthought. The share card is the main event.
- It does not say "Replay now." It creates curiosity. The player decides to replay because they want to, not because they were told to.
- The hidden content count must be accurate.
- The friend divergence hook requires the friend to have actually played and diverged. No fake divergence.

---

## IMPLEMENTATION DEPENDENCIES

This deliverable requires:
1. **Runtime state persistence** (Deliverable 8 — the state data the share card reads)
2. **Aggregate data pipeline** (engineering — collects choice percentages across players)
3. **Share card visual design** (product/design team — Shervin, Mike, Spencer)
4. **Social platform integration** (engineering — sharing APIs)
5. **Friend graph** (optional — for friend comparison features)

The aggregate data pipeline and share card visuals can be worked on in parallel with the pipeline upgrade (Deliverables 1-8). The Social Echo Layer ships AFTER the core experience is live and generating player data.

---

## PIPELINE ADDITION FOR SOCIAL ECHO

One addition to the pipeline is required: the Session Packet (Phase 5 output) must include pre-authored "defining lines" for each branching choice path. These are the provocative one-liners that appear on the share card.

Add to Phase 5 output, per branching choice:

```
DEFINING LINES (for Social Echo share card):
  Path A: "[Provocative one-liner in the author's voice — what the player did, rendered as a gut punch]"
  Path B: "[Same]"
  Path C: "[Same]"
```

These lines should be written during Phase 5 Choice Design and verified during Phase 8 Editorial Verification (add to the voice audit — are the defining lines in the author's voice?).

---

## END OF DELIVERABLE 9
