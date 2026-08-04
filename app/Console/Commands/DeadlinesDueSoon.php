<?php

namespace App\Console\Commands;

use App\Enums\DeadlineStatus;
use App\Services\DeadlineService;
use Illuminate\Console\Command;

/**
 * Phase 0 Technical Plan §7 — fires the 24h-before-due reclassification.
 * Idempotent: DeadlineService only writes a row when its status actually changed, so
 * running this every 15 minutes never double-counts.
 */
class DeadlinesDueSoon extends Command
{
    protected $signature = 'agencyos:deadlines-due-soon';

    protected $description = 'Reclassify live task steps entering the due-soon window (Phase 0 §7).';

    public function handle(DeadlineService $deadline): int
    {
        $changed = $deadline->sweepLiveSteps();
        $dueSoon = $changed->filter(fn ($step) => $step->deadline_status === DeadlineStatus::DueSoon)->count();

        $this->info("Deadline sweep complete — {$dueSoon} step(s) entered due_soon.");

        return self::SUCCESS;
    }
}
