<?php

declare(strict_types=1);

namespace App\Filament\Manager\Widgets\Analytics;

use App\Support\Analytics;
use App\Models\GameCompletion;
use App\Models\GameReset;
use App\Models\GameSessionCompletion;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Conversion funnel bar chart.
 *
 * All steps are bounded by the analytics baseline (2026-06-01).
 * Story completions use game_completions (append-only history).
 */
final class AnalyticsFunnelWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Conversion Funnel';

    protected ?string $description = 'Step-by-step user progression from first visit to story replay. All steps capped at Jun 1, 2026 baseline.';

    protected ?string $maxHeight = '340px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getFilters(): ?array
    {
        return [
            '7'   => 'Last 7 days',
            '30'  => 'Last 30 days',
            '90'  => 'Last 90 days',
            'all' => 'All time (since Jun 1)',
        ];
    }

    protected function getData(): array
    {
        [$from, $to] = $this->dateRange();

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

        $s1Completions = GameSessionCompletion::query()
            ->where('session_number', 1)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $from)
            ->where('completed_at', '<=', $to)
            ->count();

        $storyCompletions = (int) GameCompletion::query()
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
            'Visits'          => $visits,
            'Signups'         => $signups,
            'Story Starts'    => $starts,
            'Session 1 Done'  => $s1Completions,
            'Story Complete'  => $storyCompletions,
            'Replays'         => $replays,
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Players',
                    'data'  => array_values($steps),
                    'backgroundColor' => [
                        'rgba(107, 114, 128, 0.8)',
                        'rgba(147, 51, 234, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(234, 179, 8, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                    ],
                    'borderRadius' => 6,
                ],
            ],
            'labels' => array_keys($steps),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins'   => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => ['beginAtZero' => true, 'grid' => ['display' => true]],
                'y' => ['grid' => ['display' => false]],
            ],
        ];
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function dateRange(): array
    {
        $baseline = Analytics::baseline();
        $to       = Carbon::now()->endOfDay();

        $days = match ($this->filter) {
            '7'   => 7,
            '90'  => 90,
            'all' => null,
            default => 30,
        };

        $from = $days !== null
            ? Carbon::now()->subDays($days)->startOfDay()->max($baseline)
            : $baseline;

        return [$from, $to];
    }
}
