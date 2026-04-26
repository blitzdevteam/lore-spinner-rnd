# Narrator Runtime Logic — What We Feed the AI and What We Keep From the Player

**Date:** 2026-04-26
**Scope:** End-to-end runtime for the in-game narrator (per-turn). Covers the system prompt, user-message prompt, conversation history shape, persistence, and what the UI actually receives.
**Agent:** `App\Ai\Agents\NarrationAgent` — model `gpt-5.2`, temperature `0.85`, timeout `60s`, structured output.

---

## 1. Pipeline at a glance

```
PLAYER INPUT ──┐
               │
               ▼
   StorePromptRequest.prompt ──► attached to *previous* prompts row as `prompt`
                                            │
                                            ▼
                              buildConversationHistory()
                              - last 6 prompts rows
                              - narrator HTML stripped
                              - __continue__ → "Continue forward"
                                            │
                                            ▼
              ┌──────────────────────────────────────────────┐
              │ NarrationAgent (gpt-5.2, temp 0.85)          │
              │                                              │
              │ instructions (system-prompt.blade.php):      │
              │  • LORESPINNER role + canon rules            │
              │  • characterName / worldRules / toneAndStyle │
              │  • previousEvents (titles + objectives, x3)  │
              │  • currentEvent (full content + objectives)  │
              │  • nextEvents (titles only, x2)              │
              │  • turnCount pacing rules                    │
              │  • sessionAdaptation (if COMPLETED):         │
              │      cold open, beat map, A/B/C branches,    │
              │      consequence map                         │
              │                                              │
              │ prompt (prompt.blade.php):                   │
              │  CONVERSATION SO FAR: [NARRATOR]/[PLAYER]…   │
              │  PLAYER'S ACTION: <raw input>                │
              └──────────────────────────────────────────────┘
                                            │
                          structured output ▼
                   { response: HTML, choices: [3], advance_event: bool }
                                            │
                                            ▼
                            new prompts row ──► PromptResource ──► UI
```

---

## 2. Code map (every component referenced below)

| Concern | Path |
|---|---|
| First narration (event-begin) | `app/Http/Controllers/User/GameController.php` |
| Per-turn narration (player input) | `app/Http/Controllers/User/Game/PromptController.php` |
| Player input request validation | `app/Http/Requests/User/Game/Prompt/StorePromptRequest.php` |
| Narration agent definition | `app/Ai/Agents/NarrationAgent.php` |
| System-prompt template | `resources/views/ai/agents/narration/system-prompt.blade.php` |
| User-message template | `resources/views/ai/agents/narration/prompt.blade.php` |
| Prompt → UI shape | `app/Http/Resources/PromptResource.php` |
| Adaptation layer source | `App\Models\SessionAdaptation` (read when `session_status === COMPLETED`) |

---

## 3. The agent (instructions/prompt split)

`App\Ai\Agents\NarrationAgent` accepts a runtime-rendered system prompt as `customInstructions` and exposes it via `instructions()`. The schema is enforced (structured output).

```16:38:app/Ai/Agents/NarrationAgent.php
#[Model('gpt-5.2')]
#[Temperature(0.85)]
#[Timeout(60)]
class NarrationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private string $customInstructions,
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->customInstructions;
    }
```

Structured output schema (the model MUST return these three fields):

```43:69:app/Ai/Agents/NarrationAgent.php
public function schema(JsonSchema $schema): array
{
    return [
        'response' => $schema
            ->string()
            ->required()
            ->title('Response')
            ->description('Cinematic narrative as HTML. Use <p> tags for paragraphs, <em> for emphasis, <strong> for impactful moments. Immersive, atmospheric, second-person. 2-4 paragraphs.'),
        'choices' => $schema
            ->array()
            ->required()
            ->title('Choices')
            ->description('Exactly 3 short actionable choices. Each starts with a strong verb. Ordered by forward momentum: most forward, moderate, least forward (but still changes state).')
            ->items(
                $schema
                    ->string()
                    ->required()
                    ->title('Choice')
                    ->description('A single concrete, actionable choice starting with a strong verb.')
            ),
        'advance_event' => $schema
            ->boolean()
            ->required()
            ->title('Advance Event')
            ->description('True when the current event\'s core dramatic beats have been sufficiently explored and the player\'s action naturally exits or completes the scene. False when the player is still engaging within the current event.'),
    ];
}
```

