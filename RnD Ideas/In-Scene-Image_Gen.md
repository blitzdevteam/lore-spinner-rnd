Here’s the full rewritten version with the improvements folded in.

````md
Yes, exactly: **do not burden the narrator AI**.

This should run **underneath the runtime**, as a separate optional visual layer. The narrator should not be asked to think about image prompts, placement, generation timing, artifact UI, or visual logic. The narrator’s only job remains storytelling.

The image system should listen to what the runtime already has.

The clean principle is:

> **Narration/state = truth.**  
> **Artifact image = visual echo of truth.**

The artifact image reflects the story state. It does not create story state.

---

## What this feature is

This is not “image generation during gameplay.”

It is:

> **Reactive Story Artifacts**

Generated objects should feel like small pieces of the story have leaked into the interface.

Not scene illustrations.  
Not visual novel panels.  
Not inventory cards.  
Not decoration for every turn.

They are diegetic visual traces of meaningful story state.

Examples:

```text
White Rabbit pocket watch
marmalade jar
DRINK ME bottle
golden key
tiny door
musical pipe
upside-down map
playing card
hedgehog ball
bloodstained phone
split D20
Polaroid
````

The rule:

> **Generate evidence, not illustration.**

---

## What “click” means

When a floating artifact appears, the player may click or tap it to inspect it.

This should be lightweight and optional.

Example:

The narration creates a pipe artifact.

The UI shows the object itself, floating in the right-side atmospheric space, with no visible card, frame, title, or badge.

If the player clicks it, a minimal inspection popover can appear:

```text
Musical Pipe

A long brown pipe that blows marmalade-scented bubbles and plays three strange notes.
```

That is all.

In V1, clicking does **not**:

```text
send an action to the narrator
advance the scene
start another narrator call
change world state
trigger gameplay
```

Clicking only means:

> inspect the generated object / memory artifact

Later, if we want, a “Use this object” action could paste text into the input:

```text
I take out the musical pipe and use it.
```

Then the narrator handles it naturally as a normal player action.

But for now:

> Click = inspect only.

---

## Where it runs

This should run **post-runtime async**.

The story response should appear immediately. Artifact generation should happen after the narrator has already finished.

Flow:

```text
Narrator AI returns:
- response
- choices
- state_delta
- session_memory_update

Runtime saves:
- conversation
- world_state
- session_memory

Artifact system runs after:
- looks at state_delta.items
- looks at state_delta.notes
- optionally looks at session_memory_update
- decides if a visual artifact should be generated
- creates a pending artifact record
- dispatches image job in the background
- UI shows the artifact when ready
```

The narrator AI does **zero extra work**.

No prompt burden.
No extra narrator tokens.
No slower text response.
No interference with pacing.
No extra responsibility inside the narration system prompt.

This is a silent observer layer.

---

## Do not put this into the narrator system prompt

Do **not** add instructions like:

```text
Describe image prompts.
Decide what images to generate.
Create visual artifact descriptions.
Choose image placement.
Think about generated images.
```

That pollutes the narrator and weakens the storytelling.

Use the existing runtime outputs:

```text
state_delta.items
state_delta.notes
state_delta.knowledge
session_memory_update
```

The narrator already creates story truth. The artifact layer simply observes it.

---

## Best V1

For V1, only generate artifacts from `state_delta.items`.

If the AI says the player acquired or meaningfully encountered an item, generate a visual artifact.

Example:

```json
{
  "state_delta": {
    "items": ["musical pipe"],
    "knowledge": [
      "The pipe blows marmalade-scented recursive bubbles and plays three strange notes."
    ],
    "notes": [
      "Alice acquired a strange musical pipe during the fall."
    ]
  }
}
```

Then a separate backend process says:

```text
New item detected: musical pipe.
Generate isolated story artifact image.
```

This happens after the text response has already rendered.

Good V1 triggers:

```text
Alice picks up the musical pipe
Alice takes the golden key
Alice opens the marmalade jar
Alice receives the hedgehog ball
Lucas finds the bloodstained phone
The D20 splits
Jaxton reveals the Polaroid
```

Avoid triggering for:

```text
generic atmosphere
every mentioned object
background scenery
objects the player has not interacted with
objects that do not matter
```

---

## Visual treatment

Render generated in-scene images as **floating diegetic artifacts**, not UI cards.

The object should appear as a borderless isolated image in the unused side whitespace of the story screen, usually the right margin on desktop.

It should not have:

```text
card frame
title badge
label
“discovered” text
hard container
rectangular boundary
inventory styling
```

It should feel like:

> the story has leaked a physical trace into the interface.

Visual direction:

```text
- isolated object only
- transparent PNG/WebP preferred
- slight tilt/rotation, around -8deg to +8deg
- soft teal/cyan atmospheric haze behind the object
- subtle smoky/misty radial glow
- very light particle/speckle texture around it
- no rectangular boundary
- floats above background but behind primary interaction layer
- never blocks story text, choices, or input bar
- low-contrast enough to feel ambient
- visible enough to be noticed
- magical, cinematic, diegetic
- never like an inventory tooltip
```

Motion:

```text
- slow float up/down, around 4–8px
- very subtle rotation drift, around 1–2deg
- optional slow opacity breathing, around 0.85–1
- haze pulses separately and more slowly
- calm, not gamey
- never distracting
- respect prefers-reduced-motion
```

Placement:

```text
Desktop:
- place in the right whitespace beside the story text
- vertically align around the middle of the current narration block
- never insert inline inside the story text
- never push layout
- absolutely positioned as atmospheric overlay

