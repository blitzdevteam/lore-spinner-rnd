<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight collaboration notes for the Writer Lab.
 *
 * Scoped to a chapter so editors leave context-aware comments for each other.
 * Optional event_id pins a note to a specific event card. Optional author_name
 * is captured at post time so the display works even if the writer account is
 * later removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writer_lab_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('writer_id')->nullable()->constrained('writers')->nullOnDelete();
            $table->string('author_name', 80);
            $table->text('body');
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();

            $table->index(['chapter_id', 'is_resolved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writer_lab_notes');
    }
};
