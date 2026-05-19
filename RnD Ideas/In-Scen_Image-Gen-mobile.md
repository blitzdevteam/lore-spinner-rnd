For mobile, I would **not show the floating artifact by default inside the reading area**.

Mobile has no safe whitespace. If you float objects over the text, it can quickly feel cluttered or cheap. The mobile rule should be:

> **Desktop = ambient floating artifact.**
> **Mobile = subtle artifact reveal + collapsible memory tray.**

## Mobile behavior

### 1. Do not overlay the story text by default

Avoid this on mobile:

```text
artifact floating behind/over paragraphs
artifact beside choices
artifact near input bar
```

The text column is the whole experience. Protect it.

### 2. Show a small “artifact formed” moment after narration

When an artifact is ready, show a very small, non-blocking reveal between narration and choices or just above the input bar:

```text
A story artifact has formed
[ tiny object thumbnail ]
```

But keep it soft and diegetic. No big card.

Better copy:

```text
Something from the story lingers.
```

Or no copy at all, just the object shimmer.

### 3. Use a bottom artifact tray

Best mobile pattern:

```text
Narration
What do you do?
Choices
Input bar
Artifact tray handle
```

A tiny handle/icon can sit above the input or near the lower corner.

Tap it:

```text
opens bottom sheet
shows current artifact + previous artifacts
```

This avoids blocking the story.

## Exact mobile plan

### Default mobile state

```text
- no floating object over text
- no right-side artifact
- no layout shift
- no forced visual
- show only a tiny artifact glimmer/handle if artifact exists
```

### On artifact generated

```text
- text response appears immediately
- artifact generation finishes later
- a small soft shimmer appears near the bottom/right
- optional tiny thumbnail fades in for 2–3 seconds
- then collapses into artifact tray handle
```

### On tap

Open bottom sheet:

```text
Artifact
Musical Pipe

A long brown pipe that blows marmalade-scented bubbles and plays three strange notes.

[Close]
```

Later:

```text
[Use this]
```

But V1 should only inspect.

## Mobile UI copy

Use minimal language:

```text
Story artifact
```

or more atmospheric:

```text
Something lingers
```

I prefer **no persistent label**. Just a small icon/glow.

## Mobile visual treatment

```text
- thumbnail size: 44–72px
- max expanded image: 160–220px
- transparent object
- same teal haze, much softer
- no animation heavier than fade/scale
- avoid constant floating
- disable particles on mobile
- respect reduced motion
```

## Mobile placement

Best options ranked:

### 1. Bottom sheet tray

Most practical.

```text
Small artifact glyph/thumbnail near bottom-right, above input safe area.
Tap opens bottom sheet.
```

### 2. Temporary inline reveal

After narration, before choices:

```text
[small floating object fades in]
```

Then it disappears into tray.

### 3. Background ghost behind text

Only for special session-end artifacts, very faint.

Not for regular objects.

## CSS-ish mobile behavior

```css
@media (max-width: 768px) {
  .scene-artifact-layer {
    display: none;
  }

  .artifact-tray-trigger {
    position: fixed;
    right: 18px;
    bottom: calc(env(safe-area-inset-bottom) + 86px);
    width: 52px;
    height: 52px;
    z-index: 20;
  }

  .artifact-tray-trigger img {
    width: 44px;
    height: 44px;
    object-fit: contain;
    filter:
      drop-shadow(0 0 12px rgba(72, 245, 220, 0.22))
      drop-shadow(0 8px 18px rgba(0, 0, 0, 0.35));
  }

  .artifact-bottom-sheet {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 40;
    padding: 20px 20px calc(env(safe-area-inset-bottom) + 20px);
    border-radius: 24px 24px 0 0;
    backdrop-filter: blur(18px);
  }
}
```

## Add this to the spec

```md
## Mobile behavior

Mobile should not use the desktop floating-side artifact treatment by default because there is no safe whitespace and the text must remain primary.

On mobile, artifacts become a subtle inspectable tray:

- Do not overlay artifacts over story text.
- Do not push layout.
- Do not animate constantly.
- Show a small artifact glimmer or thumbnail only after an artifact is ready.
- Place the artifact trigger near the bottom-right above the input safe area.
- Tapping opens a bottom sheet with the artifact image and short description.
- V1 inspection only; no gameplay action is triggered.
- Hide the desktop artifact layer under 768px.
- Disable particles and heavy haze on mobile.
- Respect `prefers-reduced-motion`.
- The artifact system remains optional. If it fails or is pending, the story continues normally.
```

So the final product rule:

> **Desktop artifacts haunt the whitespace. Mobile artifacts live in a quiet tray.**