Mobile/tablet:
- hide by default
- or show very faintly only when safe space exists
- never interfere with reading or input
```

Important rule:

> The text remains the main experience. The artifact is a visual echo.

---

## Only one active floating artifact

Only one primary scene artifact should float in the story view at a time.

If multiple artifacts exist:

```text
show the newest or most dramatically relevant one
move older artifacts into memory/inventory shelf later
do not clutter the reading space
```

This keeps the interface elegant and literary.

---

## Artifact intensity

Not every artifact should feel equally powerful.

Use three intensity levels:

```text
subtle
present
charged
```

### subtle

For ordinary but relevant objects.

```text
small size
low opacity
gentle haze
minimal particles
```

Example:

```text
ordinary key
map fragment
plain playing card
```

### present

For active story objects.

```text
normal size
visible glow
soft float
clear silhouette
```

Example:

```text
DRINK ME bottle
musical pipe
White Rabbit pocket watch
```

### charged

For magical, cursed, emotional, or session-defining objects.

```text
stronger haze
slightly brighter pulse
more atmosphere
still not distracting
```

Example:

```text
split D20
bloodstained phone
sentient jar
session-end relic
```

---

## Fail-safe behavior

The artifact layer must never block gameplay.

If image generation is pending, slow, or fails:

```text
story continues normally
choices remain usable
input remains usable
runtime does not wait
narrator is not re-called
no error interrupts the player
```

Artifact generation is optional enhancement.

It must never become a dependency for narration, state, choices, or session progression.

---

## Exact architecture

```text
1. Narrator call
   returns response, choices, state_delta, session_memory_update

2. Controller saves turn
   conversation_history
   world_state
   session_memory

3. ArtifactDetector checks state_delta.items

4. If new meaningful item exists:
   create visual_artifact record with status = pending
   dispatch GenerateArtifactImageJob

5. Frontend receives normal narration response immediately

6. Frontend also receives pending_artifacts if any:
   [{ id, name, status: "pending" }]

7. UI may show a subtle “forming” atmospheric placeholder
   or show nothing until ready

8. Job finishes
   artifact status = ready
   image_url saved

9. Frontend polls or listens
   artifact fades into the side whitespace
```

---

## Suggested `visual_artifacts` shape

Do not put generated image URLs directly inside `world_state`.

Use a separate artifact record.

Production shape:

```json
{
  "id": "artifact_ulid",
  "chaos_session_id": "...",
  "story_id": "...",
  "story_session_number": 1,
  "source_turn": 4,
  "artifact_type": "object",
  "name": "musical pipe",
  "description": "A long brown pipe that blows marmalade-scented recursive bubbles and plays three strange notes.",
  "status": "pending | generating | ready | failed",
  "image_url": "",
  "prompt": "",
  "style_seed": "alice_carroll_story_artifact",
  "intensity": "present",
  "is_player_created": true,
  "is_collectible": false
}
```

Why separate storage?

Because artifacts are not only state. They are:

```text
visual assets
cacheable objects
inspectable UI elements
possible collectibles
session memory traces
future inventory/memory shelf items
```

---

## Artifact detector

Start simple.

V1 detector:

```php
if (!empty($stateDelta['items'])) {
    dispatch(new GenerateStoryArtifactJob(...));
}
```

Better V1 detector:

```php
$newItems = detectNewItems($stateDelta['items'], $existingWorldState['items']);

