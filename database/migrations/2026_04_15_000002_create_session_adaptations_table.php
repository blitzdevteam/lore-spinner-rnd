<?php

declare(strict_types=1);

use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_adaptations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_adaptation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('session_number');
            $table->string('session_status')->default(SessionAdaptationStatusEnum::PENDING->value);
            $table->longText('entry_point_diagnosis')->nullable();
            $table->longText('session_architecture')->nullable();
            $table->longText('session_choice_design')->nullable();
            $table->longText('choice_consequence_map')->nullable();
            $table->longText('session_close_design')->nullable();
            $table->longText('editorial_verification')->nullable();
            $table->timestamps();

            $table->unique(['story_adaptation_id', 'session_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_adaptations');
    }
};
