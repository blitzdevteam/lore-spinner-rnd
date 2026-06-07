<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds completed_at to the games table.
 *
 * This becomes the single source of truth for Story Completions.
 * Set in GameController@nextSession() when nextSessionNumber > totalSessions.
 * Not cleared on reset — preserved as a historical record of most recent completion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->after('current_session_complete');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn('completed_at');
        });
    }
};
