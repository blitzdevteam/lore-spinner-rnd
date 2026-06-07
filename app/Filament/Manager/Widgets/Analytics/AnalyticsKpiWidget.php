<?php

declare(strict_types=1);

namespace App\Filament\Manager\Widgets\Analytics;

use App\Support\Analytics;
use App\Models\GameCompletion;
use App\Models\GameReset;
use App\Models\GameSessionCompletion;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Platform KPI cards.
 *
 * All metrics are bounded by the analytics baseline (2026-06-01). No data
 * before that date is included regardless of the date filter selected.
 *
 * Story Completions come from game_completions (append-only history).
 * games.completed_at is current/mutable state and is NOT used here.
 *
 * Abandoned = incomplete game with no gameplay activity for ≥ 14 days.
 * Incomplete = started but not yet completed (may still be in-progress).
 * These are two separate metrics with different business meanings.
 */
final class AnalyticsKpiWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Platform Metrics';

    protected function getStats(): array
    {
        [$from, $to] = $this->dateRange();

        $visits     = $this->visits($from, $to);
        $signups    = $this->signups($from, $to);
        $starts     = $this->storyStarts($from, $to);
        $s1         = $this->firstSessionCompletions($from, $to);
        $completionEvents = $this->completionEvents($from, $to);
        $uniqueCompleted  = $this->uniqueGamesCompleted($from, $to);
        $incomplete       = $this->incompleteStories($from, $to);
        $abandoned        = $this->abandonedStories($from, $to);
        $returns          = $this->returns($from, $to);
        $replayEvents     = $this->replayEvents($from, $to);
        $uniqueReplayers  = $this->uniqueReplayers($from, $to);

        $incompletePct = $starts > 0
            ? number_format(($incomplete / $starts) * 100, 1)
            : '0.0';

        $abandonedPct = $starts > 0
            ? number_format(($abandoned / $starts) * 100, 1)
            : '0.0';

        return [
            Stat::make('Visits', number_format($visits))
                ->description('Unique sessions on public pages')
                ->color('gray'),

            Stat::make('Signups', number_format($signups))
                ->description('New accounts created')
                ->color('primary'),

            Stat::make('Story Starts', number_format($starts))
                ->description('New games created (excl. previews)')
                ->color('info'),

            Stat::make('Session 1 Completions', number_format($s1))
                ->description('Players who finished and advanced past Session 1')
                ->color('warning'),

            Stat::make('Completion Events', number_format($completionEvents))
                ->description(number_format($uniqueCompleted) . ' unique games completed')
                ->color('success'),

            Stat::make('Incomplete Stories', number_format($incomplete))
                ->description("Started but not yet completed — {$incompletePct}% of starts")
                ->color('warning'),

            Stat::make('Abandoned Stories', number_format($abandoned))
                ->description("Incomplete with no activity for 14+ days — {$abandonedPct}% of starts")
                ->color('danger'),

            Stat::make('Returns', number_format($returns))
                ->description('Active players returning after a prior session')
                ->color('primary'),

            Stat::make('Replay Events', number_format($replayEvents))
                ->description(number_format($uniqueReplayers) . ' unique players replayed')
                ->color('danger'),
        ];
    }

    /**
     * Returns [baseline, now] — StatsOverviewWidget does not support getFilters()
     * in Filament 4, so this widget always shows all-time data since the baseline.
     *
     * @return array{Carbon, Carbon}
     */
    private function dateRange(): array
    {
        return [Analytics::baseline(), Carbon::now()->endOfDay()];
    }

    private function visits(Carbon $from, Carbon $to): int
    {
        return DB::table('page_views')
            ->where('view_date', '>=', $from->toDateString())
            ->where('view_date', '<=', $to->toDateString())
            ->distinct('session_id')
            ->count('session_id');
    }

    private function signups(Carbon $from, Carbon $to): int
    {
        return User::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->count();
    }

    private function storyStarts(Carbon $from, Carbon $to): int
    {
        return DB::table('games')
            ->where('is_preview', false)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->count();
    }

    private function firstSessionCompletions(Carbon $from, Carbon $to): int
    {
        return GameSessionCompletion::query()
            ->where('session_number', 1)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $from)
            ->where('completed_at', '<=', $to)
            ->count();
    }

    /** Total completion events — one per completed story cycle (can exceed starts). */
    private function completionEvents(Carbon $from, Carbon $to): int
    {
        return GameCompletion::query()
            ->where('completed_at', '>=', $from)
            ->where('completed_at', '<=', $to)
            ->count();
    }

    /** Distinct games that completed at least once in the period. Used for completion rate. */
    private function uniqueGamesCompleted(Carbon $from, Carbon $to): int
    {
        return (int) GameCompletion::query()
            ->where('completed_at', '>=', $from)
            ->where('completed_at', '<=', $to)
            ->distinct('game_id')
            ->count('game_id');
    }

    /**
     * Games created since the baseline (within the period) that are still
     * incomplete. A player may still be actively playing — this is current
     * state, not a permanent label.
     */
    private function incompleteStories(Carbon $from, Carbon $to): int
    {
        return DB::table('games')
            ->where('is_preview', false)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->whereNull('completed_at')
            ->count();
    }

    /**
     * Incomplete games whose latest gameplay activity is older than 14 days.
     *
     * Activity sources (all gameplay, no page views):
     *   - games.created_at
     *   - prompts.created_at
     *   - game_session_completions.started_at / completed_at
     *   - game_resets.created_at
     */
    private function abandonedStories(Carbon $from, Carbon $to): int
    {
        $cutoff = Analytics::abandonedCutoff()->toDateTimeString();

        $result = DB::select("
            SELECT COUNT(*) AS cnt
            FROM (
                SELECT g.id,
                    GREATEST(
                        g.created_at,
                        MAX(p.created_at),
                        MAX(gsc.started_at),
                        MAX(gsc.completed_at),
                        MAX(gr.created_at)
                    ) AS last_active_at
                FROM games g
                LEFT JOIN prompts p ON p.game_id = g.id
                LEFT JOIN game_session_completions gsc ON gsc.game_id = g.id
                LEFT JOIN game_resets gr ON gr.game_id = g.id
                WHERE g.is_preview = false
                  AND g.completed_at IS NULL
                  AND g.created_at >= ?
                  AND g.created_at <= ?
                GROUP BY g.id, g.created_at
            ) sub
            WHERE last_active_at < ?
        ", [$from->toDateTimeString(), $to->toDateTimeString(), $cutoff]);

        return (int) ($result[0]->cnt ?? 0);
    }

    private function returns(Carbon $from, Carbon $to): int
    {
        return (int) DB::table('user_activity_days as a')
            ->where('a.activity_date', '>=', $from->toDateString())
            ->where('a.activity_date', '<=', $to->toDateString())
            ->whereExists(function ($q) use ($from): void {
                $q->from('user_activity_days as b')
                    ->whereColumn('b.user_id', 'a.user_id')
                    ->where('b.activity_date', '<', $from->toDateString());
            })
            ->distinct('a.user_id')
            ->count('a.user_id');
    }

    /** Total replay events — resets after a prior completion. */
    private function replayEvents(Carbon $from, Carbon $to): int
    {
        return GameReset::query()
            ->where('had_prior_completion', true)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->count();
    }

    /** Distinct players who replayed at least once in the period. */
    private function uniqueReplayers(Carbon $from, Carbon $to): int
    {
        return (int) GameReset::query()
            ->where('had_prior_completion', true)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->distinct('user_id')
            ->count('user_id');
    }
}
