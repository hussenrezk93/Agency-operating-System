<?php

namespace App\Console\Commands;

use App\Services\PerformanceService;
use Illuminate\Console\Command;

/**
 * Phase 0 Technical Plan §7 — the FINAL monthly snapshot, for the month that just
 * closed. Every due date in that month has already passed by the time this runs, so
 * `PerformanceService`'s `asOf` cutoff is a no-op here — this is the authoritative
 * number, never recomputed afterward (BRD §17 — a stored snapshot, not a live query).
 */
class PerformanceSnapshot extends Command
{
    protected $signature = 'agencyos:performance-snapshot';

    protected $description = 'Build the final monthly performance snapshot for the month that just closed.';

    public function handle(PerformanceService $performance): int
    {
        $monthStart = now()->subMonth()->startOfMonth();

        $performance->snapshotAll($monthStart);

        $this->info("Performance snapshot built for {$monthStart->format('Y-m')}.");

        return self::SUCCESS;
    }
}
