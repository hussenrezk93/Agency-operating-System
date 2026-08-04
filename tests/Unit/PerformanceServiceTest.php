<?php

namespace Tests\Unit;

use App\Enums\RoleCode;
use App\Enums\WorkflowStatus;
use App\Models\Department;
use App\Models\TaskStep;
use App\Models\TaskStepAssignment;
use App\Models\User;
use App\Services\PerformanceService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * PHASE 9 — the BRD §17 score calculation, resolved per the plan's stated rules:
 * on-time = approved_at <= current_due_at; Cancelled/Redirected excluded; a step not
 * yet due within the month isn't counted yet; N/A (not 0) when nothing was due.
 */
class PerformanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceService $service;

    private Department $marketing;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Pins "now" safely after every fixture date below, so calculateForUser()'s
        // default `asOf` (real now()) never accidentally excludes a step as "not yet due".
        Carbon::setTestNow(Carbon::parse('2026-09-05 09:00:00', config('app.timezone')));

        $this->service = app(PerformanceService::class);
        $this->marketing = Department::factory()->create();
        $this->employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function stepDueOn(string $date, WorkflowStatus $status, ?string $approvedAt = null, ?User $assignee = null): TaskStep
    {
        $step = TaskStep::factory()->create([
            'department_id' => $this->marketing->id,
            'workflow_status' => $status->value,
            'current_due_at' => $date.' 23:59:00',
            'approved_at' => $approvedAt,
        ]);

        TaskStepAssignment::create([
            'task_step_id' => $step->id,
            'assignee_id' => ($assignee ?? $this->employee)->id,
            'assigned_by' => $this->employee->id,
            'is_self_assigned' => false,
            'start_date' => $date,
            'due_date' => $date,
            'ended_at' => $status->isTerminal() ? now() : null,
        ]);

        return $step->fresh();
    }

    public function test_a_step_approved_before_its_deadline_counts_as_on_time(): void
    {
        $this->stepDueOn('2026-08-15', WorkflowStatus::Approved, '2026-08-14 10:00:00');

        $stats = $this->service->calculateForUser($this->employee, Carbon::parse('2026-08-01'));

        $this->assertSame(1, $stats['due']);
        $this->assertSame(1, $stats['on_time']);
        $this->assertSame(0, $stats['overdue']);
        $this->assertSame(100.0, $stats['score']);
    }

    public function test_a_step_approved_after_its_deadline_counts_as_late(): void
    {
        $this->stepDueOn('2026-08-15', WorkflowStatus::Approved, '2026-08-16 10:00:00');

        $stats = $this->service->calculateForUser($this->employee, Carbon::parse('2026-08-01'));

        $this->assertSame(1, $stats['due']);
        $this->assertSame(0, $stats['on_time']);
        $this->assertSame(1, $stats['overdue']);
    }

    public function test_a_step_never_approved_after_its_deadline_passed_counts_as_late(): void
    {
        $this->stepDueOn('2026-08-15', WorkflowStatus::InProgress);

        $stats = $this->service->calculateForUser($this->employee, Carbon::parse('2026-08-01'), Carbon::parse('2026-09-01'));

        $this->assertSame(1, $stats['due']);
        $this->assertSame(0, $stats['on_time']);
        $this->assertSame(1, $stats['overdue']);
    }

    public function test_cancelled_steps_are_excluded_from_due_steps(): void
    {
        $this->stepDueOn('2026-08-15', WorkflowStatus::Cancelled);

        $stats = $this->service->calculateForUser($this->employee, Carbon::parse('2026-08-01'));

        $this->assertSame(0, $stats['due']);
        $this->assertNull($stats['score']);
    }

    public function test_redirected_steps_are_excluded_from_due_steps(): void
    {
        $this->stepDueOn('2026-08-15', WorkflowStatus::Redirected);

        $stats = $this->service->calculateForUser($this->employee, Carbon::parse('2026-08-01'));

        $this->assertSame(0, $stats['due']);
    }

    public function test_a_step_not_yet_due_within_the_current_month_is_not_counted_yet(): void
    {
        $this->stepDueOn('2026-08-20', WorkflowStatus::InProgress);

        $stats = $this->service->calculateForUser($this->employee, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-10'));

        $this->assertSame(0, $stats['due']);
    }

    public function test_a_step_due_in_a_different_month_is_not_counted(): void
    {
        $this->stepDueOn('2026-09-01', WorkflowStatus::Approved, '2026-08-31 10:00:00');

        $stats = $this->service->calculateForUser($this->employee, Carbon::parse('2026-08-01'));

        $this->assertSame(0, $stats['due']);
    }

    public function test_no_due_steps_scores_n_a_not_zero(): void
    {
        $stats = $this->service->calculateForUser($this->employee, Carbon::parse('2026-08-01'));

        $this->assertSame(0, $stats['due']);
        $this->assertNull($stats['score']);
    }

    /**
     * The due date already reflects any hold extension (Phase 6 writes it directly to
     * `current_due_at`) — approval within the extended window still counts on time,
     * proving the calculator needs no hold-awareness of its own.
     */
    public function test_a_held_then_resumed_steps_extended_due_date_is_respected(): void
    {
        $this->stepDueOn('2026-08-20', WorkflowStatus::Approved, '2026-08-19 10:00:00');

        $stats = $this->service->calculateForUser($this->employee, Carbon::parse('2026-08-01'));

        $this->assertSame(1, $stats['on_time']);
    }

    public function test_department_calculation_counts_every_step_regardless_of_assignee(): void
    {
        $otherEmployee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();
        $this->stepDueOn('2026-08-15', WorkflowStatus::Approved, '2026-08-14 10:00:00', $this->employee);
        $this->stepDueOn('2026-08-16', WorkflowStatus::Approved, '2026-08-17 10:00:00', $otherEmployee);

        $stats = $this->service->calculateForDepartment($this->marketing, Carbon::parse('2026-08-01'));

        $this->assertSame(2, $stats['due']);
        $this->assertSame(1, $stats['on_time']);
        $this->assertSame(1, $stats['overdue']);
    }
}
