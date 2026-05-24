<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pipeline Upgrade V2 — session_adaptations columns.
 *
 * Adds the cached, pre-assembled Runtime Narrator Template (Deliverable 8)
 * for each session. The RuntimeNarratorAssemblyJob runs after Phase 8 and
 * fills the 17 sections from prior phase outputs. Chaos Mode reads this
 * column at session start; runtime only injects Tier 1/2/3 state + symbolic
 * memory + arc continuity on top.
 *
 *   - `runtime_narrator_prompt`        : the assembled prompt body, bounded
 *                                        to 65,000 characters per Deliverable 8.
 *   - `runtime_narrator_assembled_at`  : timestamp; used by validation runbook
 *                                        + by the controller to detect drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_adaptations', function (Blueprint $table): void {
            if (! Schema::hasColumn('session_adaptations', 'runtime_narrator_prompt')) {
                $table->longText('runtime_narrator_prompt')->nullable()->after('editorial_verification');
            }
            if (! Schema::hasColumn('session_adaptations', 'runtime_narrator_assembled_at')) {
                $table->timestamp('runtime_narrator_assembled_at')->nullable()->after('runtime_narrator_prompt');
            }
        });
    }

    public function down(): void
    {
        Schema::table('session_adaptations', function (Blueprint $table): void {
            if (Schema::hasColumn('session_adaptations', 'runtime_narrator_assembled_at')) {
                $table->dropColumn('runtime_narrator_assembled_at');
            }
            if (Schema::hasColumn('session_adaptations', 'runtime_narrator_prompt')) {
                $table->dropColumn('runtime_narrator_prompt');
            }
        });
    }
};
