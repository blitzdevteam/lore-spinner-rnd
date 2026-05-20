# Making Chaos Mode the Default Game Experience

**Goal:** When a user clicks any story card — on the homepage, library, or anywhere in the app — the game opens in Chaos Mode instead of the legacy Story Guard runtime. The user never sees a story picker or model selector. They click, loading happens, the narration starts.

Story Guard (`GameController`, `User/Games/Show.vue`) remains fully intact in the codebase and can be reactivated later. Nothing is deleted.

---

## What the current flow looks like

```
Story card click
    → /stories/{slug}          StoryController::show  → Stories/Show.vue
    → [Start button click]
    → POST /user/games          GameController::store  → creates default Game row
    → /user/games/{id}          User/Games/Show.vue    ← Story Guard runtime
```

## What the new flow will look like

```
Story card click
    → /chaos-mode?story={slug}  ChaosModeController::show  → ChaosMode.vue
    → [auto-start on mount — no button click needed]
    → POST /chaos-mode/start    (internal, no page change)
    → ChaosMode.vue game view   ← Chaos runtime, narration begins
```

---

## Changes required — in implementation order

### 1. `app/Http/Controllers/ChaosMode/ChaosModeController.php`

**Method:** `show()`

Add a `Request $request` parameter. Read the optional `?story=` query param, validate it is a known chaos slug, and pass it as `initialStory` to the Inertia page.

```php
public function show(Request $request): Response
{
    $initialStory = null;
    $storyParam   = $request->query('story');

    if (is_string($storyParam) && in_array($storyParam, ChaosStoryConfig::slugs(), true)) {
        $initialStory = $storyParam;
    }

    $configured = ChaosStoryConfig::all();
    $slugs      = array_column($configured, 'slug');

    $stories = Story::query()
        ->whereIn('slug', $slugs)
        ->with(['adaptation', 'adaptation.sessionAdaptations', 'media'])
        ->get(['id', 'title', 'slug']);

    $payload = array_map(function (array $row) use ($stories) {
        // ... existing payload-building code unchanged ...
    }, $configured);

    return inertia('ChaosMode', [
        'stories'      => $payload,
        'initialStory' => $initialStory,   // ← NEW: null when no ?story= param
    ]);
}
```

The only real addition is reading `$storyParam`, validating it against `ChaosStoryConfig::slugs()`, and passing `initialStory` into the Inertia payload. All the existing story-loading logic stays exactly as is.

---

### 2. `resources/js/pages/ChaosMode.vue`

Three small changes to the `<script setup>` block.

**a) Add `initialStory` prop to `defineProps`**

```ts
const props = defineProps<{
    stories: ChaosStoryOption[];
    initialStory?: string | null;   // ← NEW
}>();
```

**b) Initialise `selectedStorySlug` from the prop when available**

Replace the current line:
```ts
// BEFORE
const selectedStorySlug = ref<string>(firstAvailableSlug.value);
```

With:
```ts
// AFTER
const selectedStorySlug = ref<string>(
    props.initialStory ?? firstAvailableSlug.value
);
```

**c) Auto-start on mount when `initialStory` was provided**

Add an `onMounted` call directly after the existing `watch` blocks:

```ts
import { computed, nextTick, onMounted, ref, watch } from 'vue';

// ... existing watches ...

onMounted(() => {
    if (props.initialStory) {
        startWithChoices();
    }
});
```

`startWithChoices()` already handles loading state, error state, and the `started = true` transition — no other changes needed inside that function.

**d) Model selector — hide from user-facing template**

The model/temperature picker in the start-screen template is for internal testing. Gate it behind a flag or simply remove it from the visible UI for now. The `selectedModel` ref stays as-is (defaults to `gpt-5.5`) so it continues to be sent to the API correctly — users just never see the picker.

The simplest approach is to wrap the picker block in a `v-if="false"` comment until the Filament manager panel is built:

```html
<!-- model picker hidden — default controlled by manager settings (TODO: Filament) -->
<!-- <div class="...model picker markup..."> -->
```

---

### 3. `resources/js/components/BaseStoryCard.vue`

Two hrefs need updating.

**Row mode card link (line ~63):**

```ts
// BEFORE
:href="!selectable && isRow ? show(story.slug).url : undefined"

// AFTER
:href="!selectable && isRow ? `/chaos-mode?story=${story.slug}` : undefined"
```

**Column mode "View more" button (line ~217):**

