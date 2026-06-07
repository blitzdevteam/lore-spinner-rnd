<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable completion history — one row per completed story cycle.
 *
 * games.completed_at is current/mutable state (latest completion timestamp).
 * game_completions is the append-only analytics source of truth.
 *
 * Why a separate table?
 *   games.completed_at is overwritten on each new completion (replay). If a
 *   player completes a story three times, games.completed_at only holds the
 *   most recent timestamp. game_completions holds all three, each associated
 *   with its story_cycle_number.
 *
 * Written by: GameController@nextSession() — in the branch where
 *   nextSessionNumber > totalSessions, after setting games.completed_at.
 *
 * Upserted on (game_id, story_cycle_number) to be idempotent against retries.
 *
 * Analytics use:
 *   Story Completions = COUNT(*) WHERE completed_at >= '2026-06-01'
 *   Avg Completion Time = AVG(gc.completed_at - s1.started_at)
 *     joined via (game_id, story_cycle_number, session_number = 1)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->unsignedSmallInteger('story_cycle_number')->default(1);
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['game_id', 'story_cycle_number']);
            $table->index(['story_id', 'completed_at']);
            $table->index(['user_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_completions');
    }
};
