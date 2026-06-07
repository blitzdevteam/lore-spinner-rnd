<x-filament-panels::page>
    @php
        $stories     = $this->getStoryMetrics();
        $progression = $this->getStoryProgression();
        $funnels     = $this->getSessionFunnels();
        $dropOffs    = $this->dropOffSessions();
    @endphp

    <div class="space-y-4">

        {{-- Legend --}}
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-4 text-sm text-gray-500 dark:text-gray-400 space-y-1">
            <p class="font-semibold text-gray-700 dark:text-gray-200 mb-2">Data baseline: June 1, 2026 — all metrics exclude data before this date</p>
            <p><strong class="text-gray-700 dark:text-gray-200">Starts</strong> — <code>games.created_at WHERE is_preview = false AND created_at >= '2026-06-01'</code></p>
            <p><strong class="text-gray-700 dark:text-gray-200">Completion %</strong> — unique games completed ÷ starts (capped at 100%; replays don't inflate this)</p>
            <p><strong class="text-gray-700 dark:text-gray-200">Completion Events</strong> — total <code>game_completions</code> rows (all story cycles; can exceed starts)</p>
            <p><strong class="text-gray-700 dark:text-gray-200">Replay %</strong> — unique replayers ÷ unique completed games (not replay events ÷ completions)</p>
            <p><strong class="text-gray-700 dark:text-gray-200">Incomplete</strong> — Started and not yet completed (<code>games.completed_at IS NULL</code>). Player may still be active.</p>
            <p><strong class="text-gray-700 dark:text-gray-200">Abandoned</strong> — Incomplete with no gameplay activity for 14+ days</p>
            <p><strong class="text-gray-700 dark:text-gray-200">Replays</strong> — <code>game_resets WHERE had_prior_completion = true</code></p>
            <p><strong class="text-gray-700 dark:text-gray-200">Content Progression</strong> — Started → Reached S2 → S3 → … → Completed (distinct games per step)</p>
            <p><strong class="text-gray-700 dark:text-gray-200">Avg / Median Session</strong> — Mean and median <code>completed_at − started_at</code> per session (median reflects typical player experience)</p>
            <p><strong class="text-gray-700 dark:text-gray-200">Avg Completion</strong> — Mean time from Session 1 start to story completion, per story cycle</p>
            <p><strong class="text-gray-700 dark:text-gray-200">Drop-off Session</strong> — Session with the highest absolute player loss (reached − completed)</p>
        </div>

        @if ($stories->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 dark:border-white/10 p-12 text-center text-sm text-gray-400 dark:text-gray-500">
                No gameplay data yet. Metrics will appear once players start stories on or after June 1, 2026.
            </div>
        @else
            {{-- Summary table --}}
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10 text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-white/5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">Story</th>
                            <th class="px-4 py-3 text-right">Starts</th>
                            <th class="px-4 py-3 text-right">Completed</th>
                            <th class="px-4 py-3 text-right">Completion %</th>
                            <th class="px-4 py-3 text-right">Comp. Events</th>
                            <th class="px-4 py-3 text-right">Incomplete</th>
                            <th class="px-4 py-3 text-right">Incomplete %</th>
                            <th class="px-4 py-3 text-right">Abandoned</th>
                            <th class="px-4 py-3 text-right">Abandoned %</th>
                            <th class="px-4 py-3 text-right">Replay Events</th>
                            <th class="px-4 py-3 text-right">Unique Replayers</th>
                            <th class="px-4 py-3 text-right">Replay %</th>
                            <th class="px-4 py-3 text-right">Avg Session</th>
                            <th class="px-4 py-3 text-right">Avg Completion</th>
                            <th class="px-4 py-3 text-right">Drop-off</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($stories as $story)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">

                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white max-w-xs">
                                    <div class="truncate">{{ $story->title }}</div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ $story->slug }}</div>
                                </td>

                                <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($story->starts) }}</td>

                                <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($story->unique_completed) }}</td>

                                <td class="px-4 py-3 text-right tabular-nums">
                                    @if ($story->completion_rate !== null)
                                        <span @class(['font-semibold', 'text-green-600 dark:text-green-400' => $story->completion_rate >= 60, 'text-yellow-600 dark:text-yellow-400' => $story->completion_rate >= 30 && $story->completion_rate < 60, 'text-red-600 dark:text-red-400' => $story->completion_rate < 30])>{{ $story->completion_rate }}%</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($story->incomplete) }}</td>

                                <td class="px-4 py-3 text-right tabular-nums">
                                    @if ($story->incomplete_rate !== null)
                                        <span class="text-gray-600 dark:text-gray-400">{{ $story->incomplete_rate }}%</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($story->abandoned) }}</td>

                                <td class="px-4 py-3 text-right tabular-nums">
                                    @if ($story->abandoned_rate !== null)
                                        <span @class(['font-semibold', 'text-red-600 dark:text-red-400' => $story->abandoned_rate >= 60, 'text-yellow-600 dark:text-yellow-400' => $story->abandoned_rate >= 30 && $story->abandoned_rate < 60, 'text-green-600 dark:text-green-400' => $story->abandoned_rate < 30])>{{ $story->abandoned_rate }}%</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-400">{{ number_format($story->completion_events) }}</td>

                                <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($story->replay_events) }}</td>

                                <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($story->unique_replayers) }}</td>

                                <td class="px-4 py-3 text-right tabular-nums">
                                    @if ($story->replay_rate !== null)
                                        <span class="font-semibold text-purple-600 dark:text-purple-400">{{ $story->replay_rate }}%</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- Avg session duration --}}
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                    @if ($story->avg_minutes !== null)
                                        @php $mins = (float) $story->avg_minutes; $h = floor($mins / 60); $m = round($mins % 60); @endphp
                                        {{ $h > 0 ? "{$h}h {$m}m" : "{$m}m" }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- Avg completion time --}}
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                    @if ($story->avg_completion_minutes !== null)
                                        @php $mins = (float) $story->avg_completion_minutes; $h = floor($mins / 60); $m = round($mins % 60); @endphp
                                        {{ $h > 0 ? "{$h}h {$m}m" : "{$m}m" }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right">
                                    @if ($story->drop_off_session !== null)
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 ring-1 ring-red-200 dark:ring-red-700/30">
                                            Session {{ $story->drop_off_session }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Summary footer --}}
            @php
                $totalStarts      = $stories->sum('starts');
                $totalCompleted      = $stories->sum('unique_completed');
                $totalCompletionEvents = $stories->sum('completion_events');
                $totalAbandoned   = $stories->sum('abandoned');
                $totalReplayEvents = $stories->sum('replay_events');
                $totalReplayers    = $stories->sum('unique_replayers');
                $overallCompletion = $totalStarts > 0 ? round(($totalCompleted / $totalStarts) * 100, 1) : null;
            @endphp
            <div class="flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400 px-1">
                <span>{{ $stories->count() }} stories with gameplay data</span>
                <span class="text-gray-300 dark:text-gray-600">·</span>
                <span>{{ number_format($totalStarts) }} total starts</span>
                <span class="text-gray-300 dark:text-gray-600">·</span>
                <span>{{ number_format($totalCompleted) }} unique completed · {{ number_format($totalCompletionEvents) }} completion events</span>
                @if ($overallCompletion !== null)
                    <span class="text-gray-300 dark:text-gray-600">·</span>
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $overallCompletion }}% overall completion rate</span>
                @endif
                <span class="text-gray-300 dark:text-gray-600">·</span>
                <span>{{ number_format($totalAbandoned) }} abandoned · {{ number_format($totalReplayEvents) }} replay events · {{ number_format($totalReplayers) }} unique replayers</span>
            </div>

            {{-- Content progression: Started → Reached S{N} → Completed --}}
            @if ($progression->isNotEmpty())
                <div class="pt-4 space-y-4">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white px-1">Content Progression</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 px-1">How many distinct games reach each session. Immediately shows where a story loses players.</p>

                    @foreach ($progression as $prog)
                        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                                <span class="font-semibold text-gray-900 dark:text-white text-sm">{{ $prog->title }}</span>
                            </div>
                            <div class="px-4 py-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                @php $starts = (int) $prog->starts; @endphp
                                <div class="tabular-nums">
                                    <span class="text-gray-500 dark:text-gray-400">Started</span>
                                    <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ number_format($starts) }}</span>
                                </div>
                                @foreach ($prog->reached as $sessionNum => $reached)
                                    @php $pct = $starts > 0 ? round($reached / $starts * 100, 1) : null; @endphp
                                    <div class="tabular-nums">
                                        <span class="text-gray-500 dark:text-gray-400">Reached S{{ $sessionNum }}</span>
                                        <span class="ml-2 font-semibold text-gray-800 dark:text-gray-200">{{ number_format($reached) }}</span>
                                        @if ($pct !== null)
                                            <span class="ml-1 text-xs text-gray-400">({{ $pct }}%)</span>
                                        @endif
                                    </div>
                                @endforeach
                                @php $compPct = $starts > 0 ? round($prog->completions / $starts * 100, 1) : null; @endphp
                                <div class="tabular-nums">
                                    <span class="text-gray-500 dark:text-gray-400">Completed</span>
                                    <span class="ml-2 font-semibold text-green-600 dark:text-green-400">{{ number_format($prog->completions) }}</span>
                                    @if ($compPct !== null)
                                        <span class="ml-1 text-xs text-gray-400">({{ $compPct }}%)</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Session Funnel per story --}}
            @if ($funnels->isNotEmpty())
                <div class="pt-4 space-y-4">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white px-1">Session Funnel — per story</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 px-1">Aggregated across all story cycles. Each session row is unique to a (game, story_cycle_number, session_number) tuple — no data is overwritten on replay.</p>

                    @foreach ($stories as $story)
                        @php
                            $sessionRows = $funnels->get($story->id);
                            $dropOff     = $dropOffs[$story->id] ?? null;
                        @endphp
                        @if ($sessionRows && $sessionRows->isNotEmpty())
                            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">

                                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                                    <div>
                                        <span class="font-semibold text-gray-900 dark:text-white text-sm">{{ $story->title }}</span>
                                        @if ($story->completion_rate !== null)
                                            <span class="ml-2 text-xs text-gray-400 dark:text-gray-500">{{ $story->completion_rate }}% story completion</span>
                                        @endif
                                    </div>
                                    @if ($dropOff !== null)
                                        <span class="text-xs text-red-600 dark:text-red-400 font-medium">Biggest drop: Session {{ $dropOff }}</span>
                                    @endif
                                </div>

                                <table class="min-w-full divide-y divide-gray-100 dark:divide-white/5 text-sm">
                                    <thead>
                                        <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            <th class="px-4 py-2 text-left w-36">Session</th>
                                            <th class="px-4 py-2 text-right">Reached</th>
                                            <th class="px-4 py-2 text-right">Completed</th>
                                            <th class="px-4 py-2 text-right">Dropped</th>
                                            <th class="px-4 py-2 text-right">Completion %</th>
                                            <th class="px-4 py-2 text-right">Avg / Median</th>
                                            <th class="px-4 py-2 text-left pl-6">Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                        @foreach ($sessionRows as $session)
                                            @php
                                                $isDropOff = $dropOff !== null && (int) $session->session_number === $dropOff;
                                                $pct       = $session->completion_pct;
                                                $barWidth  = $pct !== null ? max(2, (int) $pct) : 0;
                                            @endphp
                                            <tr @class(['transition-colors', 'bg-red-50/60 dark:bg-red-950/20' => $isDropOff, 'hover:bg-gray-50 dark:hover:bg-white/5' => ! $isDropOff])>
                                                <td class="px-4 py-2.5 font-medium text-gray-700 dark:text-gray-300">
                                                    <div class="flex items-center gap-2">
                                                        Session {{ $session->session_number }}
                                                        @if ($isDropOff)
                                                            <span class="text-xs text-red-500 dark:text-red-400 font-semibold">↓ drop-off</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2.5 text-right tabular-nums text-gray-600 dark:text-gray-400">{{ number_format($session->reached) }}</td>
                                                <td class="px-4 py-2.5 text-right tabular-nums text-gray-600 dark:text-gray-400">{{ number_format($session->completed_cnt) }}</td>
                                                <td class="px-4 py-2.5 text-right tabular-nums">
                                                    @if ((int) $session->dropped > 0)
                                                        <span @class(['font-semibold', 'text-red-600 dark:text-red-400' => $isDropOff, 'text-gray-500 dark:text-gray-400' => ! $isDropOff])>−{{ number_format($session->dropped) }}</span>
                                                    @else
                                                        <span class="text-gray-400">0</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 text-right tabular-nums">
                                                    @if ($pct !== null)
                                                        <span @class(['font-semibold', 'text-green-600 dark:text-green-400' => $pct >= 70, 'text-yellow-600 dark:text-yellow-400' => $pct >= 40 && $pct < 70, 'text-red-600 dark:text-red-400' => $pct < 40])>{{ $pct }}%</span>
                                                    @else
                                                        <span class="text-gray-400">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                                    @if ($session->avg_minutes !== null || $session->median_minutes !== null)
                                                        @php
                                                            $fmtDur = function (?float $mins): string {
                                                                if ($mins === null) return '—';
                                                                $h = floor($mins / 60);
                                                                $m = round($mins % 60);
                                                                return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
                                                            };
                                                        @endphp
                                                        <span>{{ $fmtDur($session->avg_minutes !== null ? (float) $session->avg_minutes : null) }}</span>
                                                        <span class="text-gray-400 mx-1">/</span>
                                                        <span class="text-gray-600 dark:text-gray-300">{{ $fmtDur($session->median_minutes !== null ? (float) $session->median_minutes : null) }}</span>
                                                    @else
                                                        <span class="text-gray-400">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 pl-6">
                                                    @if ($pct !== null)
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-32 h-1.5 bg-gray-100 dark:bg-white/10 rounded-full overflow-hidden">
                                                                <div class="h-full rounded-full transition-all" style="width: {{ $barWidth }}%; background-color: {{ $pct >= 70 ? '#22c55e' : ($pct >= 40 ? '#eab308' : '#ef4444') }}"></div>
                                                            </div>
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
            @endif
        @endif

    </div>
</x-filament-panels::page>
