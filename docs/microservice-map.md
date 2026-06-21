# LoreSpinner — Microservice Map

> Snapshot of the Laravel monolith as of June 2026.  
> Generated for architecture / extraction planning.

---

## 1. Laravel Folder Structure

```
lore-spinner-rnd/
├── app/
│   ├── Actions/              # Single-purpose domain actions (Game, Auth, Prompt, User)
│   ├── Ai/
│   │   ├── Adaptation/       # Runtime narrator template builders
│   │   └── Agents/           # LLM agents (Adaptation, Chaos, Chapter, Event, WriterLab)
│   ├── ChaosMode/            # Chaos Mode story config (experimental runtime)
│   ├── Console/Commands/     # Artisan commands (adaptation, seeding, debug)
│   ├── Enums/                # Adaptation, Chapter, Comment, Story enums
│   ├── Filament/
│   │   ├── Creator/          # Creator panel (stories, chapters)
│   │   └── Manager/          # Manager panel (analytics, users, feedback)
│   ├── Helpers/
│   ├── Http/
│   │   ├── Controllers/      # Web + Inertia controllers
│   │   ├── Middleware/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Jobs/
│   │   ├── Adaptation/       # V2 adaptation pipeline jobs
│   │   ├── Chapter/
│   │   ├── Creator/
│   │   ├── Event/
│   │   └── Story/
│   ├── Models/               # Eloquent models
│   ├── Providers/            # Service providers + Filament panels
│   ├── Services/             # ChaosEngineService, etc.
│   ├── Support/
│   ├── Traits/
│   └── VoiceLab/             # Voice Lab sub-module (Models, Agents, Http)
├── bootstrap/
│   └── app.php               # Routing bootstrap (web only — no api.php)
├── config/                   # Laravel + app config
├── database/
│   ├── factories/
│   ├── migrations/
│   ├── seeders/
│   └── stories/              # Source PDFs/scripts (not DB)
├── public/
├── resources/
│   ├── css/
│   ├── js/                   # Vue 3 + Inertia frontend
│   └── views/
│       ├── ai/agents/        # Blade LLM prompt templates
│       └── filament/
├── routes/
│   ├── web.php               # Main web routes (entry point)
│   ├── console.php
│   └── routes/
│       ├── user.php          # User auth + gameplay routes
│       ├── writer.php        # Writer Lab routes
│       └── voice-lab.php
├── storage/
├── tests/
└── vendor/
```

**Routing note:** There is **no `routes/api.php`**. The app is Inertia-first (web routes only). JSON endpoints exist inside web routes (Chaos Mode, Writer Lab playground, feedback).

---

## 2. Routes

### `routes/api.php`

