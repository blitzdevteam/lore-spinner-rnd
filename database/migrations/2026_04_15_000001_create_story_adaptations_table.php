<?php

declare(strict_types=1);

use App\Enums\Adaptation\AdaptationStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_adaptations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('adaptation_status')->default(AdaptationStatusEnum::PENDING->value);
            $table->longText('format_detection')->nullable();
            $table->longText('ip_audit')->nullable();
            $table->longText('story_session_map')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_adaptations');
    }
};
