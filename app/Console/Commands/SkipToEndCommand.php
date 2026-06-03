<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Story;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Fast-forward a user's game to the last N sessions so the outro can be tested
 * without having to play through the entire story.
 *
 * The command:
 *  1. Finds the game for the given user + story.
 *  2. Determines the total number of adapted sessions.
 *  3. Deletes all prompts beyond session (total - N + 1) so only the last N
 *     sessions' worth of prompts remain — or clears everything if the game has
 *     not started yet.
 *  4. Sets current_session_number to (total - N + 1) and marks the session as
 *     incomplete so the player can continue from there.
 *
 * Usage:
 *   php artisan game:skip-to-end --user=1 --story=the-adventure-of-the-speckled-band
 *   php artisan game:skip-to-end --user=1 --story=the-wonderful-wizard-of-oz --remaining=1
 */
final class SkipToEndCommand extends Command
{
    protected $signature = 'game:skip-to-end
                            {--user=    : User ID}
                            {--story=   : Story slug or ID}
                            {--remaining=3 : How many sessions to leave remaining (default 3)}
                            {--force|y  : Skip the confirmation prompt}';

    protected $description = 'Fast-forward a game to the last N sessions for outro testing';

    public function handle(): int
    {
        $user  = $this->resolveUser();
        $story = $this->resolveStory();

        if (! $user || ! $story) {
            return self::FAILURE;
        }

        $game = Game::where('user_id', $user->id)
            ->where('story_id', $story->id)
            ->first();

        if (! $game) {
            $this->error("No game found for user [{$user->id}] and story [{$story->slug}].");
            $this->line('  → Start a game from the frontend first, then run this command.');

            return self::FAILURE;
        }

        // Load total session count from the adaptation
        $story->load('adaptation.sessionAdaptations');
        $totalSessions = (int) ($story->adaptation?->sessionAdaptations?->count() ?? 0);

        if ($totalSessions === 0) {
            $this->error("Story [{$story->slug}] has no adapted sessions. Run the adaptation pipeline first.");

            return self::FAILURE;
        }

        $remaining = max(1, (int) ($this->option('remaining') ?? 3));

        // The session the player should be placed at (first of the last N)
        $targetSession = max(1, $totalSessions - $remaining + 1);

        $this->info("Story:          {$story->title}");
        $this->info("Total sessions: {$totalSessions}");
        $this->info("Target session: {$targetSession}  (leaving {$remaining} session(s) remaining)");
        $this->info("Game ID:        {$game->id}");
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Fast-forward this game? All prompts from session '.$targetSession.' onwards will be deleted.')) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        // Delete prompts from the target session onwards so the engine re-runs them
        $deleted = $game->prompts()
            ->where('session_number', '>=', $targetSession)
            ->delete();

        // Rewind the game state
        $game->update([
            'current_session_number'   => $targetSession,
            'current_session_complete' => false,
        ]);

        $this->info("Deleted {$deleted} prompt(s) from session {$targetSession}+.");
        $this->info("Game rewound to session {$targetSession} / {$totalSessions}.");
        $this->newLine();
        $this->line('→ Visit the game in the browser and play through the remaining sessions to trigger the outro.');

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $value = $this->option('user');

        if (! $value) {
            $this->error('--user is required.');

            return null;
        }

        $user = User::find((int) $value);

        if (! $user) {
            $this->error("User [{$value}] not found.");

            return null;
        }

        return $user;
    }

    private function resolveStory(): ?Story
    {
        $value = $this->option('story');

        if (! $value) {
            $this->error('--story is required.');

            return null;
        }

        $story = is_numeric($value)
            ? Story::find((int) $value)
            : Story::where('slug', $value)->first();

        if (! $story) {
            $this->error("Story [{$value}] not found.");

            return null;
        }

        return $story;
    }
}
