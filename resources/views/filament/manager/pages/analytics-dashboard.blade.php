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
    <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
        <div style="padding:14px 20px; border-bottom:1px solid #f3f4f6; background:#f9fafb;">
            <span style="font-weight:600; font-size:14px; color:#111827;">Metric Definitions</span>
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            @php
                $defs = [
                    ['Visits',               'Unique browsing windows on public pages, tracked by an anonymous cookie. Not the same as story chapters (those are called Sessions).'],
                    ['Signups',              'New user accounts created since the baseline.'],
                    ['Story Starts',         'Total individual game plays created (one per play-through). A single user may have multiple starts across stories or replays.'],
                    ['Session 1 Completions','Users who finished Chapter 1 and advanced to Chapter 2. The first major drop-off point in the funnel.'],
                    ['Story Completions',    'Total full-story completions recorded. One per story cycle per game. Replays after reset count separately. Rate = unique completed / starts.'],
                    ['Incomplete',           'Games with no completion on record yet. The user may still be actively reading. This is not a label of failure.'],
                    ['Abandoned',            'Subset of Incomplete with no gameplay activity (prompts, sessions, resets) for 14+ days.'],
                    ['Returning Users',      'Users who were active in the selected period and also had prior activity before it. Measures whether users come back over time.'],
                    ['Replay Events',        'Resets triggered after a story was already completed. Each reset starts a new story cycle and counts as a replay event.'],
                    ['D1 / D7 / D30',        '% of users who returned on day 1, within 7 days, or within 30 days of their first playable session.'],
                    ['Return Rate',          '% of users with more than one distinct active day recorded since the baseline.'],
                ];
                $pairs = array_chunk($defs, 2);
            @endphp
            @foreach ($pairs as $i => $pair)
                <tr style="{{ $i > 0 ? 'border-top:1px solid #f3f4f6;' : '' }}">
                    @foreach ($pair as $def)
                        <td style="padding:10px 20px; vertical-align:top; width:50%;">
                            <span style="font-weight:600; color:#111827;">{{ $def[0] }}</span>
                            <span style="color:#6b7280; margin-left:4px;">{{ $def[1] }}</span>
                        </td>
                    @endforeach
                    @if (count($pair) === 1)
                        <td style="width:50%;"></td>
                    @endif
                </tr>
            @endforeach
        </table>
    </div>
</x-filament-panels::page>
