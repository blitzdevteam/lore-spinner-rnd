<?php

declare(strict_types=1);

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

require __DIR__.'/routes/user.php';
require __DIR__.'/routes/writer.php';

// Manager — login as writer (accessible only while manager is authenticated)
Route::middleware(['auth:manager'])
    ->get('manager/writers/{writer}/login-as', App\Http\Controllers\Manager\LoginAsWriterController::class)
    ->name('manager.login-as-writer');

Route::get('/', Controllers\IndexController::class)->name('index');

Route::get('design-rnd', fn () => inertia('DesignRnd'))->name('design-rnd');

Route::resource('creators', Controllers\CreatorController::class)
    ->scoped([
        'creator' => 'username',
    ])
    ->only(['index', 'show']);

Route::resource('stories', Controllers\StoryController::class)
    ->scoped([
        'story' => 'slug',
    ])
    ->only(['index', 'show']);

Route::post('feedback', [Controllers\FeedbackController::class, 'store'])->name('feedback.store');

Route::get('expansion-status', function () {
    $symlink = public_path('storage');
    $symlinkExists = file_exists($symlink);
    $symlinkTarget = $symlinkExists ? (is_link($symlink) ? readlink($symlink) : 'NOT A SYMLINK') : 'MISSING';
    $storageDir = storage_path('app/public');
    $storageDirExists = is_dir($storageDir);
    $storageFiles = $storageDirExists ? count(glob($storageDir.'/*')) : 0;

    $stories = App\Models\Story::select('id', 'title', 'status', 'creator_id')
        ->with(['creator:id,first_name,last_name', 'media'])
        ->orderBy('id')
        ->get()
        ->map(function ($s) {
            $cover = $s->getFirstMedia('cover');
            $coverUrl = $s->getFirstMediaUrl('cover');
            $coverPath = $cover?->getPath();
            $fileOnDisk = $coverPath ? file_exists($coverPath) : false;

            return [
                'title' => $s->title,
                'status' => $s->status->value,
                'creator' => $s->creator?->first_name.' '.$s->creator?->last_name,
                'has_cover_record' => $cover !== null,
                'cover_url' => $coverUrl ?: null,
                'cover_path' => $coverPath,
                'file_on_disk' => $fileOnDisk,
            ];
        });

    $creators = App\Models\Creator::select('id', 'first_name', 'last_name', 'email')
        ->with('media')
        ->get()
        ->map(function ($c) {
            $avatar = $c->getFirstMedia('avatar');
            $avatarPath = $avatar?->getPath();

            return [
                'name' => $c->first_name.' '.$c->last_name,
                'email' => $c->email,
                'has_avatar_record' => $avatar !== null,
                'avatar_url' => $c->avatar ?: null,
                'file_on_disk' => $avatarPath ? file_exists($avatarPath) : false,
            ];
        });

    $chapters = App\Models\Chapter::select('id', 'story_id', 'position', 'title', 'teaser')
        ->with(['story:id,title', 'media'])
        ->whereHas('story', fn ($q) => $q->whereIn('title', [
            "Hemingway's War", 'High Stakes', 'Pieces of Eight', 'Time Machine',
            'B.U.G.S.', 'Dream Police', 'Necropolis', "PJ's", 'Wasteland',
        ]))
        ->orderBy('story_id')
        ->orderBy('position')
        ->get()
        ->map(function ($ch) {
            $cover = $ch->getFirstMedia('cover');

            return [
                'id' => $ch->id,
                'story' => $ch->story?->title,
                'position' => $ch->position,
                'title' => $ch->title,
                'teaser' => $ch->teaser,
                'has_cover' => $cover !== null,
            ];
        });

    return response()->json([
        'symlink' => ['exists' => $symlinkExists, 'target' => $symlinkTarget],
        'storage_dir' => ['exists' => $storageDirExists, 'file_count' => $storageFiles],
        'creators' => $creators,
        'stories' => $stories,
        'chapters' => $chapters,
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
});
