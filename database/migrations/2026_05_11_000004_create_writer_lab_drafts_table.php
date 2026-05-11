<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writer_lab_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('session_number')->nullable();

            // The type of editorial operation this draft represents.
            // combine: merges multiple events into one compact rewrite
            // split:   divides one event into multiple runtime moments
            // reorder: changes position order of events within a chapter
            // edit:    rewrites a single event's content
            $table->string('type')->default('combine');

            // combine + split + edit: the original event IDs being operated on
            $table->json('source_event_ids')->nullable();

            // combine + edit: the AI-generated or writer-authored rewrite
            $table->longText('rewritten_content')->nullable();

            // combine: derived metadata from combiner output
            $table->text('derived_objectives')->nullable();
            $table->json('derived_attributes')->nullable();
            $table->string('beat_type')->nullable();
            $table->boolean('requires_choice')->default(true);

            // combine: facts that must survive the rewrite (safety net)
            $table->json('canonical_anchors')->nullable();

            // split: [{content, objectives, beat_type, requires_choice}, ...]
            $table->json('split_parts')->nullable();

            // reorder: [{event_id, new_position}, ...]
            $table->json('event_order')->nullable();

            // snapshot of original event rows before any activate — enables restore
            $table->json('previous_state')->nullable();

            // changes to apply to session_adaptations on activate (cold_open, choices, etc.)
            $table->json('adaptation_patch')->nullable();

            $table->string('status')->default('draft'); // draft|ai_written|writer_approved|activated
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writer_lab_drafts');
    }
};
