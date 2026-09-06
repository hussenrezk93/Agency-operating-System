<?php

namespace Tests\Feature;

use App\Enums\DeadlineStatus;
use App\Enums\TaskLifecycle;
use App\Enums\WorkflowStatus;
use App\Exceptions\IllegalTransitionException;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * PHASE 1B SLICE 3 — task-level Hold/Resume (Q5/Q9/Q23) and Manager Redirect (BRD §10),
 * driven directly through TaskWorkflowService, the same way TaskWorkflowTest proves the
 * rest of the engine.
 */
class TaskHoldRedirectTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private Department $design;

    private User $manager;

    private User $leader;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment('Marketing');
        $this->design = $this->makeDepartment('Design');
        $this->manager = $this->makeManager();
        $this->leader = $this->makeTeamLeader($this->marketing);
        $this->employee = $this->makeEmployee($this->marketing);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // -------------------------------------------------------------------- hold

    public function test_holding_a_task_pauses_the_current_steps_deadline_clock(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $held = $this->workflow()->hold($task, $this->manager, 'Client requested a pause');

        $this->assertSame(TaskLifecycle::OnHold, $held->lifecycle_status);
        $this->assertSame(DeadlineStatus::Paused, $step->fresh()->deadline_status);
        $this->assertDatabaseHas('task_holds', [
            'task_id' => $task->id,
            'reason' => 'Client requested a pause',
            'ended_at' => null,
        ]);
    }

    public function test_hold_requires_a_reason(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->expectException(ValidationException::class);

        $this->workflow()->hold($task, $this->manager, '  ');
    }

    public function test_a_held_task_blocks_workflow_actions(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $this->workflow()->hold($task, $this->manager, 'Pausing for now');

        $this->expectException(IllegalTransitionException::class);

        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/out');
    }

    public function test_holding_an_already_held_task_is_rejected(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $this->workflow()->hold($task, $this->manager, 'First hold');

        // TaskPolicy::hold() only admits an Active task, so a second hold is refused at
        // the authorization boundary before any workflow rule is even consulted.
        $this->expectException(AuthorizationException::class);

        $this->workflow()->hold($task->fresh(), $this->manager, 'Second hold');
    }

    public function test_a_team_leader_who_did_not_create_the_task_cannot_hold_it(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $outsider = $this->makeTeamLeader($this->design);

        $this->expectException(AuthorizationException::class);

        $this->workflow()->hold($task, $outsider, 'Not mine to pause');
    }

    // ------------------------------------------------------------------ resume

    public function test_resuming_without_a_hold_is_rejected(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        // TaskPolicy::resume() only admits an on-hold task.
        $this->expectException(AuthorizationException::class);

        $this->workflow()->resume($task, $this->manager);
    }

    public function test_resuming_extends_the_due_date_by_exactly_the_paused_duration(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-01 09:00:00', config('app.timezone')));

        // makeTeamLeader() (setUp(), before this freeze) backdated the leadership
        // assignment's start_date to the REAL now()->subMonth() — once real time passes
        // 2026-09-01 that lands AFTER the frozen date above, making canActAsLeaderOf()
        // false. Pin it safely in the past so this test stays correct on any real date.
        DepartmentLeadershipAssignment::where('user_id', $this->leader->id)->update(['start_date' => '2026-01-01']);

        [$task, $step, $assignment] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $originalDueAt = $step->current_due_at->clone();

        $this->workflow()->hold($task, $this->manager, 'Awaiting client input');

        Carbon::setTestNow(Carbon::parse('2026-08-03 09:00:00', config('app.timezone')));

        $resumed = $this->workflow()->resume($task, $this->manager);

        $this->assertSame(TaskLifecycle::Active, $resumed->lifecycle_status);

        $freshStep = $step->fresh();
        $expectedDueAt = $originalDueAt->clone()->addDays(2);

        $this->assertTrue($freshStep->current_due_at->equalTo($expectedDueAt));
        $this->assertSame($expectedDueAt->toDateString(), $assignment->fresh()->due_date->toDateString());
        $this->assertSame(DeadlineStatus::OnTime, $freshStep->deadline_status);
        $this->assertDatabaseHas('task_holds', [
            'task_id' => $task->id,
            'paused_seconds' => 2 * 24 * 60 * 60,
        ]);
    }

    // ---------------------------------------------------------------- redirect

    public function test_manager_redirects_a_waiting_task_bypassing_the_routing_matrix(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $firstStep = $task->currentStep;

        // Deliberately no DepartmentRoute between Marketing and Design — Redirect must
        // not consult the matrix at all.
        $next = $this->workflow()->redirect($task, $this->manager, $this->design, 'Wrong department, correcting');

        $this->assertSame(2, $next->sequence_no);
        $this->assertSame($this->design->id, $next->department_id);
        $this->assertSame(WorkflowStatus::WaitingAssignment, $next->workflow_status);
        $this->assertSame(WorkflowStatus::Redirected, $firstStep->fresh()->workflow_status);
        $this->assertDatabaseHas('task_redirects', [
            'task_id' => $task->id,
            'from_step_id' => $firstStep->id,
            'to_step_id' => $next->id,
            'to_department_id' => $this->design->id,
        ]);
    }

    public function test_redirect_may_target_a_previously_visited_department(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $this->workflow()->redirect($task, $this->manager, $this->design, 'First correction');

        $backToMarketing = $this->workflow()->redirect(
            $task->fresh(),
            $this->manager,
            $this->marketing,
            'Actually belongs back in Marketing',
        );

        $this->assertSame(3, $backToMarketing->sequence_no);
        $this->assertSame($this->marketing->id, $backToMarketing->department_id);
        $this->assertCount(3, $task->fresh()->steps);
    }

    public function test_redirect_cannot_target_the_same_department(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->expectException(ValidationException::class);

        $this->workflow()->redirect($task, $this->manager, $this->marketing, 'No-op');
    }

    public function test_redirect_is_refused_once_the_step_is_approved(): void
    {
        [$task] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->expectException(IllegalTransitionException::class);

        $this->workflow()->redirect($task, $this->manager, $this->design, 'Too late now');
    }

    public function test_only_a_manager_may_redirect(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->expectException(AuthorizationException::class);

        $this->workflow()->redirect($task, $this->leader, $this->design, 'Not my call');
    }

    // ------------------------------------------------------------ project hold gate

    public function test_creating_a_task_under_an_on_hold_project_is_refused(): void
    {
        $project = Project::factory()->create(['status' => 'on_hold']);

        $this->expectException(ValidationException::class);

        $this->newTask($this->marketing, $this->manager, ['project_id' => $project->id]);
    }
}
