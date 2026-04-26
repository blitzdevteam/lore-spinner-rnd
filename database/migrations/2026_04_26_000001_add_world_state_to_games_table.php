<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a materialized world_state JSON column to games.
 *
 * The column accumulates the cumulative narrative-emergent state across
 * turns: inventory (with nesting), conditions, current sub-location,
 * known facts, character relationships, and flags. branch_resolution_log
 * remains the audit trail; world_state is the runtime view the narrator
 * reads from each turn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->json('world_state')->nullable()->after('branch_resolution_log');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn('world_state');
        });
    }
};
