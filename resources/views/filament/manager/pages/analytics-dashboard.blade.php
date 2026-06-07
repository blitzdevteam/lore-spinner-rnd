<x-filament-panels::page>
    {{-- Baseline notice --}}
    <div class="rounded-xl border border-purple-200 dark:border-purple-900/60 bg-purple-50 dark:bg-purple-950/30 px-5 py-3 flex items-center gap-3 text-sm">
        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 dark:bg-purple-900/60 text-purple-600 dark:text-purple-300 flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
            </svg>
        </span>
        <p class="text-purple-800 dark:text-purple-200">
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
    <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 p-5 text-sm space-y-3">
        <p class="font-semibold text-gray-800 dark:text-gray-100 text-base">Metric definitions</p>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2 text-gray-600 dark:text-gray-300">
            <div><dt class="font-medium text-gray-800 dark:text-gray-100 inline">Visits</dt>
                <dd class="inline">: Unique browsing windows on public pages, tracked by an anonymous cookie. Not the same as story chapters (those are called Sessions).</dd></div>
            <div><dt class="font-medium text-gray-800 dark:text-gray-100 inline">Signups</dt>
                <dd class="inline">: New user accounts created since the baseline.</dd></div>
            <div><dt class="font-medium text-gray-800 dark:text-gray-100 inline">Story Starts</dt>
                <dd class="inline">: Total individual game plays created (one per play-through). A single user may have multiple starts across stories or replays.</dd></div>
            <div><dt class="font-medium text-gray-800 dark:text-gray-100 inline">Session 1 Completions</dt>
                <dd class="inline">: Users who finished Chapter 1 and advanced to Chapter 2. The first major drop-off point in the funnel.</dd></div>
            <div><dt class="font-medium text-gray-800 dark:text-gray-100 inline">Story Completions</dt>
                <dd class="inline">: Total full-story completions recorded. One per story cycle per game. Replays after reset count as new completion events. Rate = unique completed / starts.</dd></div>
            <div><dt class="font-medium text-gray-800 dark:text-gray-100 inline">Incomplete</dt>
                <dd class="inline">: Games with no completion on record yet. The user may still be actively reading. This is not a label of failure.</dd></div>
            <div><dt class="font-medium text-gray-800 dark:text-gray-100 inline">Abandoned</dt>
                <dd class="inline">: Subset of Incomplete with no gameplay activity (prompts, sessions, resets) for 14+ days. Does not include page views.</dd></div>
            <div><dt class="font-medium text-gray-800 dark:text-gray-100 inline">Returning Users</dt>
                <dd class="inline">: Users who were active in the selected period and also had prior activity before it. Measures whether users come back over time.</dd></div>
            <div><dt class="font-medium text-gray-800 dark:text-gray-100 inline">Replay Events</dt>
                <dd class="inline">: Resets triggered after a story was already completed. Each reset starts a new story cycle and counts as a replay event.</dd></div>
            <div><dt class="font-medium text-gray-800 dark:text-gray-100 inline">D1 / D7 / D30 Retention</dt>
                <dd class="inline">: % of users who returned on day 1, within 7 days, or within 30 days of their first playable session.</dd></div>
            <div><dt class="font-medium text-gray-800 dark:text-gray-100 inline">Return Rate</dt>
                <dd class="inline">: % of users with more than one distinct active day recorded since the baseline.</dd></div>
        </dl>
    </div>
</x-filament-panels::page>
