<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Allows a logged-in Manager to impersonate a Writer account.
 *
 * The manager session is left intact (manager guard stays logged in).
 * The writer guard is seeded for the current session, so visiting
 * /writer/writer-lab works immediately without a second login.
 */
final class LoginAsWriterController extends Controller
{
    public function __invoke(Request $request, Writer $writer): RedirectResponse
    {
        // Only allow authenticated managers to use this endpoint.
        // The Filament manager panel handles this via its own middleware,
        // but we double-check here in case the route is called directly.
        if (! auth('manager')->check()) {
            abort(403, 'Manager authentication required.');
        }

        auth('writer')->login($writer);

        session()->put('impersonating_writer_id', $writer->id);
        session()->put('impersonated_by_manager_id', auth('manager')->id());

        return redirect()->route('writer.writer-lab.index')
            ->with('status', "Logged in as writer: {$writer->name}");
    }
}
