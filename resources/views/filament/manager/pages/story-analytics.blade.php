<x-filament-panels::page>
    @php
        $stories     = $this->getStoryMetrics();
        $progression = $this->getStoryProgression();
        $funnels     = $this->getSessionFunnels();
        $dropOffs    = $this->dropOffSessions();
    @endphp

    <div class="space-y-6">

        {{-- Legend --}}
        <x-filament::section>
            <x-slot name="heading">Metric Definitions</x-slot>
            <x-slot name="description">Data baseline: June 1, 2026. All metrics exclude data before this date.</x-slot>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
                <p><strong class="text-gray-800 dark:text-gray-200">Starts</strong>: total games created for a story since the baseline (excl. previews)</p>
                <p><strong class="text-gray-800 dark:text-gray-200">Completion %</strong>: unique games completed divided by starts (capped at 100%; replays do not inflate this)</p>
                <p><strong class="text-gray-800 dark:text-gray-200">Comp. Events</strong>: total rows in game_completions (all story cycles; can exceed starts)</p>
                <p><strong class="text-gray-800 dark:text-gray-200">Replay %</strong>: unique replayers divided by unique completed games</p>
                <p><strong class="text-gray-800 dark:text-gray-200">Incomplete</strong>: started and not yet completed. User may still be actively reading.</p>
                <p><strong class="text-gray-800 dark:text-gray-200">Abandoned</strong>: incomplete with no gameplay activity for 14+ days</p>
                <p><strong class="text-gray-800 dark:text-gray-200">Content Progression</strong>: Started → Reached Chapter 2 → ... → Completed (distinct games per step)</p>
                <p><strong class="text-gray-800 dark:text-gray-200">Avg / Median Session</strong>: mean and median of (completed_at minus started_at) per chapter</p>
                <p><strong class="text-gray-800 dark:text-gray-200">Avg Completion</strong>: mean time from Chapter 1 start to story completion, per story cycle</p>
                <p><strong class="text-gray-800 dark:text-gray-200">Drop-off Chapter</strong>: chapter with the highest absolute user loss (reached minus completed)</p>
            </div>
        </x-filament::section>

        @if ($stories->isEmpty())
            <x-filament::section>
                <p style="text-align:center; color:#9ca3af; padding:32px 0; font-size:14px;">
                    No gameplay data yet. Metrics will appear once users start stories on or after June 1, 2026.
                </p>
            </x-filament::section>
        @else
            {{-- Summary table --}}
            <x-filament::section>
                <x-slot name="heading">Story Summary</x-slot>

                <div style="overflow-x:auto; margin:0 -24px -24px;">
                    <table style="min-width:100%; font-size:13px; border-collapse:collapse;">
                        <thead>
                            <tr style="background-color:#f9fafb; text-align:left; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;">
                                <th style="padding:10px 16px; white-space:nowrap;">Story</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Starts</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Completed</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Completion %</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Incomplete</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Incomplete %</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Abandoned</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Abandoned %</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Comp. Events</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Replay Events</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Unique Replayers</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Replay %</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Avg Session</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Avg Completion</th>
                                <th style="padding:10px 16px; text-align:right; white-space:nowrap;">Drop-off</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stories as $story)
                                <tr style="border-top:1px solid #f3f4f6;">

                                    <td style="padding:10px 16px; font-weight:500; color:#111827; min-width:180px; max-width:260px;">
                                        <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $story->title }}</div>
                                    </td>

                                    <td style="padding:10px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#374151;">{{ number_format($story->starts) }}</td>

                                    <td style="padding:10px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#374151;">{{ number_format($story->unique_completed) }}</td>

                                    <td style="padding:10px 16px; text-align:right; font-variant-numeric:tabular-nums;">
                                        @if ($story->completion_rate !== null)
                                            <span style="font-weight:600; color:{{ $story->completion_rate >= 60 ? '#16a34a' : ($story->completion_rate >= 30 ? '#ca8a04' : '#dc2626') }};">{{ $story->completion_rate }}%</span>
                                        @else
                                            <span style="color:#9ca3af;">-</span>
                                        @endif
                                    </td>

                                    <td style="padding:10px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#374151;">{{ number_format($story->incomplete) }}</td>

                                    <td style="padding:10px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#4b5563;">
                                        @if ($story->incomplete_rate !== null)
                                            {{ $story->incomplete_rate }}%
                                        @else
                                            <span style="color:#9ca3af;">-</span>
                                        @endif
                                    </td>

                                    <td style="padding:10px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#374151;">{{ number_format($story->abandoned) }}</td>

                                    <td style="padding:10px 16px; text-align:right; font-variant-numeric:tabular-nums;">
                                        @if ($story->abandoned_rate !== null)
                                            <span style="font-weight:600; color:{{ $story->abandoned_rate >= 60 ? '#dc2626' : ($story->abandoned_rate >= 30 ? '#ca8a04' : '#16a34a') }};">{{ $story->abandoned_rate }}%</span>
                                        @else
                                            <span style="color:#9ca3af;">-</span>
                                        @endif
                                    </td>

                                    <td style="padding:10px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#4b5563;">{{ number_format($story->completion_events) }}</td>
                                    <td style="padding:10px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#374151;">{{ number_format($story->replay_events) }}</td>
                                    <td style="padding:10px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#374151;">{{ number_format($story->unique_replayers) }}</td>

                                    <td style="padding:10px 16px; text-align:right; font-variant-numeric:tabular-nums;">
                                        @if ($story->replay_rate !== null)
                                            <span style="font-weight:600; color:#9333ea;">{{ $story->replay_rate }}%</span>
                                        @else
                                            <span style="color:#9ca3af;">-</span>
                                        @endif
                                    </td>

                                    <td style="padding:10px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#6b7280;">
                                        @if ($story->avg_minutes !== null)
                                            @php $mins = (float) $story->avg_minutes; $h = floor($mins / 60); $m = round($mins % 60); @endphp
                                            {{ $h > 0 ? "{$h}h {$m}m" : "{$m}m" }}
                                        @else
                                            <span style="color:#9ca3af;">-</span>
                                        @endif
                                    </td>

                                    <td style="padding:10px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#6b7280;">
                                        @if ($story->avg_completion_minutes !== null)
                                            @php $mins = (float) $story->avg_completion_minutes; $h = floor($mins / 60); $m = round($mins % 60); @endphp
                                            {{ $h > 0 ? "{$h}h {$m}m" : "{$m}m" }}
                                        @else
                                            <span style="color:#9ca3af;">-</span>
                                        @endif
                                    </td>

                                    <td style="padding:10px 16px; text-align:right;">
                                        @if ($story->drop_off_session !== null)
                                            <span style="display:inline-flex; align-items:center; border-radius:6px; padding:1px 8px; font-size:11px; font-weight:600; background-color:#fee2e2; color:#b91c1c;">
                                                Ch. {{ $story->drop_off_session }}
                                            </span>
                                        @else
                                            <span style="color:#9ca3af;">-</span>
                                        @endif
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            {{-- Summary footer --}}
            @php
                $totalStarts           = $stories->sum('starts');
                $totalCompleted        = $stories->sum('unique_completed');
                $totalCompletionEvents = $stories->sum('completion_events');
                $totalAbandoned        = $stories->sum('abandoned');
                $totalReplayEvents     = $stories->sum('replay_events');
                $totalReplayers        = $stories->sum('unique_replayers');
                $overallCompletion     = $totalStarts > 0 ? round(($totalCompleted / $totalStarts) * 100, 1) : null;
            @endphp
            <div class="flex flex-wrap gap-3 text-sm text-gray-500 dark:text-gray-400 px-1">
                <span>{{ $stories->count() }} stories with data</span>
                <span>·</span>
                <span>{{ number_format($totalStarts) }} total starts</span>
                <span>·</span>
                <span>{{ number_format($totalCompleted) }} unique completed · {{ number_format($totalCompletionEvents) }} completion events</span>
                @if ($overallCompletion !== null)
                    <span>·</span>
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $overallCompletion }}% overall completion rate</span>
                @endif
                <span>·</span>
                <span>{{ number_format($totalAbandoned) }} abandoned · {{ number_format($totalReplayEvents) }} replay events · {{ number_format($totalReplayers) }} unique replayers</span>
            </div>

            {{-- Content progression --}}
            @if ($progression->isNotEmpty())
                <x-filament::section>
                    <x-slot name="heading">Content Progression</x-slot>
                    <x-slot name="description">How many distinct games reach each chapter. Shows where stories lose users.</x-slot>

                    <div style="display:flex; flex-direction:column; gap:12px;">
                        @foreach ($progression as $prog)
                            <div style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
                                <div style="padding:8px 16px; background-color:#f9fafb; border-bottom:1px solid #f3f4f6;">
                                    <span style="font-weight:600; color:#111827; font-size:13px;">{{ $prog->title }}</span>
                                </div>
                                <div style="padding:10px 16px; display:flex; flex-wrap:wrap; gap:8px 24px; font-size:13px; font-variant-numeric:tabular-nums;">
                                    @php $starts = (int) $prog->starts; @endphp
                                    <div>
                                        <span style="color:#6b7280;">Started</span>
                                        <span style="margin-left:8px; font-weight:600; color:#111827;">{{ number_format($starts) }}</span>
                                    </div>
                                    @foreach ($prog->reached as $sessionNum => $reached)
                                        @php $pct = $starts > 0 ? round($reached / $starts * 100, 1) : null; @endphp
                                        <div>
                                            <span style="color:#6b7280;">Reached Ch. {{ $sessionNum }}</span>
                                            <span style="margin-left:8px; font-weight:600; color:#1f2937;">{{ number_format($reached) }}</span>
                                            @if ($pct !== null)
                                                <span style="margin-left:4px; font-size:11px; color:#9ca3af;">({{ $pct }}%)</span>
                                            @endif
                                        </div>
                                    @endforeach
                                    @php $compPct = $starts > 0 ? round($prog->completions / $starts * 100, 1) : null; @endphp
                                    <div>
                                        <span style="color:#6b7280;">Completed</span>
                                        <span style="margin-left:8px; font-weight:600; color:#16a34a;">{{ number_format($prog->completions) }}</span>
                                        @if ($compPct !== null)
                                            <span style="margin-left:4px; font-size:11px; color:#9ca3af;">({{ $compPct }}%)</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            {{-- Chapter funnel per story --}}
            @if ($funnels->isNotEmpty())
                <x-filament::section>
                    <x-slot name="heading">Chapter Funnel</x-slot>
                    <x-slot name="description">Aggregated across all story cycles. Each row is unique to a (game, story cycle, chapter) tuple — no data is overwritten on replay.</x-slot>

                    <div style="display:flex; flex-direction:column; gap:16px;">
                        @foreach ($stories as $story)
                            @php
                                $sessionRows = $funnels->get($story->id);
                                $dropOff     = $dropOffs[$story->id] ?? null;
                            @endphp
                            @if ($sessionRows && $sessionRows->isNotEmpty())
                                <div style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">

                                    <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; border-bottom:1px solid #f3f4f6; background-color:#f9fafb;">
                                        <div>
                                            <span style="font-weight:600; color:#111827; font-size:13px;">{{ $story->title }}</span>
                                            @if ($story->completion_rate !== null)
                                                <span style="margin-left:8px; font-size:11px; color:#9ca3af;">{{ $story->completion_rate }}% story completion</span>
                                            @endif
                                        </div>
                                        @if ($dropOff !== null)
                                            <span style="font-size:11px; color:#dc2626; font-weight:500;">Biggest drop: Chapter {{ $dropOff }}</span>
                                        @endif
                                    </div>

                                    <table style="min-width:100%; border-collapse:collapse; font-size:13px;">
                                        <thead>
                                            <tr style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280; background-color:#f9fafb;">
                                                <th style="padding:8px 16px; text-align:left; width:140px; white-space:nowrap;">Chapter</th>
                                                <th style="padding:8px 16px; text-align:right; white-space:nowrap;">Reached</th>
                                                <th style="padding:8px 16px; text-align:right; white-space:nowrap;">Completed</th>
                                                <th style="padding:8px 16px; text-align:right; white-space:nowrap;">Dropped</th>
                                                <th style="padding:8px 16px; text-align:right; white-space:nowrap;">Completion %</th>
                                                <th style="padding:8px 16px; text-align:right; white-space:nowrap;">Avg / Median</th>
                                                <th style="padding:8px 16px; text-align:left; padding-left:24px; white-space:nowrap;">Progress</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($sessionRows as $session)
                                                @php
                                                    $isDropOff = $dropOff !== null && (int) $session->session_number === $dropOff;
                                                    $pct       = $session->completion_pct;
                                                    $barWidth  = $pct !== null ? max(2, (int) $pct) : 0;
                                                @endphp
                                                <tr style="border-top:1px solid #f3f4f6; {{ $isDropOff ? 'background-color:#fff5f5;' : '' }}">
                                                    <td style="padding:8px 16px; font-weight:500; color:#374151;">
                                                        <div style="display:flex; align-items:center; gap:8px;">
                                                            Chapter {{ $session->session_number }}
                                                            @if ($isDropOff)
                                                                <span style="font-size:11px; color:#ef4444; font-weight:600;">drop-off</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td style="padding:8px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#4b5563;">{{ number_format($session->reached) }}</td>
                                                    <td style="padding:8px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#4b5563;">{{ number_format($session->completed_cnt) }}</td>
                                                    <td style="padding:8px 16px; text-align:right; font-variant-numeric:tabular-nums;">
                                                        @if ((int) $session->dropped > 0)
                                                            <span style="font-weight:600; color:{{ $isDropOff ? '#dc2626' : '#6b7280' }};">-{{ number_format($session->dropped) }}</span>
                                                        @else
                                                            <span style="color:#9ca3af;">0</span>
                                                        @endif
                                                    </td>
                                                    <td style="padding:8px 16px; text-align:right; font-variant-numeric:tabular-nums;">
                                                        @if ($pct !== null)
                                                            <span style="font-weight:600; color:{{ $pct >= 70 ? '#16a34a' : ($pct >= 40 ? '#ca8a04' : '#dc2626') }};">{{ $pct }}%</span>
                                                        @else
                                                            <span style="color:#9ca3af;">-</span>
                                                        @endif
                                                    </td>
                                                    <td style="padding:8px 16px; text-align:right; font-variant-numeric:tabular-nums; color:#6b7280;">
                                                        @if ($session->avg_minutes !== null || $session->median_minutes !== null)
                                                            @php
                                                                $fmtDur = function (?float $mins): string {
                                                                    if ($mins === null) return '-';
                                                                    $h = floor($mins / 60);
                                                                    $m = round($mins % 60);
                                                                    return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
                                                                };
                                                            @endphp
                                                            {{ $fmtDur($session->avg_minutes !== null ? (float) $session->avg_minutes : null) }}
                                                            <span style="color:#d1d5db; margin:0 2px;">/</span>
                                                            {{ $fmtDur($session->median_minutes !== null ? (float) $session->median_minutes : null) }}
                                                        @else
                                                            <span style="color:#9ca3af;">-</span>
                                                        @endif
                                                    </td>
                                                    <td style="padding:8px 16px; padding-left:24px;">
                                                        @if ($pct !== null)
                                                            <div style="width:128px; height:6px; background-color:#f3f4f6; border-radius:9999px; overflow:hidden;">
                                                                <div style="height:100%; border-radius:9999px; width:{{ $barWidth }}%; background-color:{{ $pct >= 70 ? '#22c55e' : ($pct >= 40 ? '#eab308' : '#ef4444') }};"></div>
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </x-filament::section>
            @endif
        @endif

    </div>
</x-filament-panels::page>
