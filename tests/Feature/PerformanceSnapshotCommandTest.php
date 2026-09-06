<?php

namespace Tests\Feature;

use App\Enums\SnapshotType;
use App\Enums\WorkflowStatus;
use App\Models\Department;
use App\Models\MonthlyPerformanceSnapshot;
use App\Models\TaskStep;
use App\Models\TaskStepAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** PHASE 9 — both scheduled commands populate every snapshot type and upsert in place. */
class PerformanceSnapshotCommandTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private User $leader;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        Carbon::setTestNow(Carbon::parse('2026-09-05 09:00:00', config('app.timezone')));

        $this->marketing = $this->makeDepartment('Marketing');
        $this->leader = $this->makeTeamLeader($this->marketing);
        $this->employee = $this->makeEmployee($this->marketing);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function stepDueOn(string $date, WorkflowStatus $status, ?string $submittedAt, User $assignee): TaskStep
    {
        $step = TaskStep::factory()->create([
            'department_id' => $this->marketing->id,
            'workflow_status' => $status->value,
            'current_due_at' => $date.' 23:59:00',
            'submitted_at' => $submittedAt,
            'approved_at' => $submittedAt,
        ]);

        TaskStepAssignment::create([
            'task_step_id' => $step->id,
            'assignee_id' => $assignee->id,
            'assigned_by' => $this->leader->id,
            'is_self_assigned' => false,
            'start_date' => $date,
            'due_date' => $date,
            'ended_at' => $status->isTerminal() ? now() : null,
        ]);

        return $step;
    }

    public function test_the_final_snapshot_targets_the_previous_month_and_writes_every_type(): void
    {
        $this->stepDueOn('2026-08-10', WorkflowStatus::Approved, '2026-08-09 10:00:00', $this->employee);

        $this->artisan('agencyos:performance-snapshot')->assertExitCode(0);

        $this->assertDatabaseHas('monthly_performance_snapshots', [
            'user_id' => $this->employee->id, 'snapshot_type' => SnapshotType::Employee->value,
            'month_start' => '2026-08-01', 'due_steps' => 1, 'on_time_steps' => 1,
        ]);
        $this->assertDatabaseHas('monthly_performance_snapshots', [
            'user_id' => $this->leader->id, 'snapshot_type' => SnapshotType::TlPersonal->value, 'month_start' => '2026-08-01',
        ]);
        $this->assertDatabaseHas('monthly_performance_snapshots', [
            'user_id' => $this->leader->id, 'snapshot_type' => SnapshotType::TlTeam->value,
            'month_start' => '2026-08-01', 'due_steps' => 1, 'on_time_steps' => 1,
        ]);
        $this->assertDatabaseHas('monthly_performance_snapshots', [
            'department_id' => $this->marketing->id, 'user_id' => null, 'snapshot_type' => SnapshotType::Department->value,
            'month_start' => '2026-08-01', 'due_steps' => 1, 'on_time_steps' => 1,
        ]);
    }

    public function test_the_refresh_targets_the_current_month(): void
    {
        $this->stepDueOn('2026-09-01', WorkflowStatus::Approved, '2026-08-31 10:00:00', $this->employee);

        $this->artisan('agencyos:performance-refresh')->assertExitCode(0);

        $this->assertDatabaseHas('monthly_performance_snapshots', [
            'user_id' => $this->employee->id, 'snapshot_type' => SnapshotType::Employee->value, 'month_start' => '2026-09-01',
        ]);
        $this->assertDatabaseMissing('monthly_performance_snapshots', [
            'user_id' => $this->employee->id, 'snapshot_type' => SnapshotType::Employee->value, 'month_start' => '2026-08-01',
        ]);
    }

    public function test_rerunning_the_snapshot_updates_in_place_without_duplicating(): void
    {
        $this->stepDueOn('2026-08-10', WorkflowStatus::Approved, '2026-08-09 10:00:00', $this->employee);

        $this->artisan('agencyos:performance-snapshot')->assertExitCode(0);
        $this->artisan('agencyos:performance-snapshot')->assertExitCode(0);

        $this->assertSame(1, MonthlyPerformanceSnapshot::where('user_id', $this->employee->id)
            ->where('snapshot_type', SnapshotType::Employee->value)
            ->where('month_start', '2026-08-01')
            ->count());
    }

    public function test_department_and_tl_team_snapshots_always_match(): void
    {
        $this->stepDueOn('2026-08-10', WorkflowStatus::Approved, '2026-08-09 10:00:00', $this->employee);
        $this->stepDueOn('2026-08-11', WorkflowStatus::Approved, '2026-08-12 10:00:00', $this->employee);

        $this->artisan('agencyos:performance-snapshot')->assertExitCode(0);

        $department = MonthlyPerformanceSnapshot::where('department_id', $this->marketing->id)
            ->where('snapshot_type', SnapshotType::Department->value)->where('month_start', '2026-08-01')->firstOrFail();
        $tlTeam = MonthlyPerformanceSnapshot::where('user_id', $this->leader->id)
            ->where('snapshot_type', SnapshotType::TlTeam->value)->where('month_start', '2026-08-01')->firstOrFail();

        $this->assertSame($department->due_steps, $tlTeam->due_steps);
        $this->assertSame($department->on_time_steps, $tlTeam->on_time_steps);
        $this->assertSame($department->overdue_steps, $tlTeam->overdue_steps);
        $this->assertEquals($department->score, $tlTeam->score);
    }
}
