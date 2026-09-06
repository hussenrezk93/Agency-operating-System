<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

class GenerateDailyDepartmentReportsCommandTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    public function test_the_command_generates_todays_reports(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);

        $this->artisan('agencyos:generate-daily-department-reports')->assertExitCode(0);

        $this->assertDatabaseHas('department_daily_reports', [
            'department_id' => $department->id,
            'report_date' => now()->toDateString(),
            'type' => 'summary',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $leader->id,
            'type' => 'department_report.ready',
        ]);
    }
}
