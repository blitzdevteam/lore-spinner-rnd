<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (user, calendar day) on which the user performed any game action.
 *
 * Written idempotently (updateOrInsert) from game interaction points:
 *   - GameController@begin()
 *   - GameController@nextSession()
 *   - GameController@reset()
 *   - PromptController@store()
 *
 * Enables: DAU · WAU · MAU · D1/D7/D30 retention · Return Rate · Returns count.
 * Low volume: max one row per user per day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('activity_date');
            $table->timestamps();

            $table->unique(['user_id', 'activity_date']);
            $table->index('activity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_days');
    }
};
