<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Agents\NarrationAgent;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Models\Event;
use App\Models\SessionAdaptation;
use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Neutrally traces what happens when a new game is started.
 *
 * Replicates, without creating any DB rows:
 *   - CreateGameAction::resolveStartEvent  (which event the game starts on)
 *   - GameController::generateFirstNarration  (system prompt + NarrationAgent call)
 *
 * Actually invokes the LLM and prints back what the player would see on the
 * first screen (response HTML + three choices + advance_event flag).
 *
 * This command reports facts. It does NOT judge whether "session 1 cold open
 * fires" — it just shows what the flow returns.
 */
final class SimulateGameStartCommand extends Command
{
    protected $signature = 'game:simulate-start
        {story? : Story ID or slug (defaults to latest)}
        {--dry : Skip the LLM call; dump the rendered system prompt only}
        {--out= : Custom output directory for saved prompts (defaults to storage/app/debug)}';

    protected $description = 'Trace what a new game start returns: resolved event + session adaptation + live LLM response';

    public function handle(): int
    {
        $story = $this->resolveStory();

        if (! $story) {
            $this->error('Story not found.');

            return self::FAILURE;
        }

        $this->info('=== STORY ===');
        $this->line("id:    {$story->id}");
        $this->line("title: {$story->title}");
        $this->line('slug:  ' . ($story->slug ?? '(none)'));
        $this->newLine();

        $firstChapter = $story->chapters()->orderBy('position')->first();

        if (! $firstChapter) {
            $this->error('Story has no chapters.');

            return self::FAILURE;
        }

        $firstEvent = $firstChapter->events()->orderBy('position')->first();

        if (! $firstEvent) {
            $this->error('Story has no events.');

            return self::FAILURE;
        }

        $startEvent = $this->resolveStartEvent($story, $firstEvent);
        $this->reportStartEventResolution($story, $firstEvent, $startEvent);

        $sessionAdaptation = $this->resolveSessionAdaptation($story, $startEvent);
        $this->reportSessionAdaptation($startEvent, $sessionAdaptation);

        $systemPrompt = $this->renderSystemPrompt($story, $startEvent, $sessionAdaptation);
        $userPrompt = $this->renderUserPrompt();

        $promptPath = $this->writePrompt($story, $systemPrompt, $userPrompt);
        $this->line('System prompt written to:');
        $this->line("  {$promptPath}");
        $this->newLine();

        if ($this->option('dry')) {
            $this->warn('--dry flag set; skipping LLM call.');

            return self::SUCCESS;
        }

        $this->info('=== CALLING NARRATION AGENT ===');
        $this->line('(this invokes the real LLM; may take 20-60s)');
        $this->newLine();

        $result = $this->invokeNarrationAgent($systemPrompt, $userPrompt);

        if ($result === null) {
            return self::FAILURE;
        }

        $this->reportLlmResponse($result);

        return self::SUCCESS;
    }

    private function resolveStory(): ?Story
    {
        $identifier = $this->argument('story');

        if (! $identifier) {
            return Story::latest()->first();
        }

        if (is_numeric($identifier)) {
            return Story::find((int) $identifier);
        }

        return Story::where('slug', $identifier)->first();
    }

    /**
     * Mirror of App\Actions\Game\CreateGameAction::resolveStartEvent.
     * Keeps the same gate semantics so this command reports what the real
     * flow would do, not what we wish it would do.
     */
    private function resolveStartEvent(Story $story, Event $firstEvent): Event
    {
        $adaptation = $story->adaptation;

        if ($adaptation?->adaptation_status !== AdaptationStatusEnum::COMPLETED) {
            return $firstEvent;
        }

        $session1 = $adaptation->sessionAdaptations()
            ->where('session_number', 1)
            ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
            ->first();

        $startEventId = $session1?->entry_point_diagnosis['start_event_id'] ?? null;

        if ($startEventId === null) {
            return $firstEvent;
        }

        $resolved = Event::find($startEventId);

        if ($resolved
            && $resolved->chapter->story_id === $story->id
            && $resolved->session_number === 1) {
            return $resolved;
        }

        return $firstEvent;
    }

    private function reportStartEventResolution(Story $story, Event $firstEvent, Event $startEvent): void
    {
        $adaptation = $story->adaptation;

        $this->info('=== START EVENT RESOLUTION (mirrors CreateGameAction) ===');
        $this->line('adaptation present:   ' . ($adaptation ? 'yes' : 'no'));
        $this->line('adaptation_status:    ' . ($adaptation?->adaptation_status?->value ?? 'n/a'));
        $this->line('first event (chap 1): id=' . $firstEvent->id . ', pos=' . $firstEvent->position . ', title="' . $firstEvent->title . '", session=' . ($firstEvent->session_number ?? 'null'));
        $this->line('resolved start event: id=' . $startEvent->id . ', pos=' . $startEvent->position . ', title="' . $startEvent->title . '", session=' . ($startEvent->session_number ?? 'null'));

        if ($startEvent->id === $firstEvent->id) {
            $this->line('  -> start event unchanged from chapter-1 event 1');
        } else {
            $this->line("  -> start event shifted by " . ($startEvent->position - $firstEvent->position) . " position(s)");
        }

        $this->newLine();
    }

