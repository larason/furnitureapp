<?php

namespace App\Support;

use App\Exceptions\Api\ApiException;
use Closure;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;
use PDOException;

/**
 * Runs a database transaction with a short, bounded retry for transient
 * concurrency conflicts (deadlock, lock wait, MariaDB ER_RECORD_CHANGED).
 *
 * Retried work must be rollback-safe; no external side effects belong inside.
 */
final class ConcurrentTransaction
{
    private const MAX_ATTEMPTS = 5;

    private const TRANSIENT_DRIVER_CODES = [1020, 1205, 1213];

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(Closure $callback): mixed
    {
        if (DB::transactionLevel() > 0) {
            return $callback();
        }

        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return DB::transaction($callback);
            } catch (ApiException $exception) {
                throw $exception;
            } catch (PDOException $exception) {
                self::handle($exception, $attempt);
            }
        }
    }

    private static function handle(PDOException $exception, int $attempt): void
    {
        if (! self::isTransient($exception)) {
            throw $exception;
        }

        if ($attempt >= self::MAX_ATTEMPTS) {
            throw new ApiException(ApiErrorCode::CONFLICT, 'The request could not be completed due to concurrent activity.', 409);
        }

        usleep(random_int(2_000, 15_000));
    }

    private static function isTransient(PDOException $exception): bool
    {
        if ($exception instanceof DeadlockException) {
            return true;
        }

        $state = $exception->errorInfo[0] ?? null;
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $state === '40001' || in_array($driverCode, self::TRANSIENT_DRIVER_CODES, true);
    }
}
