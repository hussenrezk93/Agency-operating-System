<?php

namespace App\Console\Commands;

use App\Services\PerformanceService;
use Illuminate\Console\Command;

/**
 * Phase 0 Technical Plan §7 — the PROVISIONAL snapshot for the in-progress month, kept
 * fresh daily so dashboards have a reasonably current number before the month closes.
 * `PerformanceService::calculate()`'s `asOf` cutoff (defaults to now()) is what keeps a
 * not-yet-due step out of the count rather than misreporting it as overdue.
 */
class PerformanceRefresh extends Command
{
    protected $signature = 'agencyos:performance-refresh';

    protected $description = 'Refresh the provisional performance snapshot for the current, in-progress month.';

    public function handle(PerformanceService $performance): int
    {
        $monthStart = now()->startOfMonth();

        $performance->snapshotAll($monthStart);

        $this->info("Performance snapshot refreshed for {$monthStart->format('Y-m')}.");

        return self::SUCCESS;
    }
}
