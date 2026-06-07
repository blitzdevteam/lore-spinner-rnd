<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks which story cycle a game is currently in.
 *
 * A story cycle is one lifecycle of a game from start (or reset) to completion
 * or next reset. The number increments on every player-initiated reset.
 *
 *   story_cycle_number = 1  →  first story cycle
 *   story_cycle_number = 2  →  after first reset
 *   story_cycle_number = 3  →  after second reset
 *
 * This column drives the unique key on game_session_completions so that session
 * history is preserved across resets rather than overwritten.
 *
 * Set by: GameController@reset() — incremented in the same update() call that
 *         resets game state, ensuring atomicity.
 * Never decremented.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->unsignedSmallInteger('current_story_cycle_number')
                ->default(1)
                ->after('current_session_number');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn('current_story_cycle_number');
        });
    }
};
