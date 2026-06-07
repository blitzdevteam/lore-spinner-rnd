<?php

declare(strict_types=1);

namespace App\Filament\Manager\Pages;

use App\Filament\Manager\Widgets\Analytics\AnalyticsKpiWidget;
use App\Filament\Manager\Widgets\Analytics\AnalyticsFunnelWidget;
use App\Filament\Manager\Widgets\Analytics\AnalyticsRetentionWidget;
use App\Models\GameCompletion;
use App\Models\GameReset;
use App\Models\GameSessionCompletion;
use App\Models\User;
use App\Support\Analytics;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    /**
     * Funnel steps: Visits → Signups → Starts → Ch.1 Done → Completions → Replays.
     * Each step carries a conversion % relative to the previous step and to Visits.
     *
     * @return array<int, array{label: string, value: int, conv_prev: string|null, conv_top: string|null, color: string}>
     */
    public function getFunnelData(): array
    {
        try {
            $from = Analytics::baseline();
            $to   = Carbon::now()->endOfDay();

            $visits = DB::table('page_views')
                ->where('view_date', '>=', $from->toDateString())
                ->where('view_date', '<=', $to->toDateString())
                ->distinct('session_id')
                ->count('session_id');

            $signups = User::query()
                ->where('created_at', '>=', $from)
                ->where('created_at', '<=', $to)
                ->count();

            $starts = DB::table('games')
                ->where('is_preview', false)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<=', $to)
                ->count();

            $ch1done = GameSessionCompletion::query()
                ->where('session_number', 1)
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', $from)
                ->where('completed_at', '<=', $to)
                ->count();

            $completions = (int) GameCompletion::query()
                ->where('completed_at', '>=', $from)
                ->where('completed_at', '<=', $to)
                ->distinct('game_id')
                ->count('game_id');

            $replays = GameReset::query()
                ->where('had_prior_completion', true)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<=', $to)
                ->count();

            $steps = [
                ['label' => 'Visits',           'value' => $visits,      'color' => '#6366f1'],
                ['label' => 'Signups',           'value' => $signups,     'color' => '#8b5cf6'],
                ['label' => 'Story Starts',      'value' => $starts,      'color' => '#3b82f6'],
                ['label' => 'Ch. 1 Completed',   'value' => $ch1done,     'color' => '#f59e0b'],
                ['label' => 'Story Completions', 'value' => $completions, 'color' => '#22c55e'],
                ['label' => 'Replays',           'value' => $replays,     'color' => '#ec4899'],
            ];

            $top = max($visits, 1);
            foreach ($steps as $i => &$step) {
                $prev              = $i > 0 ? $steps[$i - 1]['value'] : null;
                $step['pct_top']   = $top > 0 ? round($step['value'] / $top * 100, 1) : 0.0;
                $step['pct_prev']  = ($prev !== null && $prev > 0) ? round($step['value'] / $prev * 100, 1) : null;
                $step['bar_width'] = max(4, (int) round($step['pct_top']));
            }
            unset($step);

            return $steps;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Top 5 stories by starts, with bar width relative to the leader.
     *
     * @return array<int, array{title: string, starts: int, pct: int}>
     */
    public function getTopStories(): array
    {
        try {
            $rows = DB::select("
                SELECT s.title, COUNT(g.id) AS starts
                FROM stories s
                JOIN games g ON g.story_id = s.id
                WHERE g.is_preview = false
                  AND g.created_at >= ?
                GROUP BY s.id, s.title
                ORDER BY starts DESC
                LIMIT 5
            ", [Analytics::baselineDateTimeString()]);

            if (empty($rows)) {
                return [];
            }

            $max = max(array_column((array) $rows, 'starts')) ?: 1;

            return array_map(fn ($r) => [
                'title'  => $r->title,
                'starts' => (int) $r->starts,
                'pct'    => max(4, (int) round($r->starts / $max * 100)),
            ], $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * 3–4 computed insight strings for the dashboard callout panel.
     *
     * @return array<int, array{icon: string, text: string, highlight: string, color: string}>
     */
    public function getKeyInsights(): array
    {
        try {
            $from = Analytics::baseline();
            $to   = Carbon::now()->endOfDay();

            $starts      = DB::table('games')->where('is_preview', false)->where('created_at', '>=', $from)->count();
            $completions = (int) GameCompletion::query()->where('completed_at', '>=', $from)->distinct('game_id')->count('game_id');
            $signups     = User::query()->where('created_at', '>=', $from)->where('created_at', '<=', $to)->count();
            $stories     = DB::table('games')->where('is_preview', false)->where('created_at', '>=', $from)->distinct('story_id')->count('story_id');
            $abandoned   = (int) (DB::select("
                SELECT COUNT(*) AS cnt FROM (
                    SELECT g.id, GREATEST(g.created_at, MAX(p.created_at), MAX(gsc.started_at), MAX(gsc.completed_at), MAX(gr.created_at)) AS last_active_at
                    FROM games g
                    LEFT JOIN prompts p ON p.game_id = g.id
                    LEFT JOIN game_session_completions gsc ON gsc.game_id = g.id
                    LEFT JOIN game_resets gr ON gr.game_id = g.id
                    WHERE g.is_preview = false AND g.completed_at IS NULL AND g.created_at >= ?
                    GROUP BY g.id, g.created_at
                ) sub WHERE last_active_at < ?
            ", [$from->toDateTimeString(), Analytics::abandonedCutoff()->toDateTimeString()])[0]->cnt ?? 0);

            $insights = [];

            // Signup insight
            $insights[] = [
                'icon'      => '👥',
                'text'      => "signed up since the baseline across",
                'highlight' => number_format($signups) . ' users',
                'suffix'    => null,
                'color'     => '#8b5cf6',
            ];

            // Start insight
            $perStory = $stories > 0 ? round($starts / $stories, 1) : 0;
            $insights[] = [
                'icon'      => '▶',
                'text'      => "game starts across {$stories} " . ($stories === 1 ? 'story' : 'stories') . " ({$perStory} avg per story)",
                'highlight' => number_format($starts),
                'suffix'    => null,
                'color'     => '#3b82f6',
            ];

            // Completion insight
            if ($starts > 0 && $completions === 0) {
                $insights[] = [
                    'icon'      => '📖',
                    'text'      => "users are mid-story — no completions yet. Normal for a new launch.",
                    'highlight' => number_format($starts),
                    'suffix'    => null,
                    'color'     => '#f59e0b',
                ];
            } elseif ($starts > 0) {
                $rate       = round($completions / $starts * 100, 1);
                $insights[] = [
                    'icon'      => '✅',
                    'text'      => "completion rate — {$completions} unique games finished",
                    'highlight' => "{$rate}%",
                    'suffix'    => null,
                    'color'     => '#22c55e',
                ];
            }

            // Abandoned insight
            if ($abandoned === 0 && $starts > 0) {
                $insights[] = [
                    'icon'      => '🟢',
                    'text'      => "abandoned games — all starts are still within the 14-day activity window.",
                    'highlight' => 'Zero',
                    'suffix'    => null,
                    'color'     => '#22c55e',
                ];
            } elseif ($abandoned > 0) {
                $insights[] = [
                    'icon'      => '⚠️',
                    'text'      => "games abandoned (no activity for 14+ days)",
                    'highlight' => number_format($abandoned),
                    'suffix'    => null,
                    'color'     => '#ef4444',
                ];
            }

            return $insights;
        } catch (\Throwable) {
            return [];
        }
    }
}
