<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Agency OS requires PostgreSQL. The schema depends on jsonb, partial unique indexes, an
 * EXCLUDE USING gist constraint for temporary-TL periods, and an append-only trigger on
 * audit_logs. On any other driver those protections are silently skipped, which would
 * produce a database that looks migrated but enforces none of the approved invariants —
 * and a test suite that passes for the wrong reason.
 *
 * Failing loudly here is deliberate: a wrong-driver run must never look successful.
 */
class DatabaseGuardServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole() && DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException(
                'Agency OS requires PostgreSQL. Current driver: '.DB::getDriverName().'. '
                .'Set DB_CONNECTION=pgsql in .env (see README-BACKEND.md §3–§4). '
                .'SQLite and MySQL cannot enforce the approved database invariants.'
            );
        }
    }
}
