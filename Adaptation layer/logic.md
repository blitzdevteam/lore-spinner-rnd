# Lore Spinner — AI logic (ground truth)

This document describes how artificial intelligence is used in this application: what is read from the database (or storage), what is sent to each model, how structured outputs are shaped, and which controllers, jobs, and agents participate. It is intended as a baseline for redesigning or extending gameplay and creator pipelines.

**Stack (brief):**

- **Laravel AI** (`laravel/ai`): “Agent” classes with structured JSON output via JSON Schema (`NarrationAgent`, `SystemPromptGeneratorAgent`, `OpeningNarrationAgent`, extractors).
- **Prism** (`prism-php/prism`): image generation (`gpt-image-1` via OpenAI) and ad-hoc text in seeders.
- **Direct HTTP**: OpenAI Whisper (`TranscribeController`), ElevenLabs TTS (`TextToSpeechController`).

Default provider config lives in `config/ai.php` (e.g. `default` → `openai`). Text agents in this app explicitly use `#[Model('gpt-5.2')]` on their classes.

---

## 1. Data the runtime cares about

### `stories`

| Field / asset | Role in AI |
|---------------|------------|
| `title`, `teaser` | Story listing, cover prompts, opening job, seeders. |
| `system_prompt` (JSON) | **Runtime narration.** Stored by `SystemPromptGeneratorJob`. Shape: `character_name` (string), `world_rules` (array of strings), `tone_and_style` (string). |
| `opening` | Cinematic HTML opening; from `StoryOpeningGeneratorJob`, not sent into the turn-by-turn narrator. |
| Media `script` | Plain text screenplay; **read from disk** for system-prompt generation and chapter extraction. |
| `category`, `rating` | Cover image prompts (story cover). |

### `chapters`

| Field | Role in AI |
|-------|------------|
| `content` | Full script excerpt for the chapter; line-numbered for event extraction; substring used in chapter cover prompts. |
| `title`, `teaser`, `position` | Metadata; teasers/titles feed chapter cover prompts. |
| `status` | Workflow state for extraction (see jobs). |

### `events`

| Field | Role in AI |
|-------|------------|
| `position`, `title` | Ordering and labels; previous/next event context for narration. |
| `content` | **Canonical screenplay text** for the current event (verbatim dialogue rules in system prompt). |
| `objectives` | Textual “what changed” summary from `EventObjectiveAndAttributeExtractor` job; fed into narration as context, not as new plot authority. |
| `attributes` | JSON array of continuity strings from the same job; passed into narration as JSON. |

### `games`

| Field | Role |
|-------|------|
| `story_id` | Loads story + chapters/events. |
| `current_event_id` | **Single source of truth** for which event the player is in. Updated when narration advances. |

### `prompts` (per-turn records)

| Field | Role |
|-------|------|
| `event_id` | Which event this turn belongs to (updated to the **next** event when advancing). |
| `prompt` | Player text or `__continue__` (continue button). |
| `response` | HTML narration from the model; shown in UI and used for TTS/history. |
| `choices` | Array of 3 strings from the model. |

**Conversation history for the narrator** is rebuilt from `prompts`: last **6** prompts, **reversed** to chronological order, each contributing narrator line + player line (see `PromptController::buildConversationHistory`).

---

## 2. Core gameplay flow (interactive narration)

### Routes (see `routes/routes/user.php`)

- `POST /user/games/{game}/begin` → `GameController@begin` — first turn if no prompts exist.
- `POST /user/games/{game}/prompt` → `PromptController@store` — subsequent turns.

### `GameController`

- **`show`**: Loads `story`, `currentEvent.chapter`, and `prompts` (with `event`) for the Inertia page. No AI.
- **`store`**: Creates a `Game` via `CreateGameAction` (first chapter’s first event → `current_event_id`). No AI.
- **`begin`**: If the game already has prompts, redirects to show. Otherwise calls **`generateFirstNarration`**, which:
  - Reads `story.system_prompt` → `character_name`, `world_rules`, `tone_and_style`.
  - Loads `firstEvent` from `game->currentEvent`.
  - Queries **next events** in the same chapter only (`position` > first, limit 2) for titles/positions (pacing awareness).
  - Renders `resources/views/ai/agents/narration/system-prompt.blade.php` with:
    - `previousEvents` = `[]`
    - `currentEvent` = position, title, content, objectives, attributes
    - `nextEvents` from the query above
    - **No `turnCount`** (unlike `PromptController`, which passes it after the first turn).
  - Renders `resources/views/ai/agents/narration/prompt.blade.php` with `conversationHistory` = `[]`, `playerAction` = `''` (opening branch in the user prompt template).
  - Calls **`NarrationAgent::make(customInstructions: $systemPrompt)`** → `->prompt(...)`.
  - On failure: fallback HTML from first event `content` and default choices.

