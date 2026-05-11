<?php

declare(strict_types=1);

namespace App\Http\Controllers\Writer\Authentication;

use App\Actions\Authentication\LoginAuthenticatableGuardAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Writer\Authentication\StoreLoginRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

final class LoginController extends Controller
{
    public function create(): Response
    {
        return inertia('WriterLab/Authentication/Login');
    }

    public function store(
        StoreLoginRequest $request,
        LoginAuthenticatableGuardAction $loginAuthenticatableGuard,
    ): RedirectResponse {
        $result = $loginAuthenticatableGuard->handle(
            'writer',
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        if ($result === false) {
            return back()
                ->with('error', 'Credentials do not match our records.')
                ->onlyInput('email');
        }

        return redirect()->intended(route('writer.writer-lab.index'))
            ->with('success', 'Welcome back to Writer Lab.');
    }
}
