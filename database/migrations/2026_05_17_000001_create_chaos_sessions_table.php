<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chaos Mode session log.
 *
 * Records each Chaos Mode playthrough. Auth-optional — chaos mode is an
 * open experimental runtime, so user_id is nullable. The AI controls
 * narration and movement inside a session; runtime owns:
 *   - which session is loaded (`story_session_number`)
 *   - persistent state across turns (`world_state`, `session_memory`)
 *   - conversation log (`conversation_history`)
 *   - session boundary (`session_complete`)
 *
 * Fully revertible — drop this table to remove all chaos mode persistence.
 * No foreign keys depend on it from other tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chaos_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('story_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('story_session_number')->default(1);
            $table->string('model', 64);
            $table->json('conversation_history')->nullable();
            $table->json('world_state')->nullable();
            $table->text('session_memory')->nullable();
            $table->boolean('session_complete')->default(false);
            $table->unsignedInteger('turn_count')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['story_id', 'story_session_number']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chaos_sessions');
    }
};
