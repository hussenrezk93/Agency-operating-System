<?php

namespace App\Console\Commands;

use App\Enums\DeadlineStatus;
use App\Services\DeadlineService;
use Illuminate\Console\Command;

/**
 * Phase 0 Technical Plan §7 — fires the past-due reclassification. Shares
 * DeadlineService::sweepLiveSteps() with `agencyos:deadlines-due-soon` rather than a
 * second copy of the classification logic; each command just reports the slice of the
 * same idempotent sweep it's named for.
 */
class DeadlinesOverdue extends Command
{
    protected $signature = 'agencyos:deadlines-overdue';

    protected $description = 'Reclassify live task steps that are now overdue (Phase 0 §7).';

    public function handle(DeadlineService $deadline): int
    {
        $changed = $deadline->sweepLiveSteps();
        $overdue = $changed->filter(fn ($step) => $step->deadline_status === DeadlineStatus::Overdue)->count();

        $this->info("Deadline sweep complete — {$overdue} step(s) became overdue.");

        return self::SUCCESS;
    }
}
