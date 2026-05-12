<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evolve writer_lab_versions to support full-chapter snapshots.
 *
 *  - chapter_id          : the chapter the snapshot scopes to (nullable for legacy)
 *  - snapshot_kind       : 'session' (legacy) | 'chapter' (new full-chapter form)
 *  - snapshot_adaptations: array of session_adaptations (multi-session capture)
 *
 * Old session-scoped rows keep working — restore reads snapshot_kind to pick the
 * right path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('writer_lab_versions', function (Blueprint $table): void {
            // chapter_id nullable so existing rows survive
            $table->foreignId('chapter_id')->nullable()->after('story_id')
                  ->constrained('chapters')->nullOnDelete();
            $table->string('snapshot_kind', 16)->default('session')->after('chapter_id');
            $table->json('snapshot_adaptations')->nullable()->after('snapshot_adaptation');
            $table->index(['story_id', 'chapter_id']);
        });
    }

    public function down(): void
    {
        Schema::table('writer_lab_versions', function (Blueprint $table): void {
            $table->dropIndex(['story_id', 'chapter_id']);
            $table->dropColumn(['snapshot_adaptations', 'snapshot_kind']);
            $table->dropConstrainedForeignId('chapter_id');
        });
    }
};