---

## 4. What we feed — the **system prompt** (`instructions`)

Rendered fresh per turn by `PromptController::renderSystemPrompt()`:

```165:181:app/Http/Controllers/User/Game/PromptController.php
return view('ai.agents.narration.system-prompt', [
    'characterName' => $storyData['character_name'] ?? null,
    'worldRules' => $storyData['world_rules'] ?? [],
    'toneAndStyle' => $storyData['tone_and_style'] ?? null,
    'previousEvents' => $this->getPreviousEvents($currentEvent, 3),
    'currentEvent' => [
        'position' => $currentEvent->position,
        'title' => $currentEvent->title,
        'content' => $currentEvent->content,
        'objectives' => $currentEvent->objectives,
        'attributes' => $currentEvent->attributes,
    ],
    'nextEvents' => $this->getNextEvents($currentEvent, 2),
    'turnCount' => $turnCount,
    'sessionAdaptation' => $sessionAdaptation,
    'isSessionStart' => $isSessionStart,
])->render();
```

### 4.1 Variable origins

| Variable | Source | Notes |
|---|---|---|
| `characterName` | `story.system_prompt.character_name` | Drives POV (2nd person if PC present, else 3rd person) |
| `worldRules` | `story.system_prompt.world_rules` | Listed verbatim as "GLOBAL WORLD RULES" |
| `toneAndStyle` | `story.system_prompt.tone_and_style` | One-line style directive |
| `previousEvents` | DB, last 3 events across chapter boundaries | Only `position`, `title`, `objectives` — **not full text** (continuity context, not re-narratable) |
| `currentEvent` | DB | `position`, `title`, full `content` (screenplay), `objectives`, `attributes` (JSON) |
| `nextEvents` | DB, next 2 events across chapter boundaries | `position` + `title` only — **explicitly no spoilers** |
| `turnCount` | `count(prompts where event_id = currentEvent->id)` | Drives wrap-up pressure at turn 2/3/4+ |
| `sessionAdaptation` | `SessionAdaptation` row matched by `story_id` + `session_number`, only kept if `session_status === COMPLETED` | Adds adaptation-layer block (cold open, beat map, branching choices, consequence map) |
| `isSessionStart` | `currentEvent->id === entry_point_diagnosis.start_event_id && turnCount === 0` | Triggers cold-open-guidance block |

Continuity windows:

```219:251:app/Http/Controllers/User/Game/PromptController.php
private function getPreviousEvents(Event $currentEvent, int $take = 3): array
{
    $events = Event::query()
        ->where('chapter_id', $currentEvent->chapter_id)
        ->where('position', '<', $currentEvent->position)
        ->orderByDesc('position')
        ->take($take)
        ->get();

    if ($events->count() < $take) {
        $remaining = $take - $events->count();

        $prevChapter = Chapter::query()
            ->where('story_id', $currentEvent->chapter->story_id)
            ->where('position', '<', $currentEvent->chapter->position)
            ->orderByDesc('position')
            ->first();

        if ($prevChapter) {
            $events = $events->merge(
                $prevChapter->events()->orderByDesc('position')->take($remaining)->get()
            );
        }
    }

    return $events->sortBy('position')
        ->map(fn (Event $event): array => [
            'position' => $event->position,
            'title' => $event->title,
            'objectives' => $event->objectives,
        ])
        ->values()
        ->all();
}
```

```260:289:app/Http/Controllers/User/Game/PromptController.php
private function getNextEvents(Event $currentEvent, int $take = 2): array
{
    $events = Event::query()
        ->where('chapter_id', $currentEvent->chapter_id)
        ->where('position', '>', $currentEvent->position)
        ->orderBy('position')
        ->take($take)
        ->get();

    if ($events->count() < $take) {
        $remaining = $take - $events->count();

        $nextChapter = Chapter::query()
            ->where('story_id', $currentEvent->chapter->story_id)
            ->where('position', '>', $currentEvent->chapter->position)
            ->orderBy('position')
            ->first();

        if ($nextChapter) {
            $events = $events->merge(
                $nextChapter->events()->orderBy('position')->take($remaining)->get()
            );
        }
    }

    return $events->map(fn (Event $event): array => [
        'position' => $event->position,
        'title' => $event->title,
    ])->all();
}
```