```html
<!-- BEFORE -->
<BaseButton ... :href="show(story.slug).url"> View more </BaseButton>

<!-- AFTER -->
<BaseButton ... :href="`/chaos-mode?story=${story.slug}`"> View more </BaseButton>
```

The `show` import from `@/wayfinder/routes/stories` can be removed from this file if it is no longer used anywhere else in the component.

---

### 4. `resources/js/pages/Index.vue`

**`handleSelectStory` function (line ~41):**

```ts
// BEFORE
const handleSelectStory = (story: StoryInterface) => {
    if (window.innerWidth < 768) {
        router.visit(show(story.slug).url);
        return;
    }
    selectedStory.value = story;
};

// AFTER
const handleSelectStory = (story: StoryInterface) => {
    if (window.innerWidth < 768) {
        router.visit(`/chaos-mode?story=${story.slug}`);
        return;
    }
    selectedStory.value = story;
};
```

The desktop path (`selectedStory.value = story`) feeds the sidebar panel. How the sidebar's "Play" / "View more" action links are wired depends on the redesigned UI — make sure any play/start button in the sidebar also links to `/chaos-mode?story={slug}`.

The `show` import from `@/wayfinder/routes/stories` can be removed from this file if nothing else in `Index.vue` uses it after this change.

---

### 5. `resources/js/pages/Stories/Show.vue`

The story detail page still exists and is reachable directly. Its "Start" button should also route to Chaos Mode so users who land here get the same experience.

**`handleStartStory` function (line ~32):**

```ts
// BEFORE
const handleStartStory = (): void => {
    if (props.existingGameId) {
        router.visit(showGame.url(props.existingGameId));
    } else {
        router.post(store(), {
            story_id: props.story.id,
        });
    }
};

// AFTER
const handleStartStory = (): void => {
    router.visit(`/chaos-mode?story=${props.story.slug}`);
};
```

The `existingGameId` prop and the Story Guard game-resumption logic become unused here. Leave the prop definition in place (the controller still sends it) but the handler no longer needs it. The `store` and `showGame` wayfinder imports can be removed from this file.

---

## What does NOT change

| File / area | Status |
|---|---|
| `GameController` and all Story Guard routes | Untouched — fully functional, just no longer linked from the UI |
| `User/Games/Show.vue` | Untouched |
| `ChaosModeController::start / turn / continue / tts` | Untouched |
| `ChaosStoryConfig.php` | Untouched |
| `routes/web.php` | Untouched — `/stories/{slug}` route still works for direct navigation |
| All DB migrations, models, seeders | Untouched |

---

## Model defaults — current and future

**Right now:** `ChaosMode.vue` initialises `selectedModel` to `'gpt-5.5'` and `selectedTemperature` to `0.9`. These values are sent to `/chaos-mode/start` and `/chaos-mode/turn`. No user action needed; the defaults fire automatically on auto-start.

**Future (Filament manager panel):** When the manager panel is built, `ChaosModeController::show()` will read the configured default from a settings table and pass it as a `defaultModel` prop. `ChaosMode.vue` will initialise `selectedModel` from that prop instead of the hardcoded string. The model picker UI stays in the file as a dev/testing tool — accessible by navigating to `/chaos-mode` directly without a `?story=` param.

---

## Quick smoke-test checklist after implementation

- [ ] Click a story card on the homepage → page navigates to `/chaos-mode?story={slug}`
- [ ] Loading spinner appears immediately (no lobby screen, no "Begin" button)
- [ ] First narration paragraph renders within a few seconds
- [ ] Player input box appears and accepts a choice
- [ ] Navigating to `/chaos-mode` directly (no query param) shows the lobby/selector as before — dev testing path still works
- [ ] Navigating to `/stories/{slug}` directly still works and the "Start" button routes to chaos mode
- [ ] Story Guard routes (`/user/games/*`) still resolve correctly for any hard-coded links or bookmarks

---

## Files touched — summary

```
app/Http/Controllers/ChaosMode/ChaosModeController.php   ← show() accepts ?story= param
resources/js/pages/ChaosMode.vue                         ← initialStory prop + onMounted auto-start
resources/js/components/BaseStoryCard.vue                ← href → /chaos-mode?story={slug}
resources/js/pages/Index.vue                             ← handleSelectStory → chaos route
resources/js/pages/Stories/Show.vue                      ← handleStartStory → chaos route
```

Five files. No new files. No migrations. No route changes.