foreach ($newItems as $item) {
    if (isMeaningfulArtifact($item)) {
        createPendingVisualArtifact($item);
        dispatch(new GenerateStoryArtifactJob($artifact));
    }
}
```

Later, a small classifier can decide visual worth:

```json
{
  "should_generate": true,
  "artifact_name": "musical pipe",
  "artifact_type": "held_object",
  "visual_description": "A long brown pipe with faint marmalade-scented bubbles curling from the bowl.",
  "intensity": "present",
  "reason": "The player acquired a new story object with unique behavior."
}
```

But do not use a classifier in V1 unless needed.

---

## Image prompt template

Use a controlled prompt template outside the narrator.

```text
Create an isolated story artifact on a transparent background.

Object:
{artifact_name}

Story context:
{short_context}

Visual details:
{visual_description}

Style:
- literary story artifact
- tactile, strange, atmospheric
- isolated object only
- transparent background
- no character
- no full scene
- no background environment
- no text unless the object canonically has text
- readable silhouette
- subtle magical/cinematic presence
- suitable for floating over a dark reading interface
- consistent with {story_style}
```

For Alice:

```text
whimsical Victorian storybook object, strange but elegant, Carroll-like absurdity, soft antique texture, delicate surreal detail
```

For Red Hallow:

```text
cursed horror evidence object, worn material, unsettling realism, low light, no gratuitous gore, haunted-house atmosphere
```

---

## CSS direction

```css
.scene-artifact-layer {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 0;
  overflow: hidden;
}

.story-content,
.choice-panel,
.input-bar {
  position: relative;
  z-index: 2;
}

.scene-artifact {
  position: absolute;
  right: 9vw;
  top: 34%;
  width: clamp(90px, 9vw, 150px);
  pointer-events: auto;
  z-index: 1;
  transform: rotate(-7deg);
  opacity: 0.92;
  filter:
    drop-shadow(0 0 18px rgba(72, 245, 220, 0.22))
    drop-shadow(0 12px 28px rgba(0, 0, 0, 0.35));
  animation: artifactFloat 7s ease-in-out infinite;
}

.scene-artifact-haze {
  position: absolute;
  inset: -80px;
  z-index: -1;
  background:
    radial-gradient(
      circle,
      rgba(72, 245, 220, 0.18),
      rgba(72, 245, 220, 0.05) 34%,
      transparent 68%
    );
  filter: blur(28px);
  opacity: 0.8;
  pointer-events: none;
  animation: artifactHaze 10s ease-in-out infinite;
}

@keyframes artifactFloat {
  0%, 100% {
    transform: translateY(0) rotate(-7deg);
  }

  50% {
    transform: translateY(-7px) rotate(-5deg);
  }
}

@keyframes artifactHaze {
  0%, 100% {
    opacity: 0.55;
    transform: scale(0.96);
  }

  50% {
    opacity: 0.85;
    transform: scale(1.06);
  }
}

@media (prefers-reduced-motion: reduce) {
  .scene-artifact,
  .scene-artifact-haze {
    animation: none;
  }
}
```

Note: if `pointer-events: auto` is used on the artifact for click/inspect, keep the surrounding layer `pointer-events: none` so it never blocks reading or input.

---

## Should it affect runtime?

Not in V1.

It should be **decorative-persistent**, not mechanical.

Meaning:

```text
The artifact image reflects state.
It does not create state.
```

The pipe exists because narrator state says Alice has a pipe.

The image does not create the pipe.

The generated artifact can be inspected, remembered, and later shown again, but it should not change gameplay by itself.

---

## Later versions

### V2: Memory shelf

Add a subtle memory/artifact shelf outside the main reading area.

```text
Artifacts
- Musical Pipe
- Golden Key
- DRINK ME Bottle
- Hedgehog Ball
```

The floating artifact view shows only one object. The shelf holds the collection.

### V3: Use artifact

Clicking an artifact can offer:

```text
Use this
Inspect
Dismiss
```

“Use this” should simply populate the input with:

```text
I take out the musical pipe and use it.
```

The narrator then handles it naturally.

### V4: Session-end visual echo

At session completion, generate one symbolic relic based on `session_memory`.

Not a full scene.

Example:

```text
The Pipe That Asked Questions
The Watch That Ran Late
The Chair That Waited
The Die That Split
```

This becomes a session memory object.

---

## Final recommendation

Build it as a **silent floating artifact layer**:

> The narrator creates story state.
> The artifact system observes story state.
> Images generate asynchronously.
> UI displays one borderless floating object as diegetic story evidence.
> Runtime is not slowed.
> The narrator is not burdened.
> The text remains the main experience.

The key implementation sentence:

> **Render generated in-scene images as borderless floating story artifacts with smoky teal haze, subtle tilt, and slow ambient drift, positioned in unused side whitespace without affecting layout.**

```
```
