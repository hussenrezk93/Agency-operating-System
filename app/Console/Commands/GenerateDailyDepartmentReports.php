<?php

namespace App\Console\Commands;

use App\Services\DepartmentReportService;
use Illuminate\Console\Command;

/** Daily Department Reports — scheduled dailyAt('15:00') in routes/console.php. */
class GenerateDailyDepartmentReports extends Command
{
    protected $signature = 'agencyos:generate-daily-department-reports';

    protected $description = 'Generate today\'s department report(s) and notify each department\'s effective Team Leader.';

    public function handle(DepartmentReportService $reports): int
    {
        $reports->generateForToday();

        $this->info('Daily department reports generated.');

        return self::SUCCESS;
    }
}
