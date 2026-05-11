<?php

declare(strict_types=1);

namespace App\Actions\Authentication;

use App\Models\Creator;
use App\Models\User;
use App\Models\Writer;
use InvalidArgumentException;

final readonly class LoginAuthenticatableGuardAction
{
    private const array GUARD_MODELS = [
        'user' => User::class,
        'creator' => Creator::class,
        'writer' => Writer::class,
    ];

    public function handle(string $guard, string $email, string $password): User|Creator|Writer|false
    {
        if (! array_key_exists($guard, self::GUARD_MODELS)) {
            throw new InvalidArgumentException(sprintf('Guard `%s` is an invalid guard.', $guard));
        }

        $check = auth($guard)->attempt([
            'email' => $email,
            'password' => $password,
        ], remember: true);

        if ($check) {
            /** @var User|Creator|Writer $user */
            $user = auth($guard)->user();

            return $user;
        }

        return false;
    }
}
