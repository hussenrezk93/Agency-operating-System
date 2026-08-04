<?php

namespace App\Jobs\Concerns;

use Illuminate\Support\Carbon;

/**
 * OPEN-DECISIONS.md §C's proposed retry policy — 5 attempts, exponential up to 1h.
 * A `null` return means "stop scheduling retries"; the caller is responsible for also
 * excluding attempt_count >= self::MAX_ATTEMPTS rows from its due-for-retry query, since
 * a `Failed` row with `next_attempt_at = null` still matches `scopeDueForRetry()`.
 */
trait ComputesEmailRetryBackoff
{
    private const MAX_ATTEMPTS = 5;

    private const BACKOFF_MINUTES = [1 => 1, 2 => 5, 3 => 15, 4 => 30, 5 => 60];

    private function nextRetryAt(int $attemptCountAfterThisFailure): ?Carbon
    {
        if ($attemptCountAfterThisFailure >= self::MAX_ATTEMPTS) {
            return null;
        }

        return now()->addMinutes(self::BACKOFF_MINUTES[$attemptCountAfterThisFailure] ?? 60);
    }
}