### 4.2 Concrete blocks the model receives

The full template lives at `resources/views/ai/agents/narration/system-prompt.blade.php`. Key blocks (verbatim):

**Role + canon-fidelity** (`system-prompt.blade.php` lines 1–37):

```1:37:resources/views/ai/agents/narration/system-prompt.blade.php
=== SYSTEM ROLE ===
You are LORESPINNER — an interactive cinematic story narrator in a playable game.

Your job is to render the CURRENT_EVENT as an interactive scene:
- Preserve canonical facts and verbatim dialogue.
- Convert screenplay actions into cinematic prose with temperature.
- Treat the player's message as the driver of what happens next.

@if(!empty($characterName))
The main playable character is **{{ $characterName }}**.
Other characters act autonomously and keep their voices and actions consistent.
@endif

@if(!empty($worldRules))
=== GLOBAL WORLD RULES ===
@foreach($worldRules as $rule)
- {{ $rule }}
@endforeach

These rules are externally visible and must be followed strictly.

@endif
=== EVENT DATA FORMAT ===
Each <Event> contains:
- text: The verbatim screenplay content (canonical source of facts).
- objectives: A factual past-tense description of what observably occurred.

EVENT.text defines WHAT happens (facts and order).
EVENT.objectives are context only and do NOT authorize new plot.

=== CANON FIDELITY RULE ===
- Dialogue MUST remain verbatim (exact spoken words) when you include it.
- Actions are canonical AS FACTS, not wording:
  • Preserve what happens and in what order.
  • Rewrite action lines into cinematic prose with temperature.
- NEVER output screenplay terms (V.O., O.S., INT., EXT., CONT'D, CUT TO, FADE, SMASH CUT, etc.) — strip all script formatting and narrate the content purely as story.
- Never output any sentence that appears verbatim in EVENT.text unless it is dialogue.
```

**Contextual reference (events window)** (`system-prompt.blade.php` lines 39–70):

```39:70:resources/views/ai/agents/narration/system-prompt.blade.php
=== CONTEXTUAL REFERENCE ===
@if(!empty($previousEvents))
--- PREVIOUS EVENTS (continuity only — do NOT narrate) ---
@foreach($previousEvents as $prev)
<Event position="{{ $prev['position'] }}" title="{{ $prev['title'] }}">
@if(!empty($prev['objectives']))
Objectives: {{ $prev['objectives'] }}
@endif
</Event>
@endforeach

@endif
--- CURRENT EVENT ---
<Event position="{{ $currentEvent['position'] }}" title="{{ $currentEvent['title'] }}">
Text: {{ $currentEvent['content'] }}
@if(!empty($currentEvent['objectives']))
Objectives: {{ $currentEvent['objectives'] }}
@endif
@if(!empty($currentEvent['attributes']))
Attributes: {{ json_encode($currentEvent['attributes']) }}
@endif
</Event>

@if(!empty($nextEvents))
--- UPCOMING EVENTS (pacing awareness only — do NOT narrate, spoil, or reference) ---
@foreach($nextEvents as $next)
<Event position="{{ $next['position'] }}" title="{{ $next['title'] }}" />
@endforeach

@endif
```

**Turn-based pacing pressure** (`system-prompt.blade.php` lines 216–225):

```216:225:resources/views/ai/agents/narration/system-prompt.blade.php
@if(!empty($turnCount))
This is turn {{ $turnCount }} in this event.
@if($turnCount == 2)
PACING: The scene has been active for a few turns. Ensure all three choices push the scene forward. Prefer setting advance_event = true if the player takes any forward action.
@elseif($turnCount == 3)
PACING: This scene has run long. You SHOULD wrap it up — narrate a satisfying closing beat for the player's action and set advance_event = true. Only hold if the player is genuinely mid-interaction with a character.
@elseif($turnCount >= 4)
PACING: This is the FINAL turn for this scene. Narrate a natural, satisfying transition that honors what the player just did, then set advance_event = true. Wrap any open thread with a brief closing beat. No exceptions.
@endif
@endif
```

