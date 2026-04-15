# Adaptation Layer — Process Log

Date: April 15, 2026
Status: Implementation complete. Ready for migration and testing.

---

## Summary of Goal

Implement a 9-phase (0-8) Adaptation Layer as a production pipeline that converts passive stories into playable interactive sessions. The pipeline operates at two tiers: story-wide phases (Format Detection, IP Audit, Story Session Map with branch dimension definitions) run once per IP, then per-session phases (Entry Point Diagnosis, Session Beat Architecture, Choice Design, Consequence Mapping, Session Close, Editorial Verification) run once per planned session. Adaptation artifacts feed directly into the runtime narrator.

---

## Architecture Decisions

- **Two-tier storage**: `story_adaptations` table for story-wide artifacts, `session_adaptations` table for per-session artifacts. Mirrors the three-layer model: canon extraction / story adaptation / session adaptation.
- **Runtime join**: `events.session_number` (nullable int) written once by `StorySessionMapJob`. Runtime resolves: `game.current_event_id → event.session_number → session_adaptations`.
- **Branch dimensions**: Born in Phase 2 as canonical narrative axes (3-6 per story). Instantiated by Phase 5 as concrete choices. Registry stored in `story_session_map.branch_dimensions` JSON. ChoiceDesignJob normalizes dimension names (case-insensitive, underscore-normalized) to prevent near-duplicates.
- **Runtime branch resolution**: Behavioral contract classifying freeform input as expressive / branch-aligned / emergent / unsupported. Enforced through narrator system-prompt instructions.
- **Production safety**: Idempotency contract on pipeline reruns, DB::transaction boundaries on Phase 2 multi-writes, readiness gate at runtime (presence + COMPLETED enum check), per-session failure isolation with PARTIAL_COMPLETION status.
- **Reconciliation finalizer**: `AdaptationStatusReconciliationJob` dispatched after all per-session chains via `Bus::batch()->finally()`. Computes aggregate story-level status.

---

## Files Created

### Enums (2 files)
- `app/Enums/Adaptation/AdaptationStatusEnum.php` — PENDING, FORMAT_DETECTION, IP_AUDIT, STORY_SESSION_MAP, ADAPTING_SESSIONS, COMPLETED, PARTIAL_COMPLETION, FAILED
- `app/Enums/Adaptation/SessionAdaptationStatusEnum.php` — PENDING, ENTRY_POINT_DIAGNOSIS, SESSION_ARCHITECTURE, CHOICE_DESIGN, CONSEQUENCE_MAPPING, SESSION_CLOSE, EDITORIAL_VERIFICATION, COMPLETED, FAILED

### Models (2 files)
- `app/Models/StoryAdaptation.php` — belongsTo Story, hasMany SessionAdaptation. JSON casts for format_detection, ip_audit, story_session_map.
- `app/Models/SessionAdaptation.php` — belongsTo StoryAdaptation. JSON casts for 6 per-session artifact columns.

### Migrations (4 files)
- `database/migrations/2026_04_15_000001_create_story_adaptations_table.php`
- `database/migrations/2026_04_15_000002_create_session_adaptations_table.php`
- `database/migrations/2026_04_15_000003_add_session_number_to_events_table.php`
- `database/migrations/2026_04_15_000004_add_adaptation_state_to_games_table.php` — current_session_number, current_beat_type, branching_choices_taken, tracked_dimensions, branch_resolution_log

### Agents (9 files)
- `app/Ai/Agents/Adaptation/FormatDetectionAgent.php` — Temp 0.4, Timeout 120s
- `app/Ai/Agents/Adaptation/IpAuditAgent.php` — Temp 0.4, Timeout 180s
- `app/Ai/Agents/Adaptation/StorySessionMapAgent.php` — Temp 0.5, Timeout 240s
- `app/Ai/Agents/Adaptation/EntryPointDiagnosisAgent.php` — Temp 0.6, Timeout 120s
- `app/Ai/Agents/Adaptation/SessionArchitectureAgent.php` — Temp 0.6, Timeout 180s
- `app/Ai/Agents/Adaptation/ChoiceDesignAgent.php` — Temp 0.7, Timeout 240s
- `app/Ai/Agents/Adaptation/ConsequenceMappingAgent.php` — Temp 0.6, Timeout 180s
- `app/Ai/Agents/Adaptation/SessionCloseAgent.php` — Temp 0.7, Timeout 180s
- `app/Ai/Agents/Adaptation/EditorialVerificationAgent.php` — Temp 0.3, Timeout 120s

### Jobs (11 files)
- `app/Jobs/Adaptation/RunAdaptationPipelineJob.php` — Orchestrator with idempotency contract
- `app/Jobs/Adaptation/FormatDetectionJob.php`
- `app/Jobs/Adaptation/IpAuditJob.php`
- `app/Jobs/Adaptation/StorySessionMapJob.php` — DB::transaction for session map + event mapping + session row creation
- `app/Jobs/Adaptation/EntryPointDiagnosisJob.php`
- `app/Jobs/Adaptation/SessionArchitectureJob.php`
- `app/Jobs/Adaptation/ChoiceDesignJob.php` — includes enrichBranchDimensionRegistry()
- `app/Jobs/Adaptation/ConsequenceMappingJob.php`
- `app/Jobs/Adaptation/SessionCloseJob.php`
- `app/Jobs/Adaptation/EditorialVerificationJob.php` — marks session COMPLETED
- `app/Jobs/Adaptation/AdaptationStatusReconciliationJob.php` — Finalizer computing aggregate status

