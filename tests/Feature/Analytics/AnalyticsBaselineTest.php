<?php

declare(strict_types=1);

use App\Support\Analytics;
use Illuminate\Support\Carbon;
use Tests\Support\Analytics\AnalyticsTestLogger;

describe('analytics baseline config', function () {
    it('reads start date from config', function () {
        expect(Analytics::startDate())->toBe('2026-06-01');
        expect(Analytics::baseline()->toDateString())->toBe('2026-06-01');
    });

    it('computes abandoned cutoff from configured inactivity days', function () {
        Carbon::setTestNow('2026-06-20 12:00:00');

        expect(Analytics::abandonedInactivityDays())->toBe(14);
        expect(Analytics::abandonedCutoff()->toDateTimeString())
            ->toBe('2026-06-06 12:00:00');

        Carbon::setTestNow();
    });

    it('logs baseline snapshot to analytics test log', function () {
        $payload = [
            'start_date'                => Analytics::startDate(),
            'baseline_datetime'         => Analytics::baselineDateTimeString(),
            'abandoned_inactivity_days' => Analytics::abandonedInactivityDays(),
        ];

        AnalyticsTestLogger::log('analytics_baseline', $payload);

        expect(file_exists(AnalyticsTestLogger::logPath()))->toBeTrue();
    });
});
