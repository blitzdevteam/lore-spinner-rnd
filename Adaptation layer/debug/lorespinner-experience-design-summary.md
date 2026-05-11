# LoreSpinner: Experience Design Summary

## What It Is

LoreSpinner turns great published stories into playable, personalized interactive experiences powered by AI. A player doesn't read Alice in Wonderland; they *become* Alice, making real decisions that shape how the story unfolds and how other characters respond to them, across multiple sessions that remember everything they chose.

It is not a chatbot. It is not a choose-your-own-adventure with fixed paths. It is an authored, AI-performed interactive experience where the source material is the canon, the player's choices are the variable, and every session is uniquely theirs.

---

## The Player's Experience, Turn by Turn

The player opens the experience and is dropped directly into the story. Not a menu, not a summary: an immersive, sensory opening written by an editorial AI that matches the voice and temperature of the source author. For Alice, Session 1 opens like this:

> *"Heat shimmers off the river stones, and your stockings stick to the back of your knees as you lean over the grass…"*

From that first moment, control belongs to the player. They type what they want to do. The AI, playing the role of a cinematic narrator, responds to their specific action, gives it real in-world consequence, and hands three choices back. Each choice represents a clear, single intent. No menus, no numbered lists, no game-speak. Pure prose and action.

A turn looks like this:

- **Player types:** *"You sprint after him and dive for the rabbit-hole the instant you see it."*
- **Narrator responds:** *"You throw yourself forward before your mind can assemble a sensible objection. Grass tears under your palms…"*, and presents three choices for what happens next.

The system is designed to deliver one small, playable slice per response and not skip the player's decision. This is what makes it feel like an interactive experience rather than a story being read at them.

---

## When Players Go Off-Script

Players will try things the story never imagined. They'll reach into pockets, ask characters questions, invent objects, try to leave the scene. The system is designed to treat this as play, not as error.

If a player writes *"I found a bottle in my pocket and drank from it"* during Alice's fall, the narrator acknowledges the specific act, the reaching and the fumbling, before gently grounding it in what actually exists in the scene. It doesn't erase what the player tried. It doesn't tell them they can't do that. It gives the moment a short, local playable beat of real in-world consequence and then lets the scene's natural gravity pull back toward the story.

The player feels heard. They feel like their instincts matter. The story continues.

---

## The Memory Layer: What the Experience Remembers

Every object the player picks up, every condition they're in (shrinking, crying, growing), every fact they learn, every location they move through, all of it is tracked in a live world state that persists across every turn and every session.

If Alice picked up the golden key in Session 1, it is still in her possession at the start of Session 2. The narrator treats this as true and binding. The player does not need to re-establish what they have or where they are.

This world state is injected into the narrator's instructions on every single turn. The narrator is instructed to treat it as the authoritative record of what is true in the world.

---

## The Authored Choices: Where Decisions Have Weight

At key moments in each session, the player faces authored branching choices. These are not invented by the AI; they were designed by a story adaptation system that analyzed the source material for its natural decision points, its moral weight, and its replayability.

For Alice Session 1, the three choices are:

**Choice 1: How she enters Wonderland** (impulsive dive / deliberate preparation / calling out before jumping)
Tracks: *impulse vs. deliberation*

**Choice 2: How she responds to authority abandoning her** (politely asking for help / asserting her name / silently taking the costume and its advantages)
Tracks: *self-definition vs. external labels*

**Choice 3: How she handles identity fracture** (clinging to old rules / treating wrong answers as experiments / simply observing herself)
Tracks: *rule-following vs. rule-testing*

These aren't flavor choices. Each one feeds into a **consequence map** that shapes concrete moments in Sessions 2, 3, and 4. The player who chose to silently put on the gloves will see themselves in a mirror in Session 2 and read "Mary Ann" in their reflection before they hear the name spoken aloud. The player who shouted back will get a confrontation instead.

The story remembers which Alice you are.

---

## The Session Arc

Each session is a contained dramatic experience with a designed opening, a beat map, authored choice moments, and a closing hook that makes the player want to come back.

