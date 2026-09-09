<?php

namespace App\Support\Database;

use Closure;
use Illuminate\Database\QueryException;
use Throwable;

final class WriteRetry
{
    /**
     * Retry sqlite "database is locked" / deadlock writes a few times.
     *
     * @template TReturn
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(Closure $callback, int $attempts = 5, int $sleepMs = 75): mixed
    {
        $tries = max(1, $attempts);
        $last = null;

        for ($i = 1; $i <= $tries; $i++) {
            try {
                return $callback();
            } catch (Throwable $e) {
                $last = $e;
                if (! self::isRetryable($e) || $i === $tries) {
                    throw $e;
                }

                usleep(($sleepMs * $i) * 1000);
            }
        }

        throw $last ?? new QueryException('sqlite', '', [], new \RuntimeException('Write retry failed.'));
    }

    private static function isRetryable(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'database is locked')
            || str_contains($message, 'database locked')
            || str_contains($message, 'deadlock')
            || str_contains($message, 'serialization failure');
    }
}