**Does not exist.** Registered in `bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

### `routes/web.php` (main entry)

| Method | Path | Controller | Purpose |
|--------|------|------------|---------|
| GET | `/` | `IndexController` | Homepage |
| GET | `/creators`, `/creators/{creator}` | `CreatorController` | Creator pages |
| GET | `/stories`, `/stories/{story}` | `StoryController` | Story catalog |
| POST | `/feedback` | `FeedbackController` | Feedback form |
| GET | `/chaos-mode` | `ChaosModeController@show` | Chaos Mode UI |
| POST | `/chaos-mode/start` | `ChaosModeController@start` | Start chaos session (JSON) |
| POST | `/chaos-mode/turn` | `ChaosModeController@turn` | Player turn (JSON) |
| POST | `/chaos-mode/continue` | `ChaosModeController@continueSession` | Next session (JSON) |
| GET | `/chaos-mode/{chaosSession}/tts/{turnIndex}` | `ChaosTtsController` | TTS audio |
| GET | `/expansion-status` | closure | Dev status JSON |

Includes: `routes/routes/user.php`, `routes/routes/writer.php`

### `routes/routes/user.php` (gameplay)

| Method | Path | Controller | Purpose |
|--------|------|------------|---------|
| GET/POST | `/user/authentication/*` | Auth controllers | Register, login, profile |
| GET | `/user/dashboard` | `DashboardController` | User dashboard |
| POST | `/user/bookmarks/{story}` | `BookmarkController` | Toggle bookmark |
| GET/POST | `/user/games` | `GameController` | List/create game |
| GET | `/user/games/{game}` | `GameController@show` | Game play page |
| POST | `/user/games/{game}/begin` | `GameController@begin` | Opening narration |
| POST | `/user/games/{game}/reset` | `GameController@reset` | Reset story cycle |
| POST | `/user/games/{game}/next-session` | `GameController@nextSession` | Advance session |
| POST | `/user/games/prompt` | `PromptController@store` | Player turn / continue |
| GET | `/user/games/{game}/tts/{prompt}` | `TextToSpeechController` | TTS |
| POST | `/user/games/transcribe` | `TranscribeController` | Speech-to-text |

---

## 3. Main Gameplay Controllers

### Production runtime

| File | Role |
|------|------|
| `app/Http/Controllers/User/GameController.php` | Game lifecycle: create, show, begin, nextSession, reset |
| `app/Http/Controllers/User/Game/PromptController.php` | Turn-by-turn narration (player choice / continue) |
| `app/Http/Controllers/User/Game/TextToSpeechController.php` | TTS for prompt responses |
| `app/Http/Controllers/User/Game/TranscribeController.php` | Voice input transcription |
| `app/Services/ChaosEngineService.php` | Shared engine: session context, system prompt, LLM call, state merge |

**Flow:** `GameController@begin` → opening turn. `PromptController@store` → subsequent turns. Both delegate to `ChaosEngineService`.

**Launch slugs** (production games): `the-wonderful-wizard-of-oz`, `the-adventure-of-the-speckled-band`, `the-tell-tale-heart`, `the-masque-of-the-red-death`

### Experimental / internal

| File | Role |
|------|------|
| `app/Http/Controllers/ChaosMode/ChaosModeController.php` | Stateless JSON API for Chaos Mode testing |
| `app/Http/Controllers/ChaosMode/ChaosTtsController.php` | Chaos Mode TTS |
| `app/Http/Controllers/Writer/WriterLab/PlaygroundController.php` | Writer Lab multi-event preview (mirrors runtime) |

---

## 4. Game / Session Models

| Model | Table | PK | Purpose |
|-------|-------|----|---------|
| `Game` | `games` | ULID | Production game instance per user+story |
| `Prompt` | `prompts` | ULID | Narration turns (response, choices, player prompt) |
| `GameSessionCompletion` | `game_session_completions` | int | Per-session start/complete analytics |
| `GameCompletion` | `game_completions` | int | Immutable story-cycle completion record |
| `GameReset` | `game_resets` | int | Immutable reset event log |
| `ChaosSession` | `chaos_sessions` | ULID | Chaos Mode experimental session state |

### Key `Game` fields

- `story_id`, `user_id`
- `current_session_number`, `current_story_cycle_number`
- `model` (LLM)
- `world_state` (JSON), `symbolic_memory`, `alignment_scaffold` (JSON)
- `is_climactic_choice`, `defining_choice_id`, `defining_choice_line`
- `current_session_complete`, `completed_at`, `is_preview`

### Key `ChaosSession` fields

- `story_id`, `user_id`, `story_session_number`
- `conversation_history` (JSON), `world_state`, `alignment_scaffold`
- `session_memory`, `symbolic_memory`
- `session_complete`, `turn_count`, `model`, `ip_address`

### Relationships

```
User ──< Game ──< Prompt
Story ──< Game
Game ──< GameSessionCompletion
Game ──< GameCompletion
Game ──< GameReset
Story ──< ChaosSession
```

---

## 5. Story / Event / Adaptation Models

| Model | Table | Purpose |
|-------|-------|---------|
| `Story` | `stories` | Story metadata, script media, system_prompt JSON |
| `Chapter` | `chapters` | Story chapters (position, content, status) |
| `Event` | `events` | Beat-level content within chapters |
| `StoryAdaptation` | `story_adaptations` | Story-level adaptation pipeline output |
| `SessionAdaptation` | `session_adaptations` | Per-session adaptation + runtime narrator prompt |

### Key `Story` relations

- `chapters`, `events` (hasManyThrough), `games`, `adaptation` (hasOne)
- Media: `script`, `cover`, `banner`, `gallery`, `outro`

### Key `Event` fields

- `chapter_id`, `position`, `title`, `content`
- `objectives`, `attributes` (JSON)
- `session_number` (assigned during adaptation)
- `requires_choice`

### Key `StoryAdaptation` fields

- `adaptation_status` (enum)
- `ip_trimming`, `format_detection`, `ip_audit`, `voice_profile`, `story_session_map` (JSON)

### Key `SessionAdaptation` fields

- `session_number`, `session_status` (enum)
- `entry_point_diagnosis`, `session_architecture`, `session_choice_design`
- `choice_consequence_map`, `session_close_design`, `editorial_verification` (JSON)
- `runtime_narrator_prompt`, `runtime_narrator_assembled_at`

### Relationships

```
Story ──< Chapter ──< Event
Story ──< StoryAdaptation ──< SessionAdaptation
Event.session_number → SessionAdaptation.session_number (logical, not FK)
```

---

## 6. Jobs List

### Adaptation pipeline (`app/Jobs/Adaptation/`)

| Job | Queue |
|-----|-------|
| `RunAdaptationPipelineJob` | `adaptation` |
| `IpTrimmingChapterJob` | `adaptation` |
| `IpTrimmingMergeJob` | `adaptation` |
| `FormatDetectionJob` | `adaptation` |
| `IpAuditJob` | `adaptation` |
| `VoiceLockChapterJob` | `adaptation` |
| `VoiceLockMergeJob` | `adaptation` |
| `StorySessionMapJob` | `adaptation` |
| `EntryPointDiagnosisJob` | `adaptation` |
| `SessionArchitectureJob` | `adaptation` |
| `ChoiceDesignJob` | `adaptation` |
| `ConsequenceMappingJob` | `adaptation` |
| `SessionCloseJob` | `adaptation` |
| `EditorialVerificationJob` | `adaptation` |
| `RuntimeNarratorAssemblyJob` | `adaptation` |
| `AdaptationStatusReconciliationJob` | `adaptation` |

### Story ingestion & assets

| Job | Queue |
|-----|-------|
| `ChapterExtractorJob` | `chapter-extraction` |
| `EventExtractorJob` | `event-extraction` |
| `EventObjectiveAndAttributeExtractor` | `event-objective-and-attribute-extraction` |
| `SystemPromptGeneratorJob` | `system-prompt-generation` |
| `StoryOpeningGeneratorJob` | `opening-generation` |
| `StoryCoverGeneratorJob` | `image-generation` |
| `StoryBannerGeneratorJob` | `image-generation` |
| `ChapterCoverGeneratorJob` | `image-generation` |
| `CreatorAvatarGeneratorJob` | `image-generation` |

### Other

| Job | Queue |
|-----|-------|
| `RunExpansionSeederJob` | `default` (no explicit queue) |

**Total: 26 job classes**

---

## 7. Queues List

### Config (`config/queue.php`)

| Setting | Value |
|---------|-------|
| Default connection | `database` (`QUEUE_CONNECTION` env) |
| Jobs table | `jobs` |
| Failed jobs table | `failed_jobs` |
| Batches table | `job_batches` |

### Named queues (used via `->onQueue(...)`)

| Queue name | Jobs | Typical worker |
|------------|------|----------------|
| `adaptation` | All V2 adaptation pipeline jobs | `php artisan queue:work database --queue=adaptation --tries=1 --timeout=900` |
| `chapter-extraction` | `ChapterExtractorJob` | Generic worker |
| `event-extraction` | `EventExtractorJob` | Generic worker |
| `event-objective-and-attribute-extraction` | `EventObjectiveAndAttributeExtractor` | Generic worker |
| `system-prompt-generation` | `SystemPromptGeneratorJob` | Generic worker |
| `opening-generation` | `StoryOpeningGeneratorJob` | Generic worker |
| `image-generation` | Cover, banner, avatar jobs | `--queue=image-generation` |
| `default` | Jobs without explicit queue (e.g. `RunExpansionSeederJob`) | Default worker |

### Production (Laravel Cloud)

- Worker cluster: 3 processes
- Command: `php artisan queue:work database --queue=adaptation --tries=1 --timeout=900`
- Adaptation jobs **must** specify `database` connection + `adaptation` queue

---

## 8. Database Tables

| Table | Created by | Domain |
|-------|------------|--------|
| `users` | migration | Auth / players |
| `creators` | migration | Story creators |
| `managers` | migration | Filament manager panel |
| `writers` | migration | Writer Lab auth |
| `categories` | migration | Story categories |
| `stories` | migration | Stories |
| `chapters` | migration | Chapters |
| `events` | migration | Events/beats |
| `comments` | migration | Story comments |
| `bookmarks` | migration | User bookmarks |
| `games` | migration | Game instances |
| `prompts` | migration | Game narration turns |
| `game_session_completions` | migration | Session analytics |
| `game_completions` | migration | Story completion analytics |
| `game_resets` | migration | Reset analytics |
| `chaos_sessions` | migration | Chaos Mode sessions |
| `story_adaptations` | migration | Story-level adaptation |
| `session_adaptations` | migration | Session-level adaptation |
| `feedbacks` | migration | User feedback |
| `page_views` | migration | Analytics page views |
| `user_activity_days` | migration | DAU tracking |
| `writer_lab_drafts` | migration | Writer Lab drafts |
| `writer_lab_versions` | migration | Writer Lab version history |
| `writer_lab_notes` | migration | Writer Lab notes |
| `voice_lab_sessions` | migration | Voice Lab sessions |
| `voice_lab_prompts` | migration | Voice Lab prompts |
| `media` | migration | Spatie media library |
| `agent_conversations` | migration | Laravel AI agent conversations |
| `agent_conversation_messages` | migration | Agent message history |
| `jobs` | migration | Queue jobs |
| `job_batches` | migration | Queue batches |
| `failed_jobs` | migration | Failed queue jobs |
| `cache` | migration | Cache store |
| `cache_locks` | migration | Cache locks |
| `sessions` | migration | HTTP sessions (not game sessions) |

**Total: 35 tables**

---

## 9. Runtime Data Flow (summary)

```
Story script
  → ChapterExtractorJob → chapters
  → EventExtractorJob → events
  → RunAdaptationPipelineJob
      → ip_trimming → format_detection → ip_audit → voice_lock
      → story_session_map (assigns event.session_number)
      → per-session: entry_point → architecture → choices → consequences → close → editorial
      → RuntimeNarratorAssemblyJob → session_adaptations.runtime_narrator_prompt

Player starts game
  → GameController@begin / PromptController@store
  → ChaosEngineService.loadSessionContext(story, sessionNumber)
  → ChaosEngineService.renderSystemPrompt(session_adaptations.runtime_narrator_prompt + runtime blocks)
  → ChaosEngineService.callAgent() → Prompt persisted → Game state updated
```

---

## 10. Related Services & Entry Points

| Path | Purpose |
|------|---------|
| `app/Services/ChaosEngineService.php` | Core narration engine (858 lines) |
| `app/Actions/Game/CreateGameAction.php` | Game creation |
| `resources/views/ai/agents/chaos/runtime-narrator-template.blade.php` | Runtime prompt template |
| `resources/views/ai/agents/adaptation/` | Adaptation agent prompt blades |
| `app/Console/Commands/RunAdaptationCommand.php` | `php artisan stories:run-adaptation {slug}` |