**Adaptation-layer block** (gated on `session_status === COMPLETED`) — cold open, beat map, pre-authored A/B/C branching choices, consequence map (`system-prompt.blade.php` lines 227–316). Excerpt:

```227:248:resources/views/ai/agents/narration/system-prompt.blade.php
@if(!empty($sessionAdaptation) && $sessionAdaptation->session_status === \App\Enums\Adaptation\SessionAdaptationStatusEnum::COMPLETED)
=== ADAPTATION LAYER CONTEXT ===
This scene is part of a pre-designed interactive session. You are the director and performer — execute the designed structure while keeping narration natural and immersive.

SESSION: {{ $sessionAdaptation->session_number }}

@if(!empty($isSessionStart) && !empty($sessionAdaptation->entry_point_diagnosis))
@php $entryPoint = $sessionAdaptation->entry_point_diagnosis; @endphp
--- SESSION COLD OPEN GUIDANCE ---
This is the OPENING of this session. The following cold open defines the tone, sensory texture, and emotional direction for your first response. Use it as your creative brief --- match its energy, pacing, and atmospheric intent --- but generate your own narration in your voice and HTML format. Do not copy it verbatim.

COLD OPEN DIRECTION:
{{ $entryPoint['cold_open'] ?? '' }}

EMOTIONAL PROMISE: {{ $entryPoint['emotional_promise'] ?? '' }}

@if(!empty($entryPoint['format_specific_cut']['must_reintroduce']))
CUT MATERIAL TO REINTRODUCE:
The following information was cut from before this starting point but is essential context. Weave it naturally into your narration through action, dialogue, or environmental detail --- never as exposition dump:
{{ $entryPoint['format_specific_cut']['must_reintroduce'] }}
@endif
@endif
```

---

## 5. What we feed — the **user message** (`prompt`)

The user-message template is short and is rendered every turn:

```1:16:resources/views/ai/agents/narration/prompt.blade.php
@if(!empty($conversationHistory))
CONVERSATION SO FAR:
@foreach($conversationHistory as $turn)
@if($turn['role'] === 'narrator')
[NARRATOR]: {!! $turn['text'] !!}
@else
[PLAYER]: {{ $turn['text'] }}
@endif
@endforeach

@endif
@if(!empty($playerAction))
PLAYER'S ACTION: {{ $playerAction }}
@else
This is the OPENING of the event. Narrate the scene cinematically and present the first set of choices.
@endif
```

`conversationHistory` is built from the most recent **6 `prompts` rows**, replayed oldest→newest, with the narrator HTML flattened to plain text:

```189:211:app/Http/Controllers/User/Game/PromptController.php
private function buildConversationHistory(Game $game): array
{
    $history = [];

    $prompts = $game->prompts()
        ->latest()
        ->limit(6)
        ->get()
        ->reverse();

    foreach ($prompts as $p) {
        if ($p->response) {
            $history[] = ['role' => 'narrator', 'text' => strip_tags($p->response)];
        }
        if ($p->prompt && $p->prompt !== '__continue__') {
            $history[] = ['role' => 'player', 'text' => $p->prompt];
        } elseif ($p->prompt === '__continue__') {
            $history[] = ['role' => 'player', 'text' => 'Continue forward'];
        }
    }

    return $history;
}
```

Rules in effect:

- **Narrator turns are HTML-stripped** (`strip_tags`) — the model sees plain text only, never the `<p>/<em>/<strong>` markup it produced earlier.
- **`__continue__` sentinel** (the "Continue forward" UI button) is rewritten to the literal string `"Continue forward"` both inside the history transcript and as `PLAYER'S ACTION`.
- **First turn / opening** — when there is no `playerAction`, the template falls through to the `OPENING of the event` instruction line. This is what `GameController::begin()` triggers for a fresh event (see §7).

---

## 6. What we keep from the player's input

`PromptController::store()` is the single ingestion point per turn:

