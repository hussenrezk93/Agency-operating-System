<?php

namespace App\Console\Commands;

use App\Services\TemporaryLeadershipService;
use Illuminate\Console\Command;

/**
 * APPROVED DECISION Q13 — scheduled activation/expiry of temporary-TL periods.
 * Idempotent: safe to run repeatedly, and safe to miss a day (it catches up).
 * Schedule daily just after midnight Africa/Cairo:
 *   Schedule::command('agencyos:process-leadership-transitions')->dailyAt('00:05');
 */
class ProcessLeadershipTransitions extends Command
{
    protected $signature = 'agencyos:process-leadership-transitions';

    protected $description = 'Activate and end scheduled temporary Team Leader assignments (Q13).';

    public function handle(TemporaryLeadershipService $service): int
    {
        $result = $service->processScheduledTransitions();

        $this->info(sprintf(
            'Leadership transitions processed — activated: %d, ended: %d.',
            $result['activated'],
            $result['ended'],
        ));

        return self::SUCCESS;
    }
}
