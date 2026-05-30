<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chaos Engine upgrade — replaces Story Guard event-navigation columns on
 * the `games` table with Chaos V2 literary-state columns.
 *
 * Dropped (Story Guard only):
 *   - current_event_id         per-event progress pointer
 *   - current_beat_type        beat-map tracker
 *   - branching_choices_taken  authored-branch audit
 *   - tracked_dimensions       dimension tracker
 *   - branch_resolution_log    turn-level audit log
 *
 * Added (Chaos Engine):
 *   - model                    AI model slug used for this game
 *   - symbolic_memory          literary memory paragraph injected each turn
 *   - alignment_scaffold       {chaotic, lawful, neutral} tally
 *   - defining_choice_id       last climactic choice id
 *   - defining_choice_line     authored defining line for last climactic choice
 *   - is_climactic_choice      triggers Tier 3 world-state on next turn
 *   - current_session_complete signals session boundary to the UI
 *
 * Kept unchanged: id, story_id, user_id, current_session_number, world_state,
 *                 is_preview, created_at, updated_at.
 *
 * Revert: git revert to the snapshot commit made before this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropForeign(['current_event_id']);
            $table->dropColumn([
                'current_event_id',
                'current_beat_type',
                'branching_choices_taken',
                'tracked_dimensions',
                'branch_resolution_log',
            ]);

            $table->string('model', 64)->default('claude-haiku-4-5')->after('current_session_number');
            $table->longText('symbolic_memory')->nullable()->after('world_state');
            $table->json('alignment_scaffold')->nullable()->after('symbolic_memory');
            $table->string('defining_choice_id', 128)->nullable()->after('alignment_scaffold');
            $table->text('defining_choice_line')->nullable()->after('defining_choice_id');
            $table->boolean('is_climactic_choice')->default(false)->after('defining_choice_line');
            $table->boolean('current_session_complete')->default(false)->after('is_climactic_choice');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn([
                'model',
                'symbolic_memory',
                'alignment_scaffold',
                'defining_choice_id',
                'defining_choice_line',
                'is_climactic_choice',
                'current_session_complete',
            ]);

            $table->foreignId('current_event_id')->nullable()->constrained('events')->cascadeOnUpdate();
            $table->string('current_beat_type')->nullable();
            $table->longText('branching_choices_taken')->nullable();
            $table->longText('tracked_dimensions')->nullable();
            $table->longText('branch_resolution_log')->nullable();
        });
    }
};