```23:53:app/Http/Controllers/User/Game/PromptController.php
public function store(
    #[CurrentUser] User $user,
    Game $game,
    StorePromptRequest $request,
): RedirectResponse {
    $prompt = $request->string('prompt')->toString();
    $isContinue = $prompt === '__continue__';

    $game->prompts()->latest()->first()?->update([
        'prompt' => $isContinue ? '__continue__' : $prompt,
    ]);

    $currentEvent = $game->currentEvent;

    $turnCount = $game->prompts()
        ->where('event_id', $currentEvent->id)
        ->count();

    $conversationHistory = $this->buildConversationHistory($game);

    $systemPrompt = $this->renderSystemPrompt(
        story: $game->story,
        currentEvent: $currentEvent,
        turnCount: $turnCount,
    );

    $aiResult = $this->generateNarration(
        systemPrompt: $systemPrompt,
        conversationHistory: $conversationHistory,
        playerAction: $isContinue ? 'Continue forward' : $prompt,
    );
```

Key persistence semantics:

| Field | What we store | Where |
|---|---|---|
| `prompts.prompt` | **Raw player text** (or `__continue__` sentinel) | Attached to the **previous** prompts row — i.e. the narration the player was responding to |
| `prompts.response` | AI's HTML narration | New prompts row (created after AI call) |
| `prompts.choices` | AI's exactly-3 verb-led choice strings | New prompts row |
| `prompts.event_id` | `nextEvent->id` if `advance_event = true` (or forced advance), else `currentEvent->id` | New prompts row |
| `games.current_event_id` | Updated only on advancement | `Game` table |

Forced advancement guard (anti-stall):

```55:59:app/Http/Controllers/User/Game/PromptController.php
$shouldAdvance = $aiResult['advance_event'];

if (! $shouldAdvance && $turnCount >= 5) {
    $shouldAdvance = true;
}
```

Session boundary cut adjustment (when the next event is in a new session and that session has a `COMPLETED` `SessionAdaptation`, we may relocate the start event):

```89:112:app/Http/Controllers/User/Game/PromptController.php
private function applySessionTransitionCut(Event $nextEvent, Game $game): Event
{
    $nextSessionAdaptation = SessionAdaptation::query()
        ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $game->story_id))
        ->where('session_number', $nextEvent->session_number)
        ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
        ->first();

    $startEventId = $nextSessionAdaptation?->entry_point_diagnosis['start_event_id'] ?? null;

    if ($startEventId === null) {
        return $nextEvent;
    }

    $cutAdjusted = Event::find($startEventId);

    if ($cutAdjusted
        && $cutAdjusted->session_number === $nextEvent->session_number
        && $cutAdjusted->chapter->story_id === $game->story_id) {
        return $cutAdjusted;
    }

    return $nextEvent;
}
```

---

## 7. First-turn (event opening) path

For the very first narration when an event is entered with no prior prompts, the path differs slightly. `GameController::begin()` calls `generateFirstNarration()`:

```110:170:app/Http/Controllers/User/GameController.php
private function generateFirstNarration(Story $story, Event $firstEvent): array
{
    $storyData = $story->system_prompt ?? [];

    $nextEvents = Event::query()
        ->where('chapter_id', $firstEvent->chapter_id)
        ->where('position', '>', $firstEvent->position)
        ->orderBy('position')
        ->take(2)
        ->get()
        ->map(fn (Event $event): array => [
            'position' => $event->position,
            'title' => $event->title,
        ])
        ->all();

    $sessionAdaptation = null;

    if ($firstEvent->session_number !== null) {
        $sessionAdaptation = SessionAdaptation::query()
            ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $story->id))
            ->where('session_number', $firstEvent->session_number)
            ->first();

        if ($sessionAdaptation?->session_status !== SessionAdaptationStatusEnum::COMPLETED) {
            $sessionAdaptation = null;
        }
    }

    $systemPrompt = view('ai.agents.narration.system-prompt', [
        'characterName' => $storyData['character_name'] ?? null,
        'worldRules' => $storyData['world_rules'] ?? [],
        'toneAndStyle' => $storyData['tone_and_style'] ?? null,
        'previousEvents' => [],
        'currentEvent' => [
            'position' => $firstEvent->position,
            'title' => $firstEvent->title,
            'content' => $firstEvent->content,
            'objectives' => $firstEvent->objectives,
            'attributes' => $firstEvent->attributes,
        ],
        'nextEvents' => $nextEvents,
        'sessionAdaptation' => $sessionAdaptation,
        'isSessionStart' => true,
    ])->render();

    try {
        /** @var StructuredAgentResponse $response */
        $response = NarrationAgent::make(customInstructions: $systemPrompt)
            ->prompt(
                view('ai.agents.narration.prompt', [
                    'conversationHistory' => [],
                    'playerAction' => '',
                ])->render()
            );
```

