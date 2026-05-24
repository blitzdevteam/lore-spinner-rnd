<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pipeline Upgrade V2 — chaos_sessions runtime state columns.
 *
 * Per Daniel's correction (2026-05-24): this is an IN-PLACE upgrade. The
 * existing `world_state` JSON column is reused for the new literary-memory
 * shape — no `world_state_v2` shadow column. Sessions for stories that have
 * not yet been re-adapted under V2 are simply unplayable until the pipeline
 * is re-run for that story.
 *
 *   - `alignment_scaffold`    : hidden internal counter — { chaotic, lawful,
 *                               neutral } ints. Never injected into the
 *                               narrator prompt; story-native labels (from
 *                               Phase 2 Task 9) are derived from this.
 *   - `symbolic_memory`       : natural-language paragraph that the narrator
 *                               reads as Section 8 of the runtime template.
 *   - `defining_choice_id`    : when this turn resolved a branching choice,
 *                               its choice_id is captured here. Persisted
 *                               for the future Social Echo share card.
 *   - `defining_choice_line`  : the matching defining line authored in
 *                               Phase 5 Task 8 for the chosen path.
 *   - `is_climactic_choice`   : flag the narrator can raise when a turn
 *                               carried the highest moral weight of the
 *                               session. Used by the tiered state loader.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chaos_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('chaos_sessions', 'alignment_scaffold')) {
                $table->json('alignment_scaffold')->nullable()->after('world_state');
            }
            if (! Schema::hasColumn('chaos_sessions', 'symbolic_memory')) {
                $table->longText('symbolic_memory')->nullable()->after('session_memory');
            }
            if (! Schema::hasColumn('chaos_sessions', 'defining_choice_id')) {
                $table->string('defining_choice_id')->nullable()->after('symbolic_memory');
            }
            if (! Schema::hasColumn('chaos_sessions', 'defining_choice_line')) {
                $table->text('defining_choice_line')->nullable()->after('defining_choice_id');
            }
            if (! Schema::hasColumn('chaos_sessions', 'is_climactic_choice')) {
                $table->boolean('is_climactic_choice')->default(false)->after('defining_choice_line');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chaos_sessions', function (Blueprint $table): void {
            foreach ([
                'is_climactic_choice',
                'defining_choice_line',
                'defining_choice_id',
                'symbolic_memory',
                'alignment_scaffold',
            ] as $column) {
                if (Schema::hasColumn('chaos_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
