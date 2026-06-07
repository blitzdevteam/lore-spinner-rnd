<x-filament-panels::page>
    {{-- Baseline notice --}}
    <div style="border-radius:12px; border:1px solid #e9d5ff; background-color:#faf5ff; padding:12px 20px; display:flex; align-items:center; gap:12px; font-size:14px;">
        <span style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:9999px; background-color:#f3e8ff; color:#9333ea; flex-shrink:0;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
                <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
            </svg>
        </span>
        <p style="color:#6b21a8; margin:0;">
            <strong>Data baseline: June 1, 2026.</strong>
            All metrics exclude activity before this date. Hover the stat cards for definitions.
        </p>
    </div>

    <div class="space-y-2">
        {{-- KPI Cards --}}
        @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsKpiWidget::class)

        {{-- Funnel Chart --}}
        <div class="pt-2">
            @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsFunnelWidget::class)
        </div>

        {{-- Retention --}}
        <div class="pt-2">
            @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsRetentionWidget::class)
        </div>
    </div>

    {{-- Glossary --}}
    <x-filament::section>
        <x-slot name="heading">Metric Definitions</x-slot>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:8px 32px; font-size:13px; color:#4b5563;">
            <p><strong style="color:#1f2937;">Visits</strong>: Unique browsing windows on public pages, tracked by an anonymous cookie. Not the same as story chapters.</p>
            <p><strong style="color:#1f2937;">Signups</strong>: New user accounts created since the baseline.</p>
            <p><strong style="color:#1f2937;">Story Starts</strong>: Total individual game plays created (one per play-through). A single user may have multiple.</p>
            <p><strong style="color:#1f2937;">Session 1 Completions</strong>: Users who finished Chapter 1 and advanced to Chapter 2. The first major drop-off point.</p>
            <p><strong style="color:#1f2937;">Story Completions</strong>: Total full-story completions. One per story cycle per game. Replays count separately. Rate = unique completed / starts.</p>
            <p><strong style="color:#1f2937;">Incomplete</strong>: Games with no completion yet. The user may still be actively reading.</p>
            <p><strong style="color:#1f2937;">Abandoned</strong>: Subset of Incomplete with no gameplay activity for 14+ days.</p>
            <p><strong style="color:#1f2937;">Returning Users</strong>: Users active in this period who also had prior activity before it.</p>
            <p><strong style="color:#1f2937;">Replay Events</strong>: Resets triggered after a story was completed. Each reset starts a new story cycle.</p>
            <p><strong style="color:#1f2937;">D1 / D7 / D30 Retention</strong>: % of users who returned on day 1, within 7 days, or within 30 days of their first session.</p>
            <p><strong style="color:#1f2937;">Return Rate</strong>: % of users with more than one distinct active day since the baseline.</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
