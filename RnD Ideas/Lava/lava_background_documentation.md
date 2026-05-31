# Apple Music-Style Lava Background

This document explains the structure of the clean animated lava background, how the visual effect works, and how to connect it to an app so the colors can change dynamically.

---

## 1. Core Structure

The background is built from three visual layers:

```html
<div class="lava-background"></div>
<div class="grain"></div>
<div class="vignette"></div>
```

### Layer Purpose

| Layer | Purpose |
|---|---|
| `.lava-background` | Main animated color field. This creates the fluid lava-lamp movement. |
| `.grain` | Adds a subtle texture/noise layer so the background feels less flat. |
| `.vignette` | Adds darker edges and depth, making the center feel softer and more cinematic. |

Your actual app content should sit above these layers.

Example:

```html
<div class="lava-background"></div>
<div class="grain"></div>
<div class="vignette"></div>

<main class="app">
  <!-- Your app UI goes here -->
</main>
```

---

## 2. Z-Index Structure

The background layers should stay behind the app content.

```css
.lava-background,
.grain,
.vignette {
  position: fixed;
  inset: 0;
  z-index: 0;
  pointer-events: none;
}

.app {
  position: relative;
  z-index: 10;
}
```

### Important Notes

- `position: fixed` keeps the background locked to the viewport.
- `inset: 0` makes each layer fill the screen.
- `pointer-events: none` prevents the background from blocking clicks.
- App content needs a higher `z-index` than the background layers.

---

## 3. Color Variables

Use CSS variables to control the palette.

Recommended structure:

```css
:root {
  --c1: 255, 45, 85;   /* pink/red */
  --c2: 255, 122, 24;  /* orange */
  --c3: 166, 51, 255;  /* violet */
  --c4: 22, 119, 255;  /* blue */
  --bg: 8, 2, 13;      /* deep black/purple */
}
```

These are RGB values without `rgb()` or `rgba()` wrapped around them.

That lets you use them like this:

```css
rgba(var(--c1), 0.92)
rgba(var(--c2), 0.78)
rgba(var(--c3), 0.70)
rgba(var(--c4), 0.68)
```

### Why RGB Variables?

Using raw RGB variables makes opacity easy to control.

Instead of writing this:

```css
background: rgba(255, 45, 85, 0.92);
```

You can write this:

```css
background: rgba(var(--c1), 0.92);
```

Then your JavaScript can update the palette without rewriting the whole gradient.

---

## 4. Main Lava Background

The main background is usually made from layered radial gradients.

Example:

```css
.lava-background {
  background:
    radial-gradient(circle at 18% 22%, rgba(var(--c1), 0.92), transparent 34%),
    radial-gradient(circle at 80% 18%, rgba(var(--c2), 0.78), transparent 32%),
    radial-gradient(circle at 62% 78%, rgba(var(--c3), 0.70), transparent 38%),
    radial-gradient(circle at 28% 82%, rgba(var(--c4), 0.68), transparent 34%),
    linear-gradient(135deg, rgb(var(--bg)), #16051d 45%, #05020a);

  filter: blur(34px) saturate(1.55) contrast(1.08);
  transform: scale(1.15);
  animation: drift 18s ease-in-out infinite alternate;
}
```

### What Each Part Does

| CSS Part | Meaning |
|---|---|
| `radial-gradient(...)` | Creates soft glowing color blobs. |
| `transparent` | Lets blobs fade smoothly into each other. |
| `linear-gradient(...)` | Adds a dark base underneath the colors. |
| `filter: blur(...)` | Softens the blobs into a fluid Apple Music-style wash. |
| `saturate(...)` | Makes the colors more intense. |
| `contrast(...)` | Adds depth and separation between tones. |
| `transform: scale(1.15)` | Enlarges the layer so blur does not reveal hard edges. |
| `animation: drift...` | Moves the whole background slowly. |

---

## 5. Animation Controls

The smooth movement comes from CSS keyframes.

Example:

```css
@keyframes drift {
  0% {
    transform: scale(1.15) translate3d(-2%, -2%, 0) rotate(0deg);
  }

  50% {
    transform: scale(1.22) translate3d(2%, 1%, 0) rotate(8deg);
  }

  100% {
    transform: scale(1.18) translate3d(-1%, 3%, 0) rotate(-6deg);
  }
}
```

### How to Adjust Motion

```css
animation: drift 18s ease-in-out infinite alternate;
```

