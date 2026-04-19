# Voice Lab Module

Isolated R&D module for voice-to-voice story interaction. Completely separate
from the main game runtime. Change anything in here without fear of affecting
the main text-mode game flow.

## Directory Map

- `app/VoiceLab/` — backend
  - `Agents/VoiceChatAgent.php` — structured-output LLM agent (response + choices)
  - `Actions/ResolveVoiceLabSessionAction.php` — find-or-create active session
  - `Actions/ProcessVoiceTurnAction.php` — conversation pipeline (history → AI → persist)
  - `Services/ElevenLabsVoiceService.php` — ElevenLabs TTS wrapper
  - `Http/Controllers/` — `IndexController`, `RespondController`, `TranscribeController`, `ClearHistoryController`
  - `Http/Requests/StoreVoiceLabMessageRequest.php` — validation
  - `Models/VoiceLabSession.php`, `Models/VoiceLabPrompt.php` — dedicated Eloquent models
  - `Enums/VoiceLabRoleEnum.php` — player | narrator
- `resources/views/voice-lab/agents/voice-chat/` — blade templates (system prompt + turn prompt)
- `resources/js/voice-lab/` — frontend tree
  - `pages/Index.vue` — entry page (registered as Inertia `VoiceLab/Index`)
  - `layouts/VoiceLabLayout.vue` — wrapper (top bar + centered orb slot + bottom controls)
  - `components/VoiceLabOrb.vue` — the dynamic orb (all animations live here)
  - `composables/useVoiceLab.ts` — mic/transcribe/respond/playback orchestration
  - `assets/orb/Mask group.png` — base orb image
- `config/voice-lab.php` — all tunables (voice ID, TTS settings, history size, greeting toggle)
- `routes/routes/voice-lab.php` — route definitions, included from `routes/routes/user.php`
- `database/migrations/2026_04_17_000001_create_voice_lab_sessions_table.php`
- `database/migrations/2026_04_17_000002_create_voice_lab_prompts_table.php`

## Data Flow

```
[ mic capture (browser) ]
        │   MediaRecorder + AnalyserNode (live orb level)
        ▼
POST /user/voice-lab/transcribe  ──►  TranscribeController (Whisper)
        │
        ▼ text
POST /user/voice-lab/respond  ──►  RespondController
                                      │
                                      ▼
                             ResolveVoiceLabSessionAction
                                      │
                                      ▼
                              ProcessVoiceTurnAction
                                 │          │
                                 ▼          ▼
                         VoiceChatAgent   voice_lab_prompts (persist turn)
                                 │
                                 ▼
                        ElevenLabsVoiceService (TTS)
                                 │
                                 ▼  audio/mpeg + X-VoiceLab-Choices header
                        [ browser plays audio, renders choice buttons ]
```

## Database

- `voice_lab_sessions` — one row per active user conversation (`user_id`, nullable `story_id`, nullable `ended_at`).
- `voice_lab_prompts` — turn-by-turn history (`session_id`, `role`, `text`, nullable `choices`, nullable `audio_ms`).

Zero foreign keys to `games` or `prompts`. Fully isolated.

## Where to Change Things

| What you want to change                                          | File                                                              |
|------------------------------------------------------------------|-------------------------------------------------------------------|
| Voice ID / TTS stability / speed / model / output format         | `config/voice-lab.php` (env-backed)                               |
| AI conversational personality, tone, choice-weaving rules        | `resources/views/voice-lab/agents/voice-chat/system-prompt.blade.php` |
| Turn-prompt framing (what each turn's input looks like)          | `resources/views/voice-lab/agents/voice-chat/prompt.blade.php`    |
| Agent schema (output fields)                                     | `app/VoiceLab/Agents/VoiceChatAgent.php`                          |
| Number of past turns fed into the AI                             | `config/voice-lab.php` → `history_size`                           |
| Whether the first turn auto-greets the listener                  | `config/voice-lab.php` → `greeting_enabled`                       |
| Orb visual behavior (layers, hues, animations)                   | `resources/js/voice-lab/components/VoiceLabOrb.vue`               |
| Orb state mapping (listening/thinking/speaking transitions)      | `resources/js/voice-lab/composables/useVoiceLab.ts`               |
| Page layout, header, action buttons                              | `resources/js/voice-lab/layouts/VoiceLabLayout.vue`               |
| Page composition (orb + state label + choice buttons)            | `resources/js/voice-lab/pages/Index.vue`                          |
| Endpoints (URLs, verbs, names)                                   | `routes/routes/voice-lab.php`                                     |
| Story grounding (which story's character/world context)          | `config/voice-lab.php` → `story_slug`                             |

## What NOT to Touch

These belong to the main text-mode game. Do not couple Voice Lab to them:

- `app/Actions/Game/*`
- `app/Ai/Agents/NarrationAgent.php`
- `resources/views/ai/agents/narration/`
- `app/Models/Game.php`, `app/Models/Prompt.php`
- `app/Http/Controllers/User/Game/*`, `app/Http/Controllers/User/GameController.php`

## Shared Dependencies (Read-Only)

The only cross-module coupling is with `App\Models\Story`. Voice Lab reads the
story's `system_prompt` JSON (character name, world rules, tone/style) to
ground the conversational agent. It never mutates the Story.

`.env` keys are also shared: `ELEVENLABS_API_KEY`, `OPENAI_API_KEY`. Voice Lab
reads them via its own `config/voice-lab.php` wrapper, so any future override
(`VOICELAB_*` env vars) applies only to this module.

## Isolation Guarantees

Verified at merge time via these checks (see plan Step 5 + Step 6):

1. `PromptController.php` + `GameController.php` match their pre-a1c06a7
   snapshots byte-for-byte — main game is 100% untouched.
2. Grep for `ProcessGameTurnAction` project-wide — zero hits (action deleted).
3. Grep `App\Models\Game`, `App\Models\Prompt`, `NarrationAgent`,
   `App\Actions\Game` inside `app/VoiceLab/` — zero hits.
4. Grep frontend for `@/layouts/VoiceLabLayout`, `@/components/VoiceLabOrb`,
   `@/composables/useVoiceLab`, `@/assets/orb` — zero hits (old paths deleted).

## Extending

To add a new conversational feature (e.g. emotion-aware response):

1. Update `resources/views/voice-lab/agents/voice-chat/system-prompt.blade.php`
   with new guidance.
2. If you need new fields in the LLM output, add them to the schema in
   `app/VoiceLab/Agents/VoiceChatAgent.php`.
3. Handle the new fields in `ProcessVoiceTurnAction::generateReply()`.
4. Surface them via a new response header in `RespondController`, then read
   them in `resources/js/voice-lab/composables/useVoiceLab.ts`.

Nothing outside this module needs to change.

## History

Originally introduced as a prototype in commit `ed72384`. Fully isolated into
this module on 2026-04-17. See `git log -- app/VoiceLab/` for module-specific
history.
