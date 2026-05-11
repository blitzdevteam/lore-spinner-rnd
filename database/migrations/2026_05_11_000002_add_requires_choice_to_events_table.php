<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            // Default true preserves all current runtime behavior.
            // Writer Lab sets this false on cinematic flow events that
            // should advance naturally without pausing for player choice.
            $table->boolean('requires_choice')->default(true)->after('session_number');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('requires_choice');
        });
    }
};