**Session 1:** Portal and puzzle. Alice falls, navigates the hall of doors, solves the size problem. Emotional journey: restless curiosity, wonder, panic, first triumph. Closes with the identity crisis and a hook into Session 2.

**Session 2:** Errand farce and identity gate. The Caterpillar confrontation. Who is Alice now? Closes with hard-won competence.

**Session 3:** Social survival. Tea party, Queen's garden, authoritarian pressure. Will Alice comply or resist?

**Session 4:** Trial and waking. The court collapses. Alice's accumulated choices determine how loudly she erupts.

Every session opens with a **cold open**, a directed, authored paragraph that re-establishes voice and context, not a generic AI introduction. Every session closes with a **hook** designed to create anticipation, not resolution.

---

## The Adaptation Layer: How a Story Becomes an Experience

Before a story can be played, it goes through a multi-phase adaptation pipeline that runs entirely on AI, guided by a structured design framework:

1. **Format detection:** Is it a novel, screenplay, short story? What narrative tense? Who is the protagonist?
2. **IP audit:** Is it suitable? Does it have natural choice architecture, bounded agency, emotional range, recognizability?
3. **Session mapping:** The story is divided into 3-5 sessions, each with a primary dramatic question and an emotional arc.
4. **Entry point design:** Each session's start event is explicitly identified and a cold open written.
5. **Beat map:** The session's dramatic pacing is structured: what kind of beat, when, with what type of choice arriving.
6. **Branching choice design:** Authored choices per session, each tracking a meaningful dimension of the player's character.
7. **Cross-session consequence mapping:** Every choice branch generates specific payoff descriptions for future sessions.
8. **Session close design:** The exit point is explicitly identified, resolution prose written, and a hook created.

This pipeline means suitable novels, screenplays, and short stories can be made playable. The adaptation is not a summary or retelling; it is an interactive architecture built on top of the source, faithful to its canon while creating genuine player agency within it.

---

## Current State

The core experience loop is validated and working:

- Session cold opens render correctly in the author's voice
- Scene transitions fire reliably when events advance; the player always lands in the right scene
- Off-script player actions are acknowledged and given a short, local playable beat before the story steers back
- Authored branching choices are detected and presented at their designed moments
- Autopilot defaults to the passive path and records the dimension; no authored consequence is lost
- Session close triggers fire at the explicitly authored exit point
- World state (objects, conditions, knowledge, location) persists across turns and sessions

Session 2 through 4 for Alice, and the full adaptation pipeline for new stories, are the next stage of development.

---

## Next Phase

**Tone Profiling:** The system reads the emotional weight behind every choice across sessions, building a quiet profile of how this player engages: cautious or bold, compliant or defiant, emotional or analytical. Not a label, a living pattern that shapes how the story speaks back to them.

**Player Report & Analysis:** At the end of each session, a personal summary: what they chose, what those choices say about them, which dimensions are dominant, what is likely coming for their version of the story. A mirror, not a scorecard.

**Side Quest Control:** A designed layer for emergent play. Off-script actions that show real investment unlock authored micro-branches, short departures with genuine consequence that feed back into the main arc rather than evaporating after two turns.

**Key Object Image Generation:** When a significant object enters the player's world, a bottle labeled DRINK ME, a golden key, a dropped fan, a visual is generated in the style of the source material and surfaced in the experience. The world becomes visible.

**Voice Assistant (Catch-Up & Detail):** A real-time voice layer the player can invoke at any moment to ask questions: *"What did I choose last session?" "Who is the Duchess?" "What do I have with me?"* Conversational, instant, no UI required.

**Session Recap Narration:** Each session opens with a brief narrated recap of the previous session's key moments and choices, written in the author's voice, so returning players are immediately back inside the story without friction.

**Consequence Previews for Authors:** A view for story adapters and editors showing the full consequence map as a branching diagram: which paths exist, which payoffs are authored, which are still gaps, so the design can be improved over time.

---

## The Bet

The bet is that the best stories already have the branching architecture inside them. The choices are latent in every scene where a character faces a threshold. What we built is the system to find those thresholds, design the decisions, track the consequences across a full arc, and perform the story as an AI that is disciplined enough to honor the canon while being responsive enough to make the player feel they are genuinely in it.
