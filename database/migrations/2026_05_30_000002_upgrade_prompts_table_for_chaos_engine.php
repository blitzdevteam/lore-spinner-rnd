<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chaos Engine upgrade — replaces the event-scoped `event_id` FK on the
 * `prompts` table with a lightweight `session_number` column.
 *
 * In Story Guard, each prompt row tracked which DB event was being narrated.
 * The Chaos engine does not advance per-event; it narrates freely across all
 * events of a session arc. `session_number` records which session arc a turn
 * belongs to, replacing event-level granularity.
 *
 * Dropped: event_id (FK to events, NOT NULL)
 * Added:   session_number (nullable small integer — which story session arc)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table): void {
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
            $table->unsignedSmallInteger('session_number')->nullable()->after('game_id');
        });
    }

    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table): void {
            $table->dropColumn('session_number');
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnUpdate();
        });
    }
};
