<?php

declare(strict_types=1);

namespace App\Filament\Manager\Widgets\Analytics;

use App\Support\Analytics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Retention KPI cards.
 *
 * Cohort anchor: first playable experience — MIN(game_session_completions.started_at)
 * per user, on or after the analytics baseline. Excludes users who created a
 * game but never received narration.
 *
 * D1: returned the next calendar day after first playable session.
 * D7: returned any day within 7 days of first playable session.
 * D30: returned any day within 30 days of first playable session.
 * Return Rate: % of users with more than one distinct activity day.
 */
final class AnalyticsRetentionWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Retention';

    protected ?string $description = 'Cohort: users whose first playable session (Chapter 1 start) is on or after Jun 1, 2026. D1/D7/D30 = returned within that many days of first play.';

    protected function getStats(): array
    {
        $retention  = $this->retentionStats();
        $returnRate = $this->returnRate();

        $cohortSize  = (int) ($retention->cohort_size ?? 0);
        $cohortLabel = 'Cohort: ' . number_format($cohortSize) . ' users (since Jun 1)';

        $fmt = static function (int $cohort, int $retained): string {
            if ($cohort === 0) {
                return 'N/A';
            }

            return round($retained / $cohort * 100, 1) . '%';
        };

        return [
            Stat::make('D1 Retention', $fmt($cohortSize, (int) ($retention->d1_retained ?? 0)))
                ->description($cohortLabel . ' · returned next day')
                ->color('primary'),

            Stat::make('D7 Retention', $fmt($cohortSize, (int) ($retention->d7_retained ?? 0)))
                ->description($cohortLabel . ' · returned within 7 days')
                ->color('info'),

            Stat::make('D30 Retention', $fmt($cohortSize, (int) ($retention->d30_retained ?? 0)))
                ->description($cohortLabel . ' · returned within 30 days')
                ->color('warning'),

            Stat::make('Return Rate', $returnRate)
                ->description('Users with more than one distinct active day (since Jun 1)')
                ->color('success'),
        ];
    }

    /**
     * Computes all three retention rates in a single pass.
     *
     * Cohort: users whose first session started_at is >= analytics baseline.
     */
    private function retentionStats(): object
    {
        $result = DB::select("
            SELECT
                COUNT(DISTINCT c.user_id)                                            AS cohort_size,
                COUNT(DISTINCT CASE WHEN d1.user_id  IS NOT NULL THEN c.user_id END) AS d1_retained,
                COUNT(DISTINCT CASE WHEN d7.user_id  IS NOT NULL THEN c.user_id END) AS d7_retained,
                COUNT(DISTINCT CASE WHEN d30.user_id IS NOT NULL THEN c.user_id END) AS d30_retained
            FROM (
                SELECT gsc.user_id, MIN(gsc.started_at)::date AS first_play
                FROM game_session_completions gsc
                JOIN games g ON g.id = gsc.game_id
                WHERE g.is_preview = false
                  AND gsc.started_at IS NOT NULL
                  AND gsc.started_at >= ?
                GROUP BY gsc.user_id
            ) c
            LEFT JOIN user_activity_days d1
                ON  d1.user_id       = c.user_id
                AND d1.activity_date = c.first_play + INTERVAL '1 day'
            LEFT JOIN user_activity_days d7
                ON  d7.user_id       = c.user_id
                AND d7.activity_date BETWEEN c.first_play + INTERVAL '1 day'
                                         AND c.first_play + INTERVAL '7 days'
            LEFT JOIN user_activity_days d30
                ON  d30.user_id       = c.user_id
                AND d30.activity_date BETWEEN c.first_play + INTERVAL '1 day'
                                          AND c.first_play + INTERVAL '30 days'
        ", [Analytics::baselineDateTimeString()]);

        return $result[0] ?? (object) [
            'cohort_size'  => 0,
            'd1_retained'  => 0,
            'd7_retained'  => 0,
            'd30_retained' => 0,
        ];
    }

    /**
     * Return Rate: % of cohort users with more than one distinct activity day.
     * Cohort = users with at least one started session on or after baseline.
     */
    private function returnRate(): string
    {
        $row = DB::select("
            SELECT
                COUNT(DISTINCT c.user_id) AS total_users,
                COUNT(DISTINCT CASE WHEN a.day_count > 1 THEN c.user_id END) AS returners
            FROM (
                SELECT gsc.user_id
                FROM game_session_completions gsc
                JOIN games g ON g.id = gsc.game_id
                WHERE g.is_preview = false
                  AND gsc.started_at IS NOT NULL
                  AND gsc.started_at >= ?
                GROUP BY gsc.user_id
            ) c
            LEFT JOIN (
                SELECT user_id, COUNT(DISTINCT activity_date) AS day_count
                FROM user_activity_days
                GROUP BY user_id
            ) a ON a.user_id = c.user_id
        ", [Analytics::baselineDateTimeString()]);

        $data = $row[0] ?? null;

        if (! $data || (int) $data->total_users === 0) {
            return 'N/A';
        }

        return round((int) $data->returners / (int) $data->total_users * 100, 1) . '%';
    }
}