Differences vs. per-turn:

- `previousEvents = []` (no continuity context).
- `conversationHistory = []` and `playerAction = ''` → user-message template falls through to the OPENING line.
- `isSessionStart = true` (forces the cold-open guidance block whenever a `COMPLETED` adaptation exists).
- `turnCount` is omitted (no pacing pressure on opener).

---

## 8. What the UI actually receives

`PromptResource` defines the only fields rendered to the player:

```22:34:app/Http/Resources/PromptResource.php
return [
    'id' => $this->id,
    'game_id' => $this->game_id,
    'event_id' => $this->event_id,
    'prompt' => $this->prompt ?? '',
    'response' => $this->response,
    'choices' => $this->choices ?? [],

    // Relations
    'game' => GameResource::make($this->whenLoaded('game')),
    'event' => EventResource::make($this->whenLoaded('event')),
];
```

Result: the player sees their own input echoed back as `prompt` on the row they responded to, plus the narrator's `response` (HTML) and `choices` (3 verb-led strings) on the new row.

---

## 9. Side-by-side summary

### What we feed the AI

| Channel | Block | Source | Per-turn? |
|---|---|---|---|
| `instructions` | Static role/canon/spoiler/POV/choice/output rules | template literals | static |
| `instructions` | `characterName`, `worldRules`, `toneAndStyle` | `story.system_prompt` JSON | static-per-story |
| `instructions` | `previousEvents` (3, titles + objectives) | DB query, cross-chapter | per-turn |
| `instructions` | `currentEvent` (full content + objectives + attributes JSON) | DB | per-turn |
| `instructions` | `nextEvents` (2, titles only) | DB query, cross-chapter | per-turn |
| `instructions` | Pacing-pressure block | `turnCount` (count of prompts on event) | per-turn |
| `instructions` | Adaptation-layer block (cold open / beat map / A/B/C / consequence map) | `SessionAdaptation` (only if `COMPLETED`) | per-turn (gated) |
| `prompt` | Last 6 prompts as `[NARRATOR]/[PLAYER]` transcript, narrator HTML-stripped | `prompts` table | per-turn |
| `prompt` | `PLAYER'S ACTION: <text>` (or OPENING fallback) | request payload | per-turn |

### What we keep from the player

| Keep | How | Surface |
|---|---|---|
| Raw player text (or `__continue__`) | Persisted on the **previous** `prompts` row's `prompt` column | UI displays it back on the prior turn |
| Player intent into the model | Last 6 turns echoed in `CONVERSATION SO FAR`, plus current `PLAYER'S ACTION` | Used by model to drive narration |
| Implicit state shifts | Encoded in `advance_event` consequence (controller advances `current_event_id` and stamps `event_id` on the new prompts row) | Drives event/session progression and session-cut adjustment |

---

## 10. Notes worth flagging (not changes — observations)

1. **Player text is sent to the model unsanitized.** `{{ $playerAction }}` Blade-escapes for HTML output, but the prompt is delivered to the model as a string — there is no current guard against prompt-injection attempts inside player freeform input.
2. **Conversation history can balloon.** `latest()->limit(6)` means up to 6 prior prompts × 2–4 paragraphs of HTML-stripped narrator text, with no length normalization.
3. **`turnCount` is the count of prompts already attached to the current event** (not including the not-yet-created row of the in-flight turn). Forced advancement triggers when `turnCount >= 5` even if the model returns `advance_event = false`.
4. **Adaptation-layer block is opt-in by data.** It only injects when a `SessionAdaptation` row exists for the current `session_number` AND its `session_status === COMPLETED`. Otherwise the system prompt runs purely on story + event context.
