<?php

declare(strict_types=1);

namespace Tests\Support\Analytics;

use App\Models\Game;
use App\Models\GameCompletion;
use App\Models\GameReset;
use App\Models\GameSessionCompletion;
use App\Models\Prompt;
use App\Models\Story;
use App\Models\User;
use App\Models\UserActivityDay;
use App\Support\Analytics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds isolated analytics fixtures and removes them after each test.
 *
 * All created_at timestamps default to after the analytics baseline unless
 * explicitly overridden (e.g. abandoned-game scenarios).
 */
final class AnalyticsTestContext
{
    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $storyIds = [];

    /** @var list<string> */
    private array $gameIds = [];

    /** @var list<int> */
    private array $promptIds = [];

    /** @var list<int> */
    private array $sessionCompletionIds = [];

    /** @var list<int> */
    private array $completionIds = [];

    /** @var list<int> */
    private array $resetIds = [];

    /** @var list<int> */
    private array $activityDayIds = [];

    /** @var list<int> */
    private array $pageViewIds = [];

    public static function make(): self
    {
        return new self;
    }

    public function createUser(array $attributes = []): User
    {
        $user = User::factory()->withCompleteProfile()->markEmailAsVerified()->create(array_merge([
            'created_at' => $this->afterBaseline(),
            'updated_at' => $this->afterBaseline(),
        ], $attributes));

        $this->userIds[] = $user->id;

        return $user;
    }

    public function createStory(array $attributes = []): Story
    {
        $story = Story::factory()->create(array_merge([
            'created_at' => $this->afterBaseline(),
            'updated_at' => $this->afterBaseline(),
        ], $attributes));

        $this->storyIds[] = $story->id;

        return $story;
    }

    public function createGame(User $user, Story $story, array $attributes = []): Game
    {
        // forceCreate bypasses $guarded so explicitly-provided created_at / updated_at
        // are persisted as-is — essential for backdating abandoned-game fixtures.
        $game = Game::forceCreate(array_merge([
            'story_id'                     => $story->id,
            'user_id'                      => $user->id,
            'current_session_number'       => 1,
            'current_story_cycle_number'   => 1,
            'model'                        => 'claude-haiku-4-5',
            'is_preview'                   => false,
            'current_session_complete'     => false,
            'is_climactic_choice'          => false,
            'created_at'                   => $this->afterBaseline(),
            'updated_at'                   => $this->afterBaseline(),
        ], $attributes));

        $this->gameIds[] = $game->id;

        return $game;
    }

    public function startSession(
        Game $game,
        int $sessionNumber = 1,
        int $storyCycleNumber = 1,
        ?Carbon $startedAt = null,
    ): GameSessionCompletion {
        $startedAt ??= $this->afterBaseline();

        $row = GameSessionCompletion::query()->create([
            'game_id'            => $game->id,
            'story_id'           => $game->story_id,
            'user_id'            => $game->user_id,
            'story_cycle_number' => $storyCycleNumber,
            'session_number'     => $sessionNumber,
            'started_at'         => $startedAt,
            'completed_at'       => null,
            'created_at'         => $startedAt,
            'updated_at'         => $startedAt,
        ]);

        $this->sessionCompletionIds[] = $row->id;

        return $row;
    }

    public function completeSession(
        GameSessionCompletion $session,
        ?Carbon $completedAt = null,
    ): GameSessionCompletion {
        $completedAt ??= $session->started_at?->copy()->addMinutes(15) ?? $this->afterBaseline();

        $session->update([
            'completed_at' => $completedAt,
            'updated_at'   => $completedAt,
        ]);

        return $session->fresh();
    }

    public function recordCompletion(
        Game $game,
        int $storyCycleNumber = 1,
        ?Carbon $completedAt = null,
    ): GameCompletion {
        $completedAt ??= $this->afterBaseline()->copy()->addHour();

        $game->update(['completed_at' => $completedAt]);

        $row = GameCompletion::query()->updateOrCreate(
            [
                'game_id'            => $game->id,
                'story_cycle_number' => $storyCycleNumber,
            ],
            [
                'user_id'      => $game->user_id,
                'story_id'     => $game->story_id,
                'completed_at' => $completedAt,
            ],
        );

        if (! in_array($row->id, $this->completionIds, true)) {
            $this->completionIds[] = $row->id;
        }

        return $row;
    }

