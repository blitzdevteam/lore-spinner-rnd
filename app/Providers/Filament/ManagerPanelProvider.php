<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Manager\Resources\Feedback\FeedbackResource;
use App\Filament\Manager\Resources\Feedback\Pages\ListFeedbacks;
use App\Filament\Manager\Resources\Feedback\Pages\ViewFeedback;
use App\Filament\Manager\Widgets;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

final class ManagerPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // Register Livewire components directly — bypasses Filament's component
        // cache which would otherwise skip these pages if the cache predates them.
        Livewire::component(
            'app.filament.manager.resources.feedback.pages.list-feedbacks',
            ListFeedbacks::class,
        );
        Livewire::component(
            'app.filament.manager.resources.feedback.pages.view-feedback',
            ViewFeedback::class,
        );

        // Register the nav item on every manager request. FeedbackResource uses
        // $isDiscovered = false so it never enters getResources(), meaning
        // mountNavigation() would skip it. This serving() callback adds it back.
        Filament::serving(function (): void {
            if (Filament::getCurrentPanel()?->getId() !== 'manager') {
                return;
            }

            FeedbackResource::registerNavigationItems();
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->brandName('Lore Spinner')
            ->id('manager')
            ->path('manager')
            ->login()
            ->loginRouteSlug('authentication/login')
            ->colors([
                'primary' => Color::Purple,
            ])
            ->topbar(false)
            ->discoverResources(in: app_path('Filament/Manager/Resources'), for: 'App\Filament\Manager\Resources')
            // authenticatedRoutes() is NOT stored in the Filament component cache,
            // so this closure always runs regardless of cache state — this is the
            // reliable path to get /manager/feedback routes registered.
            ->authenticatedRoutes(function (Panel $panel): void {
                FeedbackResource::registerRoutes($panel);
            })
            ->discoverPages(in: app_path('Filament/Manager/Pages'), for: 'App\Filament\Manager\Pages')
            ->discoverWidgets(in: app_path('Filament/Manager/Widgets'), for: 'App\Filament\Manager\Widgets')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                Widgets\PlatformStatsOverview::class,
                Widgets\SignupChart::class,
                Widgets\EngagementStats::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('manager');
    }
}
