<?php

declare(strict_types=1);

use App\Models\GameSessionCompletion;
use Tests\Support\Analytics\AnalyticsTestContext;
use Tests\Support\Analytics\AnalyticsTestLogger;

beforeEach(function () {
    $this->ctx = AnalyticsTestContext::make();
});

afterEach(function () {
    $this->ctx->cleanup();
});

describe('story cycle session history', function () {
    it('preserves session rows per story cycle instead of overwriting', function () {
        $user  = $this->ctx->createUser();
        $story = $this->ctx->createStory();
        $game  = $this->ctx->createGame($user, $story, [
            'current_story_cycle_number' => 2,
        ]);

        $cycle1 = $this->ctx->startSession($game, sessionNumber: 1, storyCycleNumber: 1);
        $this->ctx->completeSession($cycle1);

        $cycle2 = $this->ctx->startSession($game, sessionNumber: 1, storyCycleNumber: 2);

        $rows = GameSessionCompletion::query()
            ->where('game_id', $game->id)
            ->where('session_number', 1)
            ->orderBy('story_cycle_number')
            ->get();

        expect($rows)->toHaveCount(2);
        expect($rows[0]->story_cycle_number)->toBe(1);
        expect($rows[0]->completed_at)->not->toBeNull();
        expect($rows[1]->story_cycle_number)->toBe(2);
        expect($rows[1]->id)->toBe($cycle2->id);
        expect($rows[1]->completed_at)->toBeNull();

        AnalyticsTestLogger::log('story_cycle_preservation', [
            'game_id' => $game->id,
            'rows'    => $rows->map(fn ($r) => [
                'cycle'      => $r->story_cycle_number,
                'completed'  => $r->completed_at?->toIso8601String(),
            ])->all(),
            'tracked' => $this->ctx->trackedCounts(),
        ]);
    });

    it('records multiple completion events for the same game across cycles', function () {
        $user  = $this->ctx->createUser();
        $story = $this->ctx->createStory();
        $game  = $this->ctx->createGame($user, $story);

        $this->ctx->recordCompletion($game, storyCycleNumber: 1);
        $this->ctx->recordCompletion($game, storyCycleNumber: 2);
        $this->ctx->recordCompletion($game, storyCycleNumber: 3);

        $events = $game->completionHistory()->count();
        $unique = $game->completionHistory()->distinct('game_id')->count('game_id');

        expect($events)->toBe(3);
        expect($unique)->toBe(1);

        AnalyticsTestLogger::log('completion_events_vs_unique', [
            'game_id'            => $game->id,
            'completion_events'  => $events,
            'unique_completed'   => $unique,
            'tracked'            => $this->ctx->trackedCounts(),
        ]);
    });
});
