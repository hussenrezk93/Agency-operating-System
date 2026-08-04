<?php

namespace Tests\Unit;

use App\Enums\DeadlineStatus;
use App\Models\Department;
use App\Models\User;
use App\Services\DeadlineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * `classify()` is pure — no database, only the Phase 0 trigger table's three boundaries.
 * `recomputeStep()`'s skip conditions need real Task/TaskStep rows, so this file (unlike
 * WorkflowStatusTransitionTest) uses the full Laravel TestCase + RefreshDatabase.
 */
class DeadlineServiceTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private DeadlineService $deadline;

    private Department $marketing;

    private User $manager;

    private User $leader;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->deadline = app(DeadlineService::class);
        $this->marketing = $this->makeDepartment();
        $this->manager = $this->makeManager();
        $this->leader = $this->makeTeamLeader($this->marketing);
        $this->employee = $this->makeEmployee($this->marketing);
    }

    // ------------------------------------------------------------------ classify()

    public function test_classify_just_under_24_hours_before_due_is_on_time(): void
    {
        $due = Carbon::parse('2026-08-10 23:59:00');
        $now = $due->clone()->subHours(24)->subMinute();

        $this->assertSame(DeadlineStatus::OnTime, $this->deadline->classify($due, $now));
    }

    public function test_classify_exactly_24_hours_before_due_is_due_soon(): void
    {
        $due = Carbon::parse('2026-08-10 23:59:00');

        $this->assertSame(DeadlineStatus::DueSoon, $this->deadline->classify($due, $due->clone()->subHours(24)));
    }

    public function test_classify_exactly_at_due_is_still_due_soon(): void
    {
        $due = Carbon::parse('2026-08-10 23:59:00');

        $this->assertSame(DeadlineStatus::DueSoon, $this->deadline->classify($due, $due->clone()));
    }

    public function test_classify_just_after_due_is_overdue(): void
    {
        $due = Carbon::parse('2026-08-10 23:59:00');

        $this->assertSame(DeadlineStatus::Overdue, $this->deadline->classify($due, $due->clone()->addMinute()));
    }

    // -------------------------------------------------------------- recomputeStep()

    public function test_recompute_skips_a_terminal_step(): void
    {
        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->assertFalse($this->deadline->recomputeStep($step));
    }

    public function test_recompute_skips_a_step_with_no_due_date(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->assertFalse($this->deadline->recomputeStep($task->currentStep));
    }

    public function test_recompute_skips_a_held_task(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $this->workflow()->hold($task, $this->manager, 'Pausing');

        $this->assertFalse($this->deadline->recomputeStep($step->fresh()));
    }

    public function test_recompute_writes_only_when_the_classification_changes(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        // Freshly assigned with a due date days out — already on_time, so no write.
        $this->assertFalse($this->deadline->recomputeStep($step->fresh()));

        $pastDue = $step->current_due_at->clone()->addDay();
        $this->assertTrue($this->deadline->recomputeStep($step->fresh(), $pastDue));
        $this->assertSame(DeadlineStatus::Overdue, $step->fresh()->deadline_status);
    }
}
