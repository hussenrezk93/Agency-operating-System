<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Agency OS requires MySQL (8.0.16+ — that's the version CHECK constraints started being
 * genuinely enforced, not just parsed). The schema's invariants are: plain `json`
 * columns, MySQL generated-column + regular-unique-index pairs standing in for what used
 * to be PostgreSQL partial unique indexes, and a pair of native triggers making
 * `audit_logs` append-only. One invariant has NO database-level equivalent on MySQL at
 * all — the "no two overlapping temporary Team Leader periods per department" rule — and
 * is enforced purely in application code instead, by
 * `TemporaryLeadershipService::assertNoTemporaryOverlap()` under a `lockForUpdate()`
 * transaction. That one is a real, accepted trade-off of the MySQL move, not an oversight.
 *
 * Failing loudly here is deliberate: a wrong-driver run must never look successful.
 */
class DatabaseGuardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole() && DB::getDriverName() !== 'mysql') {
            throw new RuntimeException(
                'Agency OS requires MySQL. Current driver: '.DB::getDriverName().'. '
                .'Set DB_CONNECTION=mysql in .env. '
                .'SQLite and PostgreSQL cannot enforce the approved database invariants.'
            );
        }
    }
}