### `PromptController@store`

1. **Input**: `prompt` string; `__continue__` means “Continue forward” for the model.
2. **Side effect**: Updates the **latest** prompt row’s `prompt` field to the submitted value (or `__continue__`). Then builds state from **current** `game->currentEvent` (not necessarily that row’s event—see below).
3. **`turnCount`**: Count of `prompts` for the **current** `event_id` (includes prior turns in that event).
4. **`renderSystemPrompt`**: Same Blade as gameplay; includes:
   - From DB: `story.system_prompt` fields; `currentEvent` full payload; **previous events** (up to 3, can pull from previous chapter); **next events** (up to 2, can cross into next chapter); **`turnCount`** for pacing hints in the system template.
5. **`buildConversationHistory`**: Last 6 prompts, oldest-first; strips tags from `response`; maps `__continue__` to “Continue forward”.
6. **`generateNarration`**: `NarrationAgent` with user prompt = conversation + `PLAYER'S ACTION` / opening line.
7. **Advance logic**:
   - Start from `advance_event` boolean from the model.
   - If still false but **`turnCount >= 5`**, force advance.
   - If advancing: `findNextEvent` — next event in chapter by `position`, else first event of next chapter by chapter `position`.
   - Update `game.current_event_id` if a next event exists.
8. **Persist**: `prompts()->create` with `event_id` = next event id if advanced and next exists, else current event; `response` and `choices` from the model.

### `NarrationAgent` (`app/Ai/Agents/NarrationAgent.php`)

- **Model**: `gpt-5.2`, temperature `0.85`, timeout `60s`.
- **Instructions**: The **full narrator instructions string** — rendered from **`resources/views/ai/agents/narration/system-prompt.blade.php`** (with variables below) and passed into `NarrationAgent::make(customInstructions: …)`. That rendered HTML/text is what the model sees as its system-side behavior; the **user** message is `narration/prompt.blade.php` (history + player action).

- **Structured output schema**:
  - `response` (string, HTML)
  - `choices` (array of exactly 3 strings — enforced by description in schema)
  - `advance_event` (boolean)

#### “System prompt” — three related things (do not confuse)

| What | Where | Role |
|------|--------|------|
| **`stories.system_prompt` (JSON)** | DB column | `character_name`, `world_rules`, `tone_and_style` — **extracted from the script** by `SystemPromptGeneratorAgent` / `SystemPromptGeneratorJob`. Injected into the **narration** Blade as variables. |
| **Narrator system instructions** | `ai/agents/narration/system-prompt.blade.php` | The long LORESPINNER rules + event payload. This is the main “how to play the scene” spec; **source of truth for tone, canon, POV, choices, `advance_event` semantics.** |
| **Extractor agent instructions** | `ai/agents/system-prompt-generator/system-prompt.blade.php` | Only for **generating** the JSON above from the screenplay — not used during gameplay turns. |

Variables **passed into** `narration/system-prompt.blade.php` (from `PromptController::renderSystemPrompt` / `GameController::generateFirstNarration`): `characterName`, `worldRules`, `toneAndStyle` (from JSON); `previousEvents`, `currentEvent` (full event including `content`, `objectives`, `attributes`); `nextEvents` (titles only); `turnCount` (omitted on the very first `begin` turn).

#### Outline of the narrator system template (behavioral sections)

The Blade file is long; these are the major sections (edit the file for wording changes):

