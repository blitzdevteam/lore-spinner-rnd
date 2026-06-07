<?php

declare(strict_types=1);

namespace Tests\Support\Analytics;

use Illuminate\Support\Facades\File;

/**
 * Appends human-readable analytics test output to a log file.
 * Results persist after cleanup so you can review metric snapshots.
 */
final class AnalyticsTestLogger
{
    private static string $logPath = 'logs/analytics-tests.log';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function log(string $testName, array $data): void
    {
        $path = storage_path(self::$logPath);
        File::ensureDirectoryExists(dirname($path));

        $entry = sprintf(
            "[%s] %s\n%s\n%s\n",
            now()->toIso8601String(),
            $testName,
            str_repeat('-', 72),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        File::append($path, $entry);
    }

    public static function logPath(): string
    {
        return storage_path(self::$logPath);
    }

    public static function clear(): void
    {
        $path = storage_path(self::$logPath);

        if (File::exists($path)) {
            File::delete($path);
        }
    }
}
