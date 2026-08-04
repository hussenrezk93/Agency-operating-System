<?php

namespace Tests\Feature;

use App\Enums\ReviewDecision;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * PHASE 1B SLICE 2 — the permission matrix.
 *
 * Half of these assertions go through the POLICY and half through the HTTP ENDPOINT. Both
 * matter: the policy assertions pin the rule, and the endpoint assertions prove BRD §18's
 * requirement that permissions are enforced on the server and not by hiding buttons. A
 * refusal at the route-middleware layer returns 403 before the controller is reached,
 * which is why several of the endpoint tests expect 403 rather than a validation error.
 */
class TaskAuthorizationTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private Department $design;

    private User $manager;

    private User $admin;

    private User $leader;

    private User $employee;

    private User $otherLeader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment('Marketing');
        $this->design = $this->makeDepartment('Design');
        $this->manager = $this->makeManager();
        $this->admin = $this->makeAdmin();
        $this->leader = $this->makeTeamLeader($this->marketing);
        $this->otherLeader = $this->makeTeamLeader($this->design);
        $this->employee = $this->makeEmployee($this->marketing);
    }

    // ------------------------------------------------------------- the employee

    public function test_an_employee_cannot_approve_or_request_changes(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->assertFalse($this->employee->can('review', $step));
        $this->assertFalse($this->employee->can('approve', $step));
        $this->assertFalse($this->employee->can('requestChanges', $step));

        $this->expectException(AuthorizationException::class);
        $this->workflow()->approve($step, $this->employee);
    }

    public function test_an_employee_cannot_transfer_a_step(): void
    {
        $this->allowRoute($this->marketing, $this->design);
        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->assertFalse($this->employee->can('transfer', $step));

        $this->expectException(AuthorizationException::class);
        $this->workflow()->sendToNextDepartment($step, $this->employee, $this->design);
    }

    public function test_an_employee_cannot_assign_anybody(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->assertFalse($this->employee->can('assign', $task->currentStep));

        $this->expectException(AuthorizationException::class);
        $this->workflow()->assign(
            $task->currentStep, $this->employee, $this->employee,
            now()->toDateString(), now()->addDay()->toDateString(),
        );
    }

    public function test_an_employee_cannot_create_a_task(): void
    {
        $this->assertFalse($this->employee->can('create', Task::class));
    }

    public function test_an_employee_cannot_complete_or_cancel_a_task(): void
    {
        [$task, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->assertFalse($this->employee->can('complete', $task));
        $this->assertFalse($this->employee->can('cancel', $task));

        $this->expectException(AuthorizationException::class);
        $this->workflow()->completeTask($step, $this->employee);
    }

    /** BRD §15 — an employee sees the work assigned to them, and nothing else. */
    public function test_an_employee_sees_only_tasks_they_are_assigned(): void
    {
        [$mine] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $colleague = $this->makeEmployee($this->marketing);
        [$theirs] = $this->taskInProgress($this->marketing, $this->leader, $colleague);

        $this->assertTrue($this->employee->can('view', $mine));
        $this->assertFalse($this->employee->can('view', $theirs));
    }

    public function test_an_employee_keeps_read_access_after_their_step_is_approved(): void
    {
        [$task] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->assertTrue($this->employee->can('view', $task->refresh()));
    }

    // -------------------------------------------------------------- the manager

    /** Q21 — the single most important negative rule in this slice. */
    public function test_the_manager_cannot_assign_an_employee_to_a_step(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $step = $task->currentStep;

        $this->assertFalse(
            $this->manager->can('assign', $step),
            'Q21: only the effective Team Leader of the receiving department may assign',
        );

        $this->expectException(AuthorizationException::class);
        $this->workflow()->assign(
            $step, $this->manager, $this->employee,
            now()->toDateString(), now()->addDay()->toDateString(),
        );
    }

    public function test_the_manager_cannot_transfer_a_step_between_departments(): void
    {
        $this->allowRoute($this->marketing, $this->design);
        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->assertFalse(
            $this->manager->can('transfer', $step),
            'the Manager corrects a route with Redirect (BRD §10), not with a transfer',
        );
    }

    /** Q12 — the Manager reviews a self-assigned step, and only that one. */
    public function test_the_manager_may_review_only_a_self_assigned_step(): void
    {
        [, $selfAssigned] = $this->taskUnderReview($this->marketing, $this->leader, $this->leader);
        [, $normal] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->assertTrue($this->manager->can('review', $selfAssigned));
        $this->assertFalse($this->manager->can('review', $normal));
    }

    public function test_the_manager_oversees_every_task(): void
    {
        [$task] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->assertTrue($this->manager->can('view', $task));
        $this->assertTrue($this->manager->can('create', Task::class));
        $this->assertTrue($this->manager->can('cancel', $task));
    }

    // ---------------------------------------------------------- the team leader

    public function test_a_team_leader_cannot_act_on_another_departments_step(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $step = $task->currentStep;

        $this->assertFalse($this->otherLeader->can('assign', $step));
        $this->assertFalse($this->otherLeader->can('view', $step));
        $this->assertFalse($this->otherLeader->can('view', $task));
    }

    /** BRD §15 — user and department administration is never a Team Leader's. */
    public function test_a_team_leader_cannot_manage_users_or_departments(): void
    {
        $this->assertFalse($this->leader->can('viewAny', User::class));
        $this->assertFalse($this->leader->can('create', User::class));
        $this->assertFalse($this->leader->can('manage', $this->employee));
    }

    // ----------------------------------------------------- Q14 / Q11 leadership

    /**
     * Q14 — while a temporary Team Leader covers the department, the primary one may read
     * but performs no leadership action. Q11 — the temporary one has the full set.
     */
    public function test_a_covered_primary_team_leader_is_view_only_and_the_temporary_one_acts(): void
    {
        $candidate = $this->makeEmployee($this->marketing);
        $worker = $this->makeEmployee($this->marketing);

        $task = $this->newTask($this->marketing, $this->manager);
        $step = $task->currentStep;

        $temporary = $this->appointTemporaryLeader($this->marketing, $candidate);
        $primary = $this->leader->refresh();

        $this->assertTrue($primary->isViewOnlyLeader());
        $this->assertFalse($primary->can('assign', $step), 'Q14 — no leadership action while covered');
        $this->assertTrue($primary->can('view', $task), 'Q14 — reading is still allowed');

        $this->assertTrue($temporary->can('assign', $step), 'Q11 — full operational permissions');

        $this->workflow()->assign(
            $step, $temporary, $worker,
            now()->toDateString(), now()->addDays(2)->toDateString(),
        );

        $this->workflow()->addOutput($step->refresh(), $worker, 'https://drive.example.com/v1');
        $this->workflow()->submit($step->refresh(), $worker);

        $this->assertTrue($temporary->can('review', $step->refresh()));
        $this->assertFalse($primary->can('review', $step));
    }

    // ---------------------------------------------------------------- the admin

    /** BRD §15 / §19 — the Admin configures and audits; task content is not theirs. */
    public function test_the_admin_never_sees_task_content(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->assertFalse($this->admin->can('viewAny', Task::class));
        $this->assertFalse($this->admin->can('view', $task));
        $this->assertFalse($this->admin->can('view', $step));
        $this->assertFalse($this->admin->can('create', Task::class));
        $this->assertFalse($this->admin->can('cancel', $task));
        $this->assertFalse($this->admin->can('complete', $task));
    }

    // ------------------------------------------------------------ Q20 first seen

    /**
     * Q20 — `first_seen_at` is the department Team Leader's information only. The employee
     * it describes must not see it, and neither must the Manager.
     */
    public function test_first_seen_is_visible_only_to_the_effective_team_leader(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->assertTrue($this->leader->can('viewFirstSeen', $step));
        $this->assertFalse($this->employee->can('viewFirstSeen', $step));
        $this->assertFalse($this->manager->can('viewFirstSeen', $step));
        $this->assertFalse($this->otherLeader->can('viewFirstSeen', $step));
    }

    // ------------------------------------------------------------ HTTP boundary

    public function test_guests_are_redirected_away_from_every_workflow_endpoint(): void
    {
        [$task, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->post(route('tasks.store'))->assertRedirect(route('login'));
        $this->get(route('tasks.show', $task))->assertRedirect(route('login'));
        $this->get(route('tasks.mine'))->assertRedirect(route('login'));
        $this->post(route('tasks.steps.review', $step))->assertRedirect(route('login'));
    }

    public function test_an_employee_is_refused_the_review_endpoint(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->employee)
            ->postJson(route('tasks.steps.review', $step), [
                'decision' => ReviewDecision::Approved->value,
            ])
            ->assertForbidden();
    }

    /** Q21 enforced at the outermost layer: the Manager never reaches the controller. */
    public function test_the_manager_is_refused_the_assign_endpoint(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->actingAs($this->manager)
            ->postJson(route('tasks.steps.assign', $task->currentStep), [
                'assignee_id' => $this->employee->id,
                'start_date' => now()->toDateString(),
                'due_date' => now()->addDay()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_an_employee_is_refused_the_task_creation_endpoint(): void
    {
        $this->actingAs($this->employee)
            ->postJson(route('tasks.store'), [
                'title' => 'Sneak in a task',
                'brief' => 'Should never be created.',
                'first_department_id' => $this->marketing->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, Task::count());
    }

    public function test_an_employee_is_refused_another_employees_task(): void
    {
        $colleague = $this->makeEmployee($this->marketing);
        [$theirs] = $this->taskInProgress($this->marketing, $this->leader, $colleague);

        $this->actingAs($this->employee)
            ->getJson(route('tasks.show', $theirs))
            ->assertForbidden();
    }

    public function test_the_admin_is_refused_the_task_endpoints(): void
    {
        [$task] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->admin)->getJson(route('tasks.show', $task))->assertForbidden();
        $this->actingAs($this->admin)->getJson(route('tasks.mine'))->assertForbidden();
    }

    public function test_the_employee_is_refused_the_first_seen_endpoint(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->employee)
            ->getJson(route('tasks.steps.first-seen', $step))
            ->assertForbidden();

        $this->actingAs($this->leader)
            ->getJson(route('tasks.steps.first-seen', $step))
            ->assertOk();
    }

    /** The happy path over HTTP, so the endpoints are known to be wired correctly. */
    public function test_a_team_leader_can_drive_the_endpoints_they_own(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $step = $task->currentStep;

        $this->actingAs($this->leader)
            ->postJson(route('tasks.steps.assign', $step), [
                'assignee_id' => $this->employee->id,
                'start_date' => now()->toDateString(),
                'due_date' => now()->addDays(2)->toDateString(),
            ])
            ->assertCreated();

        $this->actingAs($this->employee)
            ->postJson(route('tasks.steps.outputs.store', $step), [
                'url' => 'https://drive.example.com/final',
            ])
            ->assertCreated();

        $this->actingAs($this->employee)
            ->postJson(route('tasks.steps.submit', $step))
            ->assertOk();

        $this->actingAs($this->leader)
            ->postJson(route('tasks.steps.review', $step), [
                'decision' => ReviewDecision::Approved->value,
            ])
            ->assertCreated();

        $this->actingAs($this->leader)
            ->postJson(route('tasks.steps.complete', $step))
            ->assertOk();

        $this->assertTrue($task->refresh()->isClosed());
    }

    /** A broken workflow rule is a 422 sequencing error, not a 403 permission error. */
    public function test_a_submission_without_an_output_returns_a_workflow_error_over_http(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->employee)
            ->postJson(route('tasks.steps.submit', $step))
            ->assertStatus(422)
            ->assertJsonPath('error', 'workflow_violation');
    }
}
