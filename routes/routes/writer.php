<?php

declare(strict_types=1);

use App\Http\Controllers\Writer;
use Illuminate\Support\Facades\Route;

Route::prefix('writer')->name('writer.')->group(function (): void {

    // ── Authentication (guest only) ───────────────────────────────────────────
    Route::middleware('guest:writer')->prefix('authentication')->name('authentication.')->group(function (): void {
        Route::prefix('register')->name('register.')->controller(Writer\Authentication\RegisterController::class)->group(function (): void {
            Route::get('/', 'create')->name('create');
            Route::post('/', 'store')->name('store');
        });

        Route::prefix('login')->name('login.')->controller(Writer\Authentication\LoginController::class)->group(function (): void {
            Route::get('/', 'create')->name('create');
            Route::post('/', 'store')->name('store');
        });
    });

    // ── Authenticated writer routes ───────────────────────────────────────────
    Route::middleware('auth:writer')->group(function (): void {
        Route::delete('authentication/logout', [Writer\Authentication\LogoutController::class, 'destroy'])
            ->name('authentication.logout');

        // Writer Lab inspector
        Route::get('writer-lab', [Writer\WriterLab\WriterLabController::class, 'index'])->name('writer-lab.index');
        Route::get('writer-lab/{story}', [Writer\WriterLab\WriterLabController::class, 'show'])->name('writer-lab.show');
        Route::get('writer-lab/{story}/chapters/{chapter}', [Writer\WriterLab\WriterLabController::class, 'chapter'])->name('writer-lab.chapter');

        // Draft operations
        Route::prefix('writer-lab/{story}/chapters/{chapter}/drafts')->name('writer-lab.drafts.')->group(function (): void {
            Route::post('combine', [Writer\WriterLab\DraftController::class, 'combine'])->name('combine');
            Route::post('split', [Writer\WriterLab\DraftController::class, 'split'])->name('split');
            Route::post('reorder', [Writer\WriterLab\DraftController::class, 'reorder'])->name('reorder');
            // Direct inline edit (single event content or adaptation data) — returns JSON for AJAX
            Route::post('edit', [Writer\WriterLab\DraftController::class, 'createEdit'])->name('edit');
            Route::post('adaptation', [Writer\WriterLab\DraftController::class, 'createAdaptationEdit'])->name('adaptation');

            Route::get('{draft}', [Writer\WriterLab\DraftController::class, 'show'])->name('show');
            Route::patch('{draft}', [Writer\WriterLab\DraftController::class, 'update'])->name('update');
            Route::post('{draft}/approve', [Writer\WriterLab\DraftController::class, 'approve'])->name('approve');
            Route::post('{draft}/activate', [Writer\WriterLab\DraftController::class, 'activate'])->name('activate');
            Route::post('{draft}/preview', [Writer\WriterLab\DraftController::class, 'preview'])->name('preview');
            // AI choice alignment — returns JSON suggestion for inline diff in Chapter.vue
            Route::post('{draft}/suggest-choices', [Writer\WriterLab\DraftController::class, 'suggestChoices'])->name('suggest-choices');
            // Comprehensive script-change impact analysis across all adaptation layers
            Route::post('{draft}/analyse-impact', [Writer\WriterLab\DraftController::class, 'analyseImpact'])->name('analyse-impact');
        });

        // Version history
        Route::get('writer-lab/{story}/versions', [Writer\WriterLab\VersionController::class, 'index'])->name('writer-lab.versions.index');
        Route::post('writer-lab/{story}/versions/{version}/restore', [Writer\WriterLab\VersionController::class, 'restore'])->name('writer-lab.versions.restore');
    });
});
