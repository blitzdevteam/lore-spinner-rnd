<?php

declare(strict_types=1);

namespace App\Http\Controllers\Writer\Authentication;

use App\Actions\Authentication\LogoutAuthenticatableGuardAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LogoutController
{
    public function destroy(
        Request $request,
        LogoutAuthenticatableGuardAction $logoutAuthenticatableGuardAction,
    ): RedirectResponse {
        $logoutAuthenticatableGuardAction->handle('writer', $request);

        return to_route('writer.authentication.login.create')
            ->with('success', 'You have been logged out.');
    }
}
