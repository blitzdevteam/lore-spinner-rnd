<?php

declare(strict_types=1);

namespace App\Support\WriterLab;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Tiny wrapper around the `writer-lab` log channel.
 *
 * Every controller / agent in the Writer Lab routes its lifecycle events here
 * so a writer-reported bug can be reproduced by grepping the channel.
 *
 * Convention:
 *  - event names use dot.notation: 'playground.turn.start', 'combine.end', etc.
 *  - payloads are flat arrays of primitives (ints, strings, bools, short arrays)
 *  - the writer_id is auto-attached when the 'writer' guard is authenticated
 */
final class WriterLabLog
{
    public static function channel(): LoggerInterface
    {
        return Log::channel('writer-lab');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $event, array $context = []): void
    {
        self::channel()->info($event, self::enrich($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function debug(string $event, array $context = []): void
    {
        self::channel()->debug($event, self::enrich($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $event, array $context = []): void
    {
        self::channel()->warning($event, self::enrich($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $event, array $context = [], ?Throwable $e = null): void
    {
        if ($e !== null) {
            $context['error_class']   = $e::class;
            $context['error_message'] = $e->getMessage();
            $context['error_file']    = $e->getFile() . ':' . $e->getLine();
        }
        self::channel()->error($event, self::enrich($context));
    }

    /**
     * Convenience wrapper that times a closure and logs start/end with status.
     *
     * @template T
     * @param  array<string, mixed>  $context
     * @param  callable(): T  $closure
     * @return T
     */
    public static function track(string $event, array $context, callable $closure): mixed
    {
        $start = microtime(true);
        self::info($event . '.start', $context);

        try {
            $result = $closure();
            self::info($event . '.end', $context + [
                'status'      => 'ok',
                'duration_ms' => self::elapsed($start),
            ]);
            return $result;
        } catch (Throwable $e) {
            self::error($event . '.end', $context + [
                'status'      => 'error',
                'duration_ms' => self::elapsed($start),
            ], $e);
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function enrich(array $context): array
    {
        $writerId = Auth::guard('writer')->id();
        if ($writerId !== null && ! array_key_exists('writer_id', $context)) {
            $context['writer_id'] = $writerId;
        }
        return $context;
    }

    private static function elapsed(float $startMicrotime): int
    {
        return (int) ((microtime(true) - $startMicrotime) * 1000);
    }
}
