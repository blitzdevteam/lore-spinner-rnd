<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->unsignedInteger('current_session_number')->nullable()->after('current_event_id');
            $table->string('current_beat_type')->nullable()->after('current_session_number');
            $table->longText('branching_choices_taken')->nullable()->after('current_beat_type');
            $table->longText('tracked_dimensions')->nullable()->after('branching_choices_taken');
            $table->longText('branch_resolution_log')->nullable()->after('tracked_dimensions');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn([
                'current_session_number',
                'current_beat_type',
                'branching_choices_taken',
                'tracked_dimensions',
                'branch_resolution_log',
            ]);
        });
    }
};
