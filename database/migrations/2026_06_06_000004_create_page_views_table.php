<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (Laravel session, calendar day) on public marketing surfaces.
 *
 * Written by RecordPageView middleware on non-Inertia GET requests to:
 *   / · /stories/* · /creators/*
 *
 * The unique constraint on (session_id, view_date) means only the first
 * full-page load per session per day is recorded — giving unique visitors/day.
 *
 * Enables: daily unique visitor counts (Visits metric).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id', 40);
            $table->string('path', 512);
            $table->date('view_date');
            $table->timestamps();

            $table->unique(['session_id', 'view_date']);
            $table->index('view_date');
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