    public function recordReplay(Game $game, ?Carbon $resetAt = null): GameReset
    {
        $resetAt ??= $this->afterBaseline()->copy()->addHours(2);

        $row = GameReset::query()->create([
            'game_id'              => $game->id,
            'user_id'              => $game->user_id,
            'story_id'             => $game->story_id,
            'had_prior_completion' => $game->completed_at !== null,
            'created_at'           => $resetAt,
            'updated_at'           => $resetAt,
        ]);

        $this->resetIds[] = $row->id;

        return $row;
    }

    public function recordPrompt(Game $game, ?Carbon $createdAt = null): Prompt
    {
        $createdAt ??= $this->afterBaseline();

        $row = Prompt::query()->create([
            'game_id'        => $game->id,
            'session_number' => $game->current_session_number ?? 1,
            'response'       => 'Test narration.',
            'choices'        => [],
            'created_at'     => $createdAt,
            'updated_at'     => $createdAt,
        ]);

        $this->promptIds[] = $row->id;

        return $row;
    }

    public function recordActivityDay(User $user, Carbon $date): void
    {
        $row = UserActivityDay::query()->create([
            'user_id'       => $user->id,
            'activity_date' => $date->toDateString(),
            'created_at'    => $date,
            'updated_at'    => $date,
        ]);

        $this->activityDayIds[] = $row->id;
    }

    public function recordPageView(?string $sessionId = null, ?Carbon $viewDate = null): void
    {
        $viewDate ??= $this->afterBaseline();

        $id = DB::table('page_views')->insertGetId([
            'user_id'    => null,
            'session_id' => $sessionId ?? 'test-session-' . uniqid(),
            'path'       => '/stories/test',
            'view_date'  => $viewDate->toDateString(),
            'created_at' => $viewDate,
            'updated_at' => $viewDate,
        ]);

        $this->pageViewIds[] = $id;
    }

    public function afterBaseline(): Carbon
    {
        return Analytics::baseline()->copy()->addDays(2);
    }

    /**
     * Remove all tracked fixtures. Child tables first.
     */
    public function cleanup(): void
    {
        if ($this->promptIds !== []) {
            Prompt::query()->whereIn('id', $this->promptIds)->delete();
        }

        if ($this->sessionCompletionIds !== []) {
            GameSessionCompletion::query()->whereIn('id', $this->sessionCompletionIds)->delete();
        }

        if ($this->completionIds !== []) {
            GameCompletion::query()->whereIn('id', $this->completionIds)->delete();
        }

        if ($this->resetIds !== []) {
            GameReset::query()->whereIn('id', $this->resetIds)->delete();
        }

        if ($this->activityDayIds !== []) {
            UserActivityDay::query()->whereIn('id', $this->activityDayIds)->delete();
        }

        if ($this->pageViewIds !== []) {
            DB::table('page_views')->whereIn('id', $this->pageViewIds)->delete();
        }

        if ($this->gameIds !== []) {
            Game::query()->whereIn('id', $this->gameIds)->delete();
        }

        if ($this->storyIds !== []) {
            Story::query()->whereIn('id', $this->storyIds)->delete();
        }

        if ($this->userIds !== []) {
            User::query()->whereIn('id', $this->userIds)->delete();
        }

        $this->resetTracking();
    }

    /**
     * @return array<string, int>
     */
    public function trackedCounts(): array
    {
        return [
            'users'                => count($this->userIds),
            'stories'              => count($this->storyIds),
            'games'                => count($this->gameIds),
            'prompts'              => count($this->promptIds),
            'session_completions'  => count($this->sessionCompletionIds),
            'game_completions'     => count($this->completionIds),
            'game_resets'          => count($this->resetIds),
            'user_activity_days'   => count($this->activityDayIds),
            'page_views'           => count($this->pageViewIds),
        ];
    }

    private function resetTracking(): void
    {
        $this->userIds = [];
        $this->storyIds = [];
        $this->gameIds = [];
        $this->promptIds = [];
        $this->sessionCompletionIds = [];
        $this->completionIds = [];
        $this->resetIds = [];
        $this->activityDayIds = [];
        $this->pageViewIds = [];
    }
}