| Value | What It Does |
|---|---|
| `18s` | Animation duration. Bigger number = slower movement. |
| `ease-in-out` | Smooth acceleration and deceleration. |
| `infinite` | Loops forever. |
| `alternate` | Plays forward, then backward, avoiding harsh resets. |

Recommended speeds:

```css
/* Slow and elegant */
animation: drift 28s ease-in-out infinite alternate;

/* More active */
animation: drift 12s ease-in-out infinite alternate;

/* Very calm */
animation: drift 40s ease-in-out infinite alternate;
```

---

## 6. Blur, Saturation, and Intensity

This line controls much of the Apple Music-like softness:

```css
filter: blur(34px) saturate(1.55) contrast(1.08);
```

### Tuning Guide

| Property | Increase For | Decrease For |
|---|---|---|
| `blur()` | Softer, more liquid background | Sharper blobs |
| `saturate()` | Stronger album-like colors | More muted colors |
| `contrast()` | More dramatic separation | Smoother, flatter blend |

Suggested variants:

```css
/* Softer */
filter: blur(48px) saturate(1.4) contrast(1.02);

/* More vivid */
filter: blur(34px) saturate(1.9) contrast(1.12);

/* Minimal and subtle */
filter: blur(60px) saturate(1.15) contrast(1);
```

---

## 7. Grain Layer

The grain layer adds subtle texture.

```css
.grain {
  opacity: 0.12;
  background-image:
    radial-gradient(circle at 20% 30%, rgba(255,255,255,0.12) 0 1px, transparent 1px),
    radial-gradient(circle at 80% 70%, rgba(255,255,255,0.08) 0 1px, transparent 1px);
  background-size: 18px 18px, 22px 22px;
  mix-blend-mode: overlay;
}
```

### Adjusting Grain

| Change | Result |
|---|---|
| Increase `opacity` | More visible texture |
| Decrease `opacity` | Cleaner, smoother look |
| Increase `background-size` | Larger, more spread-out grain |
| Decrease `background-size` | Denser texture |

For a clean app UI, keep grain subtle:

```css
opacity: 0.06;
```

---

## 8. Vignette Layer

The vignette darkens the edges.

```css
.vignette {
  background:
    radial-gradient(circle at center, transparent 0%, rgba(0,0,0,0.18) 55%, rgba(0,0,0,0.62) 100%);
}
```

### Why It Matters

The vignette makes the background feel deeper and helps foreground UI remain readable.

For a brighter version:

```css
.vignette {
  background: radial-gradient(circle at center, transparent 0%, rgba(0,0,0,0.12) 60%, rgba(0,0,0,0.42) 100%);
}
```

For a darker cinematic version:

```css
.vignette {
  background: radial-gradient(circle at center, transparent 0%, rgba(0,0,0,0.25) 50%, rgba(0,0,0,0.75) 100%);
}
```

---

## 9. Connecting Colors to Your App

Your app can update the background colors dynamically with JavaScript.

Example:

```js
function setLavaColors(colors) {
  document.documentElement.style.setProperty('--c1', colors.c1);
  document.documentElement.style.setProperty('--c2', colors.c2);
  document.documentElement.style.setProperty('--c3', colors.c3);
  document.documentElement.style.setProperty('--c4', colors.c4);
}

setLavaColors({
  c1: '255, 45, 85',
  c2: '255, 122, 24',
  c3: '166, 51, 255',
  c4: '22, 119, 255'
});
```

### Example: Change Colors When Album Changes

```js
const albumPalettes = {
  sunset: {
    c1: '255, 45, 85',
    c2: '255, 122, 24',
    c3: '166, 51, 255',
    c4: '22, 119, 255'
  },
  ocean: {
    c1: '0, 190, 255',
    c2: '0, 95, 255',
    c3: '90, 70, 255',
    c4: '0, 255, 200'
  },
  forest: {
    c1: '0, 180, 120',
    c2: '80, 220, 120',
    c3: '20, 90, 70',
    c4: '180, 255, 160'
  }
};

function applyAlbumPalette(albumName) {
  const palette = albumPalettes[albumName];
  if (!palette) return;

  Object.entries(palette).forEach(([key, value]) => {
    document.documentElement.style.setProperty(`--${key}`, value);
  });
}

applyAlbumPalette('ocean');
```

---

## 10. Smooth Color Transitions

To make album color changes feel smooth, add transitions to the background layer.

```css
.lava-background {
  transition: background 900ms ease, filter 900ms ease;
}
```

However, note that complex gradient transitions can vary by browser.

