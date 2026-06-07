<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-session lifecycle record for every game.
 *
 * One row per (game, session_number). Upserted — not append-only — so replays
 * Previously overwrote prior story cycle data; now preserved via story_cycle_number (migration 000007).
 * The game_resets table captures the fact of a reset separately.
 *
 * started_at  — set in GameController@begin() for session 1;
 *               set in GameController@nextSession() when advancing to session N.
 * completed_at — set in GameController@nextSession() when session N is confirmed
 *                complete and the player advances to N+1 (or story ends).
 *
 * Enables:
 *   - Per-session completion rates per story
 *   - Avg session duration (completed_at - started_at)
 *   - Session-by-session drop-off funnel per story
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_session_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('session_number');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'session_number']);
            $table->index(['story_id', 'session_number', 'completed_at']);
            $table->index(['user_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_session_completions');
    }
};
