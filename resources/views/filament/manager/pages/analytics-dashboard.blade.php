<x-filament-panels::page>
    <div class="space-y-6">
        @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsKpiWidget::class)
        @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsFunnelWidget::class)
        @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsRetentionWidget::class)
    </div>
</x-filament-panels::page>
