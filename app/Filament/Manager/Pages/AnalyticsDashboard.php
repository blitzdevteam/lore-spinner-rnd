<?php

declare(strict_types=1);

namespace App\Filament\Manager\Pages;

use App\Filament\Manager\Widgets\Analytics\AnalyticsFunnelWidget;
use App\Filament\Manager\Widgets\Analytics\AnalyticsKpiWidget;
use App\Filament\Manager\Widgets\Analytics\AnalyticsRetentionWidget;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

final class AnalyticsDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Analytics Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.manager.pages.analytics-dashboard';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            AnalyticsKpiWidget::class,
            AnalyticsFunnelWidget::class,
            AnalyticsRetentionWidget::class,
        ];
    }
}
