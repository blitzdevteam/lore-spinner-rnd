<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_lab_prompts', function (Blueprint $table): void {
            $table->ulid('id')->primary()->index();
            $table->foreignUlid('session_id')
                ->constrained('voice_lab_sessions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('text');
            $table->json('choices')->nullable();
            $table->unsignedInteger('audio_ms')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_lab_prompts');
    }
};
