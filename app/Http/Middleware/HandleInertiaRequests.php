<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;
use Override;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    #[Override]
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'error' => $request->session()->get('error') ? Arr::wrap($request->session()->get('error')) : [],
                'success' => $request->session()->get('success') ? Arr::wrap($request->session()->get('success')) : [],
                'warning' => $request->session()->get('warning') ? Arr::wrap($request->session()->get('warning')) : [],
                'story_complete' => (bool) $request->session()->get('story_complete', false),
            ],
            'auth' => function () use ($request) {
                $auth = null;

                if ($request->user('user')) {
                    $auth = $request
                        ->user('user')
                        ->only([
                            'id',
                            'first_name',
                            'last_name',
                            'full_name',
                            'gender',
                            'username',
                            'email',
                            'avatar',
                            'bio',
                        ]);

                    /**
                     * We can load auth relations using `$auth->load(...)` here whenever we want
                     */
                }

                return $auth;
            },
            // Lightweight writer-auth flag for nav — avoids full model query on every page.
            'writerLoggedIn' => fn () => (bool) $request->user('writer'),
        ];
    }
}
