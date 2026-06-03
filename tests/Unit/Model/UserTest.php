<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('exposes avatar as a same-origin storage path', function () {
    expect($this->user->avatar)->toStartWith('/storage/');
});

it('serializes with expected keys', function () {
    expect(array_keys($this->user->fresh()->toArray()))->toEqualCanonicalizing([
        'id',
        'first_name',
        'last_name',
        'full_name',
        'gender',
        'username',
        'email',
        'avatar',
        'bio',
        'is_profile_completed',
        'media',
    ]);
});
