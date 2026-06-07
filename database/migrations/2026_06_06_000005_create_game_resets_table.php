<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable event log — one row per player-initiated story reset.
 *
 * Recorded in GameController@reset() before game state is wiped.
 * had_prior_completion = true  means the player had completed the story
 * before resetting (games.completed_at was not null). This is the
 * canonical source of truth for the Replays metric:
 *
 *   Replays = game_resets WHERE had_prior_completion = true
 *
 * Analytics history must never live inside mutable game state —
 * this table is never updated, only appended to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_resets', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->boolean('had_prior_completion')->default(false);
            $table->timestamps();

            $table->index(['story_id', 'created_at']);
            $table->index(['user_id', 'had_prior_completion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_resets');
    }
};
