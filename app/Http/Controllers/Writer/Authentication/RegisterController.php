<?php

declare(strict_types=1);

namespace App\Http\Controllers\Writer\Authentication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Writer\Authentication\StoreRegisterRequest;
use App\Models\Writer;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

final class RegisterController extends Controller
{
    public function create(): Response
    {
        return inertia('WriterLab/Authentication/Register');
    }

    public function store(StoreRegisterRequest $request): RedirectResponse
    {
        $writer = Writer::create([
            'name'     => $request->string('name')->toString(),
            'email'    => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        auth('writer')->login($writer, remember: true);

        return to_route('writer.writer-lab.index')
            ->with('success', 'Welcome to Writer Lab.');
    }
}
