<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_lab_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary()->index();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('story_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_lab_sessions');
    }
};