For the smoothest production result, transition a semi-transparent overlay or use two background layers and crossfade between them.

Simple approach:

```css
.lava-background {
  transition: opacity 700ms ease, filter 700ms ease;
}
```

Advanced approach:

- Keep one current lava layer.
- Create a second lava layer with the new colors.
- Fade the second layer in.
- Remove or recycle the old layer after the transition.

---

## 11. React Integration Example

```jsx
import { useEffect } from 'react';
import './lavaBackground.css';

export function LavaBackground({ palette }) {
  useEffect(() => {
    if (!palette) return;

    document.documentElement.style.setProperty('--c1', palette.c1);
    document.documentElement.style.setProperty('--c2', palette.c2);
    document.documentElement.style.setProperty('--c3', palette.c3);
    document.documentElement.style.setProperty('--c4', palette.c4);
  }, [palette]);

  return (
    <>
      <div className="lava-background" />
      <div className="grain" />
      <div className="vignette" />
    </>
  );
}
```

Usage:

```jsx
<LavaBackground
  palette={{
    c1: '255, 45, 85',
    c2: '255, 122, 24',
    c3: '166, 51, 255',
    c4: '22, 119, 255'
  }}
/>
```

---

## 12. Performance Notes

This background is CSS-only, so it is lighter than a full WebGL shader.

Still, blur and animated gradients can be expensive on low-end devices.

Recommended performance helpers:

```css
.lava-background {
  will-change: transform, filter;
}
```

Also consider reducing blur on mobile:

```css
@media (max-width: 768px) {
  .lava-background {
    filter: blur(26px) saturate(1.35) contrast(1.04);
    animation-duration: 26s;
  }
}
```

Respect reduced-motion preferences:

```css
@media (prefers-reduced-motion: reduce) {
  .lava-background {
    animation: none;
  }
}
```

---

## 13. Quick Customization Reference

| Goal | Change This |
|---|---|
| Change colors | Update `--c1`, `--c2`, `--c3`, `--c4` |
| Slower movement | Increase animation duration, e.g. `18s` to `32s` |
| More liquid softness | Increase `blur()` |
| More vivid colors | Increase `saturate()` |
| Darker edges | Increase vignette opacity |
| Cleaner look | Reduce grain opacity |
| Better readability | Add stronger vignette or dark overlay |

---

## 14. Minimal Drop-In Version

```html
<div class="lava-background"></div>
<div class="grain"></div>
<div class="vignette"></div>
```

```css
:root {
  --c1: 255, 45, 85;
  --c2: 255, 122, 24;
  --c3: 166, 51, 255;
  --c4: 22, 119, 255;
  --bg: 8, 2, 13;
}

.lava-background,
.grain,
.vignette {
  position: fixed;
  inset: 0;
  z-index: 0;
  pointer-events: none;
}

.lava-background {
  background:
    radial-gradient(circle at 18% 22%, rgba(var(--c1), 0.92), transparent 34%),
    radial-gradient(circle at 80% 18%, rgba(var(--c2), 0.78), transparent 32%),
    radial-gradient(circle at 62% 78%, rgba(var(--c3), 0.70), transparent 38%),
    radial-gradient(circle at 28% 82%, rgba(var(--c4), 0.68), transparent 34%),
    linear-gradient(135deg, rgb(var(--bg)), #16051d 45%, #05020a);
  filter: blur(34px) saturate(1.55) contrast(1.08);
  transform: scale(1.15);
  animation: drift 18s ease-in-out infinite alternate;
  will-change: transform, filter;
}

.grain {
  opacity: 0.08;
  mix-blend-mode: overlay;
  background-image:
    radial-gradient(circle at 20% 30%, rgba(255,255,255,0.12) 0 1px, transparent 1px),
    radial-gradient(circle at 80% 70%, rgba(255,255,255,0.08) 0 1px, transparent 1px);
  background-size: 18px 18px, 22px 22px;
}

.vignette {
  background: radial-gradient(circle at center, transparent 0%, rgba(0,0,0,0.18) 55%, rgba(0,0,0,0.62) 100%);
}

@keyframes drift {
  0% {
    transform: scale(1.15) translate3d(-2%, -2%, 0) rotate(0deg);
  }

  50% {
    transform: scale(1.22) translate3d(2%, 1%, 0) rotate(8deg);
  }

  100% {
    transform: scale(1.18) translate3d(-1%, 3%, 0) rotate(-6deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .lava-background {
    animation: none;
  }
}
```
