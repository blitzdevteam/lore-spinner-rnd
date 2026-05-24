<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pipeline Upgrade V2 — story_adaptations columns.
 *
 * Adds storage for the two new pre-Phase-1 jobs that every IP must run through
 * before the existing session pipeline starts:
 *
 *   - `ip_trimming`  : Deliverable 7 output (story spine, world rules,
 *                      content triage, conversion notes, trimmed source).
 *   - `voice_profile`: Deliverable 1 output (voice DNA, master rule 1 hard
 *                      bans, 14-point audit protocol).
 *
 * Both are story-wide and idempotent within a single pipeline run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('story_adaptations', function (Blueprint $table): void {
            if (! Schema::hasColumn('story_adaptations', 'ip_trimming')) {
                $table->longText('ip_trimming')->nullable()->after('format_detection');
            }
            if (! Schema::hasColumn('story_adaptations', 'voice_profile')) {
                $table->longText('voice_profile')->nullable()->after('ip_audit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('story_adaptations', function (Blueprint $table): void {
            if (Schema::hasColumn('story_adaptations', 'voice_profile')) {
                $table->dropColumn('voice_profile');
            }
            if (Schema::hasColumn('story_adaptations', 'ip_trimming')) {
                $table->dropColumn('ip_trimming');
            }
        });
    }
};