- **Role** — LORESPINNER; render current event as interactive scene.
- **Global world rules** — From DB JSON; must be followed.
- **Event data format** — `content` = canonical facts; `objectives` = context only.
- **Canon fidelity** — Verbatim dialogue; cinematic rewrite of action; no screenplay formatting in output.
- **Context blocks** — Previous events (continuity), current event (full), upcoming titles only (no spoilers).
- **Interactivity** — Player message drives the next beat; no autopilot past the player.
- **Turn pacing** — Small slices per turn; anti–fast-forward rules.
- **Event progression** — First response vs later turns in same event (don’t re-read the script).
- **Controlled continuation** — Allowed vs forbidden inventions when extending the scene.
- **Spoiler containment** — No future-event content.
- **POV policy** — Second-person when playable character present, etc.
- **Choices** — Three concrete verbs; anti-duplication; convergence gradient.
- **Style** — Uses `toneAndStyle` when set.
- **Event advancement signal** — When to set `advance_event` true/false; **`turnCount`** inserts extra pacing hints (turns 2–4+).
- **Output requirement** — Reminds model to return JSON matching `NarrationAgent` schema.

---

## 3. Creator / offline pipeline (story preparation)

These run on queues (or sync in seeders). They populate `stories`, `chapters`, and `events` so gameplay can run.

### Order when uploading a script (`Filament` `CreateStory`)

If `use_script_upload` is true:

1. `ChapterExtractorJob` — `chapter-extraction` queue  
2. `SystemPromptGeneratorJob` — `system-prompt-generation`  
3. `StoryCoverGeneratorJob` — `image-generation`  
4. `StoryOpeningGeneratorJob` — delayed 5 minutes, `opening-generation`  

If not using script upload: story status set to draft; only `StoryCoverGeneratorJob` runs.

Chapter extraction runs immediately; **event extraction** is dispatched separately when the creator triggers it from Filament (`ViewChapter` dispatches `EventExtractorJob`). Seeders also run `EventExtractorJob::dispatchSync` per chapter after chapters exist. The jobs below are the AI pieces.

### `ChapterExtractorJob`

- **Reads**: Script file via `$story->getFirstMediaPath('script')`, passed through `LineNumberFormatterHelper`.
- **AI**: `ChapterExtractorAgent` → user prompt from `chapter-extractor.prompt` with numbered `content`.
- **Writes**: `chapters` rows with `title`, `position`, `teaser`, `content` (slice via `NumberedLineExtractorHelper`), status `AWAITING_CREATOR_REVIEW`.
- **Story status**: `EXTRACTING_CHAPTERS` → `DRAFT` on success; on failure reverts to `AWAITING_EXTRACTING_CHAPTERS_REQUEST`.

### `EventExtractorJob` (per chapter)

- **Reads**: `chapter.content` → line-numbered.
- **AI**: `EventExtractorAgent` → events with **start/end coordinates** (line + char).
- **Writes**: `events` with `position`, `title`, `content` from `TextRangeExtractorHelper`.
- **Then**: Dispatches a **batch** of `EventObjectiveAndAttributeExtractor` jobs (one per new event). When the batch finishes, chapter status → `READY_TO_PLAY`.

### `EventObjectiveAndAttributeExtractor` (job, per event)

- **Reads**: `Event` with chapter; **`nextEvents`** = up to 5 following events in the same chapter (titles + full content for context in the Blade template).
- **Note**: The Blade `event-objective-and-attribute-extractor.prompt` supports `previousEvents`, but the job **does not pass** `previousEvents`, so that section is empty at runtime.
- **AI**: `EventObjectiveAndAttributesExtractor` agent class.
- **Writes**: `event.objectives` (string), `event.attributes` (JSON array of strings).

### `SystemPromptGeneratorJob`

- **Reads**: Full script file from media `script`.
- **AI**: `SystemPromptGeneratorAgent` — static instructions from `system-prompt-generator.system-prompt` view; user content from `system-prompt-generator.prompt` with `title` + `script`.
- **Writes**: `story.system_prompt` JSON: `character_name`, `world_rules`, `tone_and_style`.

### `StoryOpeningGeneratorJob`

- **Reads**: `story.system_prompt`, first chapter (by `position`), first event of that chapter; `title`, `teaser`.
- **AI**: `OpeningNarrationAgent` with `opening-narration.prompt` + static instructions from `opening-narration.system-prompt`.
- **Writes**: `story.opening` (HTML string).

