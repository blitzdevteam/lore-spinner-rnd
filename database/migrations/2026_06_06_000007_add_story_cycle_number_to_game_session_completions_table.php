<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes game_session_completions story-cycle-aware.
 *
 * Previously: UNIQUE(game_id, session_number)
 *   — overwrote session history on every reset, losing timing data for prior
 *     story cycles.
 *
 * After this migration: UNIQUE(game_id, story_cycle_number, session_number)
 *   — each story cycle gets its own set of session rows.
 *   — session timings from every playthrough are preserved forever.
 *   — game_resets remains the event log for the act of resetting.
 *
 * story_cycle_number matches games.current_story_cycle_number at the time
 * each session row is written. It starts at 1 and increments on every reset.
 *
 * All existing rows (if any) are seeded with story_cycle_number = 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_session_completions', function (Blueprint $table): void {
            $table->dropUnique(['game_id', 'session_number']);
            $table->dropIndex(['story_id', 'session_number', 'completed_at']);

            $table->unsignedSmallInteger('story_cycle_number')
                ->default(1)
                ->after('user_id');

            $table->unique(['game_id', 'story_cycle_number', 'session_number']);
            $table->index(['story_id', 'story_cycle_number', 'session_number', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('game_session_completions', function (Blueprint $table): void {
            $table->dropUnique(['game_id', 'story_cycle_number', 'session_number']);
            $table->dropIndex(['story_id', 'story_cycle_number', 'session_number', 'completed_at']);
            $table->dropColumn('story_cycle_number');
            $table->unique(['game_id', 'session_number']);
            $table->index(['story_id', 'session_number', 'completed_at']);
        });
    }
};
