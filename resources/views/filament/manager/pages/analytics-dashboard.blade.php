<x-filament-panels::page>
    <div style="display:flex; flex-direction:column; gap:24px;">

        {{-- Baseline notice --}}
        <div style="border-radius:12px; border:1px solid #e9d5ff; background:#faf5ff; padding:14px 20px; display:flex; align-items:center; gap:14px;">
            <span style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:9999px; background:#f3e8ff; color:#9333ea; flex-shrink:0;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
                </svg>
            </span>
            <div>
                <p style="margin:0; font-size:14px; font-weight:600; color:#6b21a8;">Data baseline: June 1, 2026</p>
                <p style="margin:4px 0 0; font-size:13px; color:#7e22ce;">All metrics exclude activity before this date. See the glossary below for metric definitions.</p>
            </div>
        </div>

        {{-- KPI Cards --}}
        <div>
            @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsKpiWidget::class)
        </div>

        {{-- Funnel Chart --}}
        <div>
            @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsFunnelWidget::class)
        </div>

        {{-- Retention --}}
        <div>
            @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsRetentionWidget::class)
        </div>

        {{-- Metric Glossary --}}
        <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
            <div style="padding:16px 24px; border-bottom:1px solid #f3f4f6; background:#f9fafb;">
                <p style="margin:0 0 2px; font-size:15px; font-weight:600; color:#111827;">Metric Glossary</p>
                <p style="margin:0; font-size:13px; color:#9ca3af;">Definitions for every number shown in the KPI cards above.</p>
            </div>
            <div style="padding:20px 24px;">
                @php
                    $defs = [
                        ['Visits',                'Unique browsing windows on public pages, tracked by an anonymous cookie. Not the same as story chapters (those are called Sessions).'],
                        ['Signups',               'New user accounts created since the baseline.'],
                        ['Story Starts',          'Total individual game plays created (one per play-through). A single user may have multiple starts across stories or replays.'],
                        ['Session 1 Completions', 'Users who finished Chapter 1 and advanced to Chapter 2. The first major drop-off point in the funnel.'],
                        ['Story Completions',     'Total full-story completions. One per story cycle per game. Replays count separately. Rate = unique completed ÷ starts.'],
                        ['Incomplete',            'Games with no completion on record yet. The user may still be actively reading. This is not a label of failure.'],
                        ['Abandoned',             'Subset of Incomplete with no gameplay activity (prompts, sessions, resets) for 14+ days.'],
                        ['Returning Users',       'Users who were active in the selected period and also had prior activity before it.'],
                        ['Replay Events',         'Resets triggered after a story was already completed. Each reset starts a new story cycle.'],
                        ['D1 / D7 / D30',         '% of users who returned on day 1, within 7 days, or within 30 days of their first playable session.'],
                        ['Return Rate',           '% of users with more than one distinct active day recorded since the baseline.'],
                    ];
                @endphp
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0;">
                    @foreach ($defs as $i => $def)
                        <div style="padding:12px 16px; display:flex; gap:10px; align-items:baseline; {{ $i >= 2 ? 'border-top:1px solid #f3f4f6;' : '' }} {{ $i % 2 === 1 ? 'border-left:1px solid #f3f4f6;' : '' }}">
                            <span style="font-weight:700; font-size:13px; color:#111827; white-space:nowrap; flex-shrink:0;">{{ $def[0] }}</span>
                            <span style="font-size:13px; color:#6b7280; line-height:1.5;">{{ $def[1] }}</span>
                        </div>
                    @endforeach
                    {{-- Fill last cell if odd count --}}
                    @if (count($defs) % 2 !== 0)
                        <div style="border-top:1px solid #f3f4f6; border-left:1px solid #f3f4f6;"></div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