### Image generation (Prism, not Laravel AI agents)

| Job | Model | Input from DB / model |
|-----|-------|------------------------|
| `StoryCoverGeneratorJob` | `gpt-image-1` | Title, teaser, category title, rating, `system_prompt.tone_and_style` |
| `ChapterCoverGeneratorJob` | `gpt-image-1` | Chapter title/teaser/content snippet, story title/teaser, category, tone; fallback `buildSafePrompt` on safety errors |
| `CreatorAvatarGeneratorJob` | `gpt-image-1` | Creator `full_name`, `bio` |

---

## 4. Other AI-related HTTP endpoints

| Controller | Purpose |
|------------|---------|
| `TranscribeController` | Multipart upload to OpenAI `whisper-1`; returns JSON `{ text }`. Uses `config('services.openai.api_key')` / `OPENAI_API_KEY`. |
| `TextToSpeechController` | ElevenLabs stream TTS from `strip_tags($prompt->response)`; caches file under `storage/app/tts/{prompt_id}.mp3`. |

Neither feeds the narration agent; they support voice input/output for the same `prompts.response` content.

---

## 5. Seed-only / dev AI

- **`CommentSeeder`**: Prism text `gpt-4o-mini` generates fake comment lines from story `title` + `teaser`. Not used in production gameplay.

---

## 6. Agent summary table

| Agent class | Temperature / timeout | Output shape (high level) |
|-------------|------------------------|---------------------------|
| `NarrationAgent` | 0.85 / 60s | `response`, `choices[3]`, `advance_event` |
| `SystemPromptGeneratorAgent` | 0.4 / 180s | `playable_character_name`, `world_rules[]`, `tone_and_style` |
| `OpeningNarrationAgent` | 0.9 / 120s | `opening` |
| `ChapterExtractorAgent` | default / 120s | `chapters[]` with positions, titles, teasers, line ranges |
| `EventExtractorAgent` | default / 120s | `events[]` with coordinates |
| `EventObjectiveAndAttributesExtractor` | default / 120s | `objective`, `attributes[]` |

---

## 7. Queues (reference)

| Queue name | Typical jobs |
|------------|----------------|
| `chapter-extraction` | `ChapterExtractorJob` |
| `event-extraction` | `EventExtractorJob` |
| `event-objective-and-attribute-extraction` | Objective/attribute jobs (batched) |
| `system-prompt-generation` | `SystemPromptGeneratorJob` |
| `opening-generation` | `StoryOpeningGeneratorJob` |
| `image-generation` | Cover/avatar image jobs |

---

## 8. Behaviors to preserve when refactoring

- **Structured narration**: The “brain” is `NarrationAgent` + `narration/system-prompt.blade.php` + `narration/prompt.blade.php`. Changing pacing or advancement should stay aligned with `advance_event` and the **5-turn** server-side cap in `PromptController`.
- **Canon source**: Event `content` is the screenplay; `objectives` / `attributes` are auxiliary continuity, as stated in the system prompt.
- **Cross-chapter context**: Previous/next event helpers in `PromptController` intentionally span chapters so chapter boundaries do not blind the model.
- **Failures**: `PromptController` and `GameController` fall back to generic HTML and default choices so the UI does not hard-error.

---

## 9. Files to open first when changing logic

| Concern | Location |
|---------|----------|
| Turn-by-turn behavior & DB reads | `app/Http/Controllers/User/Game/PromptController.php`, `app/Http/Controllers/User/GameController.php` |
| Narrator instructions & user message format | `resources/views/ai/agents/narration/system-prompt.blade.php`, `.../narration/prompt.blade.php` |
| JSON output schema | `app/Ai/Agents/NarrationAgent.php` |
| Story JSON used at runtime | `stories.system_prompt` (filled by `SystemPromptGeneratorJob`) |
| Offline extraction | `app/Jobs/Chapter/ChapterExtractorJob.php`, `app/Jobs/Event/EventExtractorJob.php`, `app/Jobs/Event/EventObjectiveAndAttributeExtractor.php` |
| Provider defaults | `config/ai.php` |

This document reflects the codebase as of the time it was written; after large refactors, update this file so it remains the single ground-truth reference.
