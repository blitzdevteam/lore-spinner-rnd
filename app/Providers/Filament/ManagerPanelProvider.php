<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Manager\Resources\Feedback\FeedbackResource;
use App\Filament\Manager\Resources\Feedback\Pages\ListFeedbacks;
use App\Filament\Manager\Resources\Feedback\Pages\ViewFeedback;
use App\Filament\Manager\Widgets;
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
        // Force-register feedback page Livewire components directly so they are
        // always available regardless of any stale Filament component cache on
        // the server. hasCachedComponents() bypasses the normal discovery when
        // a cache file exists, which would otherwise exclude these new pages.
        Livewire::component(
            'app.filament.manager.resources.feedback.pages.list-feedbacks',
            ListFeedbacks::class,
        );
        Livewire::component(
            'app.filament.manager.resources.feedback.pages.view-feedback',
            ViewFeedback::class,
        );
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
            ->resources([
                FeedbackResource::class,
            ])
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
