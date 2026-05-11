<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writer_lab_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('session_number');
            $table->unsignedInteger('version_number');

            // Full snapshots of the affected rows before this version's activate ran.
            // Used for restore: re-upsert event rows + re-apply adaptation snapshot.
            $table->json('snapshot_events');
            $table->json('snapshot_adaptation')->nullable();

            $table->boolean('is_active')->default(false);
            $table->string('note')->nullable();

            // Snapshots are immutable — no updated_at needed.
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['story_id', 'session_number', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writer_lab_versions');
    }
};
