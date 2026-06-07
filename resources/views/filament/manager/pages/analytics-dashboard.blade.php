<x-filament-panels::page>

    @php
        $funnelData  = $this->getFunnelData();
        $topStories  = $this->getTopStories();
        $keyInsights = $this->getKeyInsights();

        $defs = [
            ['Unique Visits',       'A distinct browser session that loaded any public page. One person opening the site 5 times = 5 visits.'],
            ['Signups',             'New registered accounts created within the period.'],
            ['Story Starts',        'Unique game instances launched (one per user per story attempt). Previews excluded.'],
            ['Ch. 1 Completed',     'Games where the player finished Chapter 1 and the engine saved a session completion record.'],
            ['Story Completions',   'Games that reached the final chapter and generated a completion event (distinct game counted once per cycle).'],
            ['Replays',             'Fresh game resets triggered after at least one prior completion — confirms a user chose to re-read.'],
            ['Abandoned',           'Incomplete games with no gameplay activity for 14+ consecutive days.'],
            ['Return Users',        'Registered users who were active in the period but whose first-ever activity pre-dates the period start.'],
        ];
    @endphp

    {{-- ── Baseline notice ──────────────────────────────────────────── --}}
    <div style="display:flex; align-items:flex-start; gap:12px; padding:16px 20px; border-radius:10px; background:#f0fdf4; border:1px solid #bbf7d0; margin-bottom:4px;">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0; margin-top:1px;">
            <circle cx="10" cy="10" r="9" stroke="#16a34a" stroke-width="1.5"/>
            <path d="M10 9v5M10 7h.01" stroke="#16a34a" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <div>
            <p style="margin:0 0 2px; font-size:13px; font-weight:600; color:#15803d;">Baseline: June 1, 2025 — All metrics start here</p>
            <p style="margin:0; font-size:12px; color:#4ade80; color:#16a34a;">Data before this date is excluded. Hover cards for metric definitions.</p>
        </div>
    </div>

    {{-- ── KPI Stat Cards ──────────────────────────────────────────── --}}
    @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsKpiWidget::class)

    {{-- ── Conversion Funnel ───────────────────────────────────────── --}}
    @if (!empty($funnelData))
    <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
        <div style="padding:16px 24px 12px; border-bottom:1px solid #f3f4f6; background:#fafafa; display:flex; align-items:baseline; justify-content:space-between;">
            <div>
                <p style="margin:0 0 2px; font-size:15px; font-weight:600; color:#111827;">Conversion Funnel</p>
                <p style="margin:0; font-size:12px; color:#9ca3af;">Each step's % is relative to the step above and to total Visits</p>
            </div>
        </div>
        <div style="padding:20px 24px; display:flex; flex-direction:column; gap:12px;">
            @foreach ($funnelData as $i => $step)
            <div style="display:flex; align-items:center; gap:14px;">
                {{-- Step label --}}
                <div style="width:145px; flex-shrink:0; text-align:right;">
                    <span style="font-size:13px; font-weight:600; color:#374151;">{{ $step['label'] }}</span>
                </div>
                {{-- Bar --}}
                <div style="flex:1; position:relative; height:28px; background:#f3f4f6; border-radius:6px; overflow:hidden;">
                    <div style="position:absolute; left:0; top:0; bottom:0; border-radius:6px; background:{{ $step['color'] }}; width:{{ $step['bar_width'] }}%; opacity:0.85; transition:width 0.3s;"></div>
                    <div style="position:absolute; left:10px; top:0; bottom:0; display:flex; align-items:center;">
                        <span style="font-size:12px; font-weight:700; color:#fff; text-shadow:0 1px 2px rgba(0,0,0,0.3);">{{ number_format($step['value']) }}</span>
                    </div>
                </div>
                {{-- Conversion badges --}}
                <div style="width:160px; flex-shrink:0; display:flex; align-items:center; gap:6px;">
                    <span style="font-size:11px; font-weight:600; color:#6b7280; background:#f3f4f6; padding:2px 7px; border-radius:12px; white-space:nowrap;">
                        {{ $step['pct_top'] }}% of visits
                    </span>
                    @if ($step['pct_prev'] !== null)
                    <span style="font-size:11px; font-weight:600; color:{{ $step['pct_prev'] >= 50 ? '#15803d' : ($step['pct_prev'] >= 20 ? '#b45309' : '#dc2626') }}; background:{{ $step['pct_prev'] >= 50 ? '#f0fdf4' : ($step['pct_prev'] >= 20 ? '#fffbeb' : '#fef2f2') }}; padding:2px 7px; border-radius:12px; white-space:nowrap;">
                        {{ $step['pct_prev'] }}% ↓
                    </span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Insights + Top Stories ───────────────────────────────────── --}}
    @if (!empty($keyInsights) || !empty($topStories))
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start;">

        {{-- Key Insights --}}
        @if (!empty($keyInsights))
        <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
            <div style="padding:16px 24px 12px; border-bottom:1px solid #f3f4f6; background:#fafafa;">
                <p style="margin:0 0 2px; font-size:15px; font-weight:600; color:#111827;">Key Insights</p>
                <p style="margin:0; font-size:12px; color:#9ca3af;">Computed from live data since baseline</p>
            </div>
            <div style="padding:16px 24px; display:flex; flex-direction:column; gap:14px;">
                @foreach ($keyInsights as $insight)
                <div style="display:flex; align-items:flex-start; gap:12px; padding:12px 14px; border-radius:8px; background:#f9fafb; border-left:3px solid {{ $insight['color'] }};">
                    <span style="font-size:18px; line-height:1; flex-shrink:0;">{{ $insight['icon'] }}</span>
                    <p style="margin:0; font-size:13px; color:#374151; line-height:1.5;">
                        <span style="font-weight:700; color:{{ $insight['color'] }};">{{ $insight['highlight'] }}</span>
                        {{ $insight['text'] }}
                    </p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Top Stories --}}
        @if (!empty($topStories))
        <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
            <div style="padding:16px 24px 12px; border-bottom:1px solid #f3f4f6; background:#fafafa;">
                <p style="margin:0 0 2px; font-size:15px; font-weight:600; color:#111827;">Top Stories</p>
                <p style="margin:0; font-size:12px; color:#9ca3af;">Ranked by game starts since baseline</p>
            </div>
            <div style="padding:16px 24px; display:flex; flex-direction:column; gap:12px;">
                @foreach ($topStories as $rank => $story)
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="width:22px; height:22px; border-radius:50%; background:{{ $rank === 0 ? '#6366f1' : ($rank === 1 ? '#8b5cf6' : '#d1d5db') }}; color:#fff; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0;">{{ $rank + 1 }}</span>
                    <div style="flex:1; min-width:0;">
                        <p style="margin:0 0 4px; font-size:13px; font-weight:600; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $story['title'] }}</p>
                        <div style="height:6px; background:#f3f4f6; border-radius:3px; overflow:hidden;">
                            <div style="height:100%; background:{{ $rank === 0 ? '#6366f1' : ($rank === 1 ? '#8b5cf6' : '#a5b4fc') }}; border-radius:3px; width:{{ $story['pct'] }}%;"></div>
                        </div>
                    </div>
                    <span style="flex-shrink:0; font-size:13px; font-weight:700; color:#374151; width:55px; text-align:right;">{{ $story['starts'] }} start{{ $story['starts'] !== 1 ? 's' : '' }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @else
        {{-- Insights takes full width if no stories yet --}}
        @endif

    </div>
    @endif

    {{-- ── Retention ────────────────────────────────────────────────── --}}
    @livewire(\App\Filament\Manager\Widgets\Analytics\AnalyticsRetentionWidget::class)

    {{-- ── Metric Glossary ─────────────────────────────────────────── --}}
    <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden;">
        <div style="padding:16px 24px 12px; border-bottom:1px solid #f3f4f6; background:#fafafa;">
            <p style="margin:0 0 2px; font-size:15px; font-weight:600; color:#111827;">Metric Glossary</p>
            <p style="margin:0; font-size:13px; color:#9ca3af;">Definitions for every number shown in the KPI cards above.</p>
        </div>
        <div style="padding:20px 24px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0;">
                @foreach ($defs as $i => $def)
                <div style="padding:12px 16px; display:flex; gap:10px; align-items:baseline; {{ $i >= 2 ? 'border-top:1px solid #f3f4f6;' : '' }} {{ $i % 2 === 1 ? 'border-left:1px solid #f3f4f6;' : '' }}">
                    <span style="font-weight:700; font-size:13px; color:#111827; white-space:nowrap; flex-shrink:0;">{{ $def[0] }}</span>
                    <span style="font-size:12px; color:#6b7280; line-height:1.5;">{{ $def[1] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</x-filament-panels::page>