### Prompt Templates (19 files)
- `resources/views/ai/agents/adaptation/_master-context.blade.php` — Shared partial with 4 immovable laws and session structure
- 9 phase directories, each with `system-prompt.blade.php` + `prompt.blade.php`:
  - `format-detection/`, `ip-audit/`, `story-session-map/`, `entry-point-diagnosis/`, `session-architecture/`, `choice-design/`, `consequence-mapping/`, `session-close/`, `editorial-verification/`

---

## Files Modified

### Models (additive changes only)
- `app/Models/Story.php` — Added `adaptation()` hasOne relationship, import for HasOne
- `app/Models/Event.php` — Added `session_number` to phpdoc, added `sessionAdaptation()` convenience helper (non-hot-path only)
- `app/Models/Game.php` — Added phpdoc for 5 new columns, added JSON casts for branching_choices_taken, tracked_dimensions, branch_resolution_log

### Runtime Controllers (surgical additions)
- `app/Http/Controllers/User/Game/PromptController.php` — Added explicit SessionAdaptation query with readiness gate in `renderSystemPrompt()`. Added imports for SessionAdaptation and SessionAdaptationStatusEnum. All existing methods untouched.
- `app/Http/Controllers/User/GameController.php` — Added explicit SessionAdaptation query with readiness gate in `generateFirstNarration()`. Added imports. All existing methods untouched.

### Narration Template
- `resources/views/ai/agents/narration/system-prompt.blade.php` — Added adaptation injection block inside `@if` readiness gate (checks `session_status === COMPLETED`). Injects beat map, pre-authored branching choices, branch resolution behavioral rules, and consequence awareness. Block is skipped entirely when `$sessionAdaptation` is null.

### Filament
- `app/Filament/Creator/Resources/Stories/Pages/CreateStory.php` — Added `RunAdaptationPipelineJob` dispatch (2-minute delay) after existing jobs when `use_script_upload` is true.
- `app/Filament/Creator/Resources/Stories/Pages/ViewStory.php` — Added "Run Adaptation" and "Re-run Adaptation" header action buttons.
- `app/Filament/Creator/Resources/Stories/Schemas/StoryInfolist.php` — Added Adaptation section showing status badge and session progress.

---

## What Was Intentionally Left Unchanged

- `NarrationAgent` PHP class — schema stays: response, choices, advance_event
- `narration/prompt.blade.php` — user message template untouched
- All existing extractor agents (Chapter, Event, Objective/Attribute)
- All existing enums (StoryStatusEnum, ChapterStatusEnum)
- All existing jobs (ChapterExtractorJob, EventExtractorJob, etc.)
- All existing migration files
- Core runtime flow: turn loop, advance logic, 5-turn cap, fallback responses
- `PromptController.generateNarration()`, `buildConversationHistory()`, `findNextEvent()`, `getPreviousEvents()`, `getNextEvents()` — zero modifications

---

## Runtime Safety Notes

- **Null-safe chain**: `event.session_number` defaults to null. Explicit query returns null when no adaptation exists. Readiness gate checks `session_status === SessionAdaptationStatusEnum::COMPLETED`. Blade `@if` block skips when null. Runtime behavior for unadapted stories is identical to before.
- **No forced dependency**: No existing code path requires adaptation artifacts. They are purely additive context injected when available and complete.
- **Fallback preserved**: `PromptController` and `GameController` fallback responses remain untouched.

---

## Backward Compatibility Notes

- Stories created before the adaptation layer have no `StoryAdaptation` row, no `session_number` on events, and no runtime state on games. All null. Runtime behaves exactly as before.
- The `@if(!empty($sessionAdaptation) && ...)` gate in the narration template means old stories never see adaptation instructions.
- Existing games in progress are unaffected — their events have `session_number = null`.

---

## Unfinished Items / Future Work

- **Adaptation versioning**: `version` + `is_active` columns on `story_adaptations` for rollback and A/B testing. Deferred: adds complexity to every query.
- **Precomputed runtime payload**: `runtime_payload` JSON column on `session_adaptations` for faster prompt assembly. Deferred: Blade approach handles current scale.
- **Individual session retry**: Currently only full pipeline re-run is supported. Per-session retry from Filament is a future enhancement.
- **Beat type derivation at runtime**: `current_beat_type` on games is currently a cache placeholder. Full derivation from event position + beat map needs implementation when runtime consumes beat context actively.
- **Emergent branch promotion**: The structured `branch_resolution_log` captures emergent candidates, but the editorial review and promotion workflow is not yet built.
- **Runtime branch resolution classification**: The narrator system-prompt includes the behavioral rules, but programmatic classification (outside the LLM) for stricter enforcement is a future enhancement.

---

## Queue Configuration

All adaptation jobs run on the `adaptation` queue. Add this worker to your queue configuration:

```bash
php artisan queue:work --queue=adaptation
```

---

## Migration

Run migrations to create the new tables and add columns:

```bash
php artisan migrate
```
