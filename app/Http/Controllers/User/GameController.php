<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\Game\CreateGameAction;
use App\Ai\Agents\NarrationAgent;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Game\StoreGameRequest;
use App\Models\Event;
use App\Models\Game;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Response;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

final class GameController extends Controller
{
    public function index(): RedirectResponse
    {
        return to_route('index');
    }

    public function show(Game $game): Response
    {
        $game->load([
            'story',
            'currentEvent.chapter',
            'prompts' => fn ($q) => $q
                ->select(['id', 'game_id', 'event_id', 'response', 'choices', 'prompt'])
                ->oldest(),
            'prompts.event',
        ]);

        return inertia('User/Games/Show', [
            'game' => $game->toResource()
        ]);
    }

    public function store(
        #[CurrentUser] User $user,
        StoreGameRequest $request,
        CreateGameAction $createGameAction
    ): RedirectResponse {
        $story = Story::find($request->string('story_id')->toString());

        $existingGame = $user->games()->whereBelongsTo($story)->first();

        if ($existingGame) {
            return to_route('user.games.show', $existingGame);
        }

        $game = $createGameAction->handle($user, $story);

        return to_route('user.games.show', $game);
    }

    public function reset(Game $game, CreateGameAction $createGameAction): RedirectResponse
    {
        $story = $game->story;
        $firstChapter = $story->chapters()->orderBy('position')->first();
        $firstEvent = $firstChapter?->events()->orderBy('position')->first();

        if ($firstEvent === null) {
            return to_route('user.games.show', $game);
        }

        $startEvent = $createGameAction->resolveStartEvent($story, $firstEvent);

        $game->prompts()->delete();
        $game->update([
            'current_event_id' => $startEvent->id,
            'current_session_number' => $startEvent->session_number,
            'current_beat_type' => null,
            'branching_choices_taken' => null,
            'tracked_dimensions' => null,
            'branch_resolution_log' => null,
            'world_state' => null,
        ]);

        return to_route('user.games.show', $game);
    }

    public function begin(Game $game): RedirectResponse
    {
        if ($game->prompts()->exists()) {
            return to_route('user.games.show', $game);
        }

        $story = $game->story;
        $firstEvent = $game->currentEvent;

        try {
            $aiResult = $this->generateFirstNarration($story, $firstEvent);
        } catch (Throwable) {
            // generateFirstNarration logged the exception (narration.llm_failed). Do NOT
            // insert a prompt row — that would persist a fake "scene unfolds" first beat.
            // Player can hit Begin again once the upstream issue is resolved.
            return to_route('user.games.show', $game)
                ->with('error', 'Opening narration hiccuped — please retry begin.');
        }

        $game->prompts()->create([
            'event_id' => $firstEvent->id,
            'response' => $aiResult['response'],
            'choices' => $aiResult['choices'],
        ]);

        return to_route('user.games.show', $game);
    }

    /**
     * @return array{response: string, choices: string[]}
     */
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
                ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
                ->first();
        }

        if ($sessionAdaptation === null) {
            $sessionAdaptation = SessionAdaptation::query()
                ->whereHas('storyAdaptation', fn ($q) => $q->where('story_id', $story->id))
                ->where('session_number', 1)
                ->where('session_status', SessionAdaptationStatusEnum::COMPLETED)
                ->first();
        }

        $coldOpenText = (string) ($sessionAdaptation?->entry_point_diagnosis['cold_open'] ?? '');
        Log::channel('narration')->info('narration.cold_open_audit', [
            'story_id' => $story->id,
            'event_id' => $firstEvent->id,
            'session_adaptation_resolved' => $sessionAdaptation !== null,
            'session_number' => $sessionAdaptation?->session_number,
            'cold_open_present' => $coldOpenText !== '',
            'cold_open_first_120' => $coldOpenText !== '' ? mb_substr($coldOpenText, 0, 120) : null,
        ]);

        $systemPrompt = view('ai.agents.narration.system-prompt', [
            'characterName' => $storyData['character_name'] ?? null,
            'worldRules' => $storyData['world_rules'] ?? [],
            'toneAndStyle' => $storyData['tone_and_style'] ?? null,
            'previousEvents' => [],
            'currentEvent' => [
                'position'        => $firstEvent->position,
                'title'           => $firstEvent->title,
                'content'         => $firstEvent->content,
                'objectives'      => $firstEvent->objectives,
                'attributes'      => $firstEvent->attributes,
                'requires_choice' => $firstEvent->requires_choice ?? true,
            ],
            'nextEvents' => $nextEvents,
            'turnCount' => 0,
            'isFirstTurnInEvent' => true,
            'sessionAdaptation' => $sessionAdaptation,
            'isSessionStart' => true,
            'worldState' => [],
            'deterministicMatch' => null,
        ])->render();

        try {
            /** @var StructuredAgentResponse $response */
            $response = NarrationAgent::make(customInstructions: $systemPrompt)
                ->prompt(
                    view('ai.agents.narration.prompt', [
                        'conversationHistory' => [],
                        'playerAction' => '',
                        'deterministicMatch' => null,
                    ])->render()
                );

            $responseHtml = (string) ($response['response'] ?? '');
            $choices = $response['choices'] ?? [];

            Log::channel('narration')->info('narration.llm_success', [
                'site' => 'first_narration',
                'story_id' => $story->id,
                'event_id' => $firstEvent->id,
                'response_bytes' => strlen($responseHtml),
                'choices_count' => count($choices),
                'system_prompt_bytes' => strlen($systemPrompt),
                'cold_open_present' => $coldOpenText !== '',
            ]);

            return [
                'response' => $responseHtml,
                'choices' => $choices,
            ];
        } catch (Throwable $e) {
            Log::channel('narration')->error('narration.llm_failed', [
                'site' => 'first_narration',
                'story_id' => $story->id,
                'event_id' => $firstEvent->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'system_prompt_bytes' => strlen($systemPrompt),
                'cold_open_present' => $coldOpenText !== '',
            ]);

            throw $e;
        }
    }

    /**
     * Convert source-file event content into safe paragraph HTML without
     * preserving its hard line wraps. Source scripts (e.g. Project Gutenberg)
     * are pre-wrapped at ~67 columns; naive nl2br() would turn every wrap
     * into a forced <br>, producing a visibly narrow, ragged column in the UI.
     */
    private function formatEventContentAsHtml(string $content): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($content));

        if ($normalized === '') {
            return '<p>The scene unfolds before you...</p>';
        }

        $paragraphs = preg_split('/\n\s*\n+/', $normalized) ?: [];

        $html = '';
        foreach ($paragraphs as $paragraph) {
            $flattened = preg_replace('/\s*\n\s*/', ' ', trim($paragraph));
            if ($flattened === null || $flattened === '') {
                continue;
            }
            $html .= '<p>' . e($flattened) . '</p>';
        }

        return $html !== '' ? $html : '<p>The scene unfolds before you...</p>';
    }
}