    /**
     * Mirror of App\Http\Controllers\User\GameController::generateFirstNarration
     * session lookup block.
     */
    private function resolveSessionAdaptation(Story $story, Event $startEvent): ?SessionAdaptation
    {
        if ($startEvent->session_number === null) {
            return null;
        }

        $session = SessionAdaptation::query()
            ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $story->id))
            ->where('session_number', $startEvent->session_number)
            ->first();

        if ($session?->session_status !== SessionAdaptationStatusEnum::COMPLETED) {
            return null;
        }

        return $session;
    }

    private function reportSessionAdaptation(Event $startEvent, ?SessionAdaptation $session): void
    {
        $this->info('=== SESSION ADAPTATION LOOKUP (mirrors generateFirstNarration) ===');
        $this->line('start event session_number: ' . ($startEvent->session_number ?? 'null'));

        if ($session === null) {
            $this->line('matched session_adaptation: (none — null will be passed to blade)');
            $this->newLine();

            return;
        }

        $this->line('matched session_adaptation: id=' . $session->id . ', session_number=' . $session->session_number . ', status=' . $session->session_status->value);
        $this->line('  entry_point_diagnosis:   ' . ($session->entry_point_diagnosis ? 'present' : 'null'));
        $this->line('  session_architecture:    ' . ($session->session_architecture ? 'present' : 'null'));
        $this->line('  session_choice_design:   ' . ($session->session_choice_design ? 'present' : 'null'));
        $this->line('  choice_consequence_map:  ' . ($session->choice_consequence_map ? 'present' : 'null'));

        $this->newLine();
    }

    private function renderSystemPrompt(Story $story, Event $startEvent, ?SessionAdaptation $sessionAdaptation): string
    {
        $storyData = $story->system_prompt ?? [];

        $nextEvents = Event::query()
            ->where('chapter_id', $startEvent->chapter_id)
            ->where('position', '>', $startEvent->position)
            ->orderBy('position')
            ->take(2)
            ->get()
            ->map(fn (Event $event): array => [
                'position' => $event->position,
                'title' => $event->title,
            ])
            ->all();

        return View::make('ai.agents.narration.system-prompt', [
            'characterName' => $storyData['character_name'] ?? null,
            'worldRules' => $storyData['world_rules'] ?? [],
            'toneAndStyle' => $storyData['tone_and_style'] ?? null,
            'previousEvents' => [],
            'currentEvent' => [
                'position' => $startEvent->position,
                'title' => $startEvent->title,
                'content' => $startEvent->content,
                'objectives' => $startEvent->objectives,
                'attributes' => $startEvent->attributes,
            ],
            'nextEvents' => $nextEvents,
            'sessionAdaptation' => $sessionAdaptation,
            'isSessionStart' => true,
        ])->render();
    }

    private function renderUserPrompt(): string
    {
        return View::make('ai.agents.narration.prompt', [
            'conversationHistory' => [],
            'playerAction' => '',
        ])->render();
    }

    private function writePrompt(Story $story, string $systemPrompt, string $userPrompt): string
    {
        $dir = $this->option('out') ?? storage_path('app/debug');
        File::ensureDirectoryExists($dir);

        $slug = $story->slug ?? 'story-' . $story->id;
        $timestamp = now()->format('Y-m-d_His');
        $path = "{$dir}/simulated-start-{$slug}-{$timestamp}.txt";

        $contents = "=== SYSTEM PROMPT ===\n\n"
            . $systemPrompt
            . "\n\n=== USER PROMPT ===\n\n"
            . $userPrompt;

        File::put($path, $contents);

        return $path;
    }

    /**
     * @return array{response: string, choices: array<int, string>, advance_event: bool}|null
     */
    private function invokeNarrationAgent(string $systemPrompt, string $userPrompt): ?array
    {
        try {
            /** @var StructuredAgentResponse $response */
            $response = NarrationAgent::make(customInstructions: $systemPrompt)
                ->prompt($userPrompt);

            return [
                'response' => $response['response'] ?? '',
                'choices' => $response['choices'] ?? [],
                'advance_event' => (bool) ($response['advance_event'] ?? false),
            ];
        } catch (Throwable $e) {
            $this->error('LLM call failed: ' . $e::class . ': ' . $e->getMessage());
            $this->warn('In the real flow, GameController::begin would fall back to the raw event content wrapped in <p>.');

            return null;
        }
    }

    /**
     * @param  array{response: string, choices: array<int, string>, advance_event: bool}  $result
     */
    private function reportLlmResponse(array $result): void
    {
        $this->info('=== LLM RESPONSE (what the player sees on first screen) ===');
        $this->newLine();

        $this->line('--- response (HTML) ---');
        $this->line($result['response'] !== '' ? $result['response'] : '(empty)');
        $this->newLine();

        $this->line('--- response (plain text preview) ---');
        $plain = trim(html_entity_decode(strip_tags($result['response'])));
        $this->line($plain !== '' ? $plain : '(empty)');
        $this->newLine();

        $this->line('--- choices ---');
        if (empty($result['choices'])) {
            $this->line('(none returned)');
        } else {
            foreach ($result['choices'] as $i => $choice) {
                $letter = chr(ord('A') + $i);
                $this->line("  {$letter}) {$choice}");
            }
        }
        $this->newLine();

        $this->line('advance_event: ' . ($result['advance_event'] ? 'true' : 'false'));
    }
}
