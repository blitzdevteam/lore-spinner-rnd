<?php

declare(strict_types=1);

use App\VoiceLab\Http\Controllers\ClearHistoryController;
use App\VoiceLab\Http\Controllers\IndexController;
use App\VoiceLab\Http\Controllers\RespondController;
use App\VoiceLab\Http\Controllers\TranscribeController;
use Illuminate\Support\Facades\Route;

Route::prefix('voice-lab')->name('voice-lab.')->group(function (): void {
    Route::get('/', IndexController::class)->name('index');
    Route::post('respond', RespondController::class)->name('respond');
    Route::post('transcribe', TranscribeController::class)->name('transcribe');
    Route::delete('history', ClearHistoryController::class)->name('clear-history');
});
