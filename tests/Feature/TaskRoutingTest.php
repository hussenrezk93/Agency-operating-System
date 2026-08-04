<?php

namespace Tests\Feature;

use App\Exceptions\RoutingNotAllowedException;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * PHASE 1B SLICE 2 — next-department routing (BRD §9.9, §15, §22.4).
 *
 * The Admin-managed `department_routes` matrix is the only source of permitted hops. A
 * missing row is a refusal: routing is allow-listed, never denied by exception, so a
 * department that nobody configured cannot receive work by default.
 */
class TaskRoutingTest extends TestCase
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

    public function test_a_permitted_hop_is_allowed(): void
    {
        $this->allowRoute($this->marketing, $this->design);
        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->assertTrue($this->routing()->canTransferToDepartment($step, $this->design));

        $next = $this->workflow()->sendToNextDepartment($step, $this->leader, $this->design);
        $this->assertSame($this->design->id, $next->department_id);
    }

    /** No configured route means no transfer — the matrix is an allow-list. */
    public function test_a_hop_with_no_configured_route_is_refused(): void
    {
        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->assertFalse($this->routing()->canTransferToDepartment($step, $this->design));

        $this->expectException(RoutingNotAllowedException::class);
        $this->workflow()->sendToNextDepartment($step, $this->leader, $this->design);
    }

    public function test_a_route_explicitly_marked_not_allowed_is_refused(): void
    {
        $this->allowRoute($this->marketing, $this->design, allowed: false);
        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->assertFalse($this->routing()->canTransferToDepartment($step, $this->design));

        $this->expectException(RoutingNotAllowedException::class);
        $this->workflow()->sendToNextDepartment($step, $this->leader, $this->design);
    }

    /** Routes are directional: Marketing → Design does not imply Design → Marketing. */
    public function test_a_route_does_not_work_in_reverse(): void
    {
        $this->allowRoute($this->design, $this->marketing);
        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->assertFalse($this->routing()->canTransferToDepartment($step, $this->design));
    }

    public function test_an_inactive_department_cannot_receive_work(): void
    {
        $this->allowRoute($this->marketing, $this->design);
        $this->design->forceFill(['is_active' => false])->save();

        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->assertFalse($this->routing()->canTransferToDepartment($step, $this->design->refresh()));

        $this->expectException(RoutingNotAllowedException::class);
        $this->workflow()->sendToNextDepartment($step, $this->leader, $this->design);
    }

    /** BRD §2 — a step never routes back into the department already holding it. */
    public function test_a_step_cannot_be_sent_to_its_own_department(): void
    {
        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->assertFalse($this->routing()->canTransferToDepartment($step, $this->marketing));

        $this->expectException(RoutingNotAllowedException::class);
        $this->workflow()->sendToNextDepartment($step, $this->leader, $this->marketing);
    }

    /**
     * Project membership is derived from the participating departments (BRD §7.2), so a
     * project task must not reach a department that is not one of them. Flagged in the
     * delivery report as an addition to confirm.
     */
    public function test_a_project_task_cannot_be_sent_to_a_non_participating_department(): void
    {
        $this->allowRoute($this->marketing, $this->design);

        $project = Project::factory()->create();
        $this->addDepartmentToProject($project, $this->marketing);

        $task = $this->newTask($this->marketing, $this->manager, ['project_id' => $project->id]);
        $step = $task->currentStep;

        $this->workflow()->assign($step, $this->leader, $this->employee,
            now()->toDateString(), now()->addDay()->toDateString());
        $this->workflow()->addOutput($step->refresh(), $this->employee, 'https://drive.example.com/v1');
        $this->workflow()->submit($step->refresh(), $this->employee);
        $this->workflow()->approve($step->refresh(), $this->leader);

        $this->assertFalse($this->routing()->canTransferToDepartment($step->refresh(), $this->design));

        $this->expectException(RoutingNotAllowedException::class);
        $this->workflow()->sendToNextDepartment($step->refresh(), $this->leader, $this->design);
    }

    public function test_a_project_task_may_be_sent_to_a_participating_department(): void
    {
        $this->allowRoute($this->marketing, $this->design);

        $project = Project::factory()->create();
        $this->addDepartmentToProject($project, $this->marketing);
        $this->addDepartmentToProject($project, $this->design);

        $task = $this->newTask($this->marketing, $this->manager, ['project_id' => $project->id]);
        $step = $task->currentStep;

        $this->workflow()->assign($step, $this->leader, $this->employee,
            now()->toDateString(), now()->addDay()->toDateString());
        $this->workflow()->addOutput($step->refresh(), $this->employee, 'https://drive.example.com/v1');
        $this->workflow()->submit($step->refresh(), $this->employee);
        $this->workflow()->approve($step->refresh(), $this->leader);

        $next = $this->workflow()->sendToNextDepartment($step->refresh(), $this->leader, $this->design);

        $this->assertSame($this->design->id, $next->department_id);
        $this->assertSame(2, $next->sequence_no);
    }

    /** BRD §9.9 — the list the "send to next department" control may offer. */
    public function test_the_allowed_department_list_contains_only_permitted_hops(): void
    {
        $editing = $this->makeDepartment('Editing');
        $moderation = $this->makeDepartment('Moderation');

        $this->allowRoute($this->marketing, $this->design);
        $this->allowRoute($this->marketing, $editing);
        $this->allowRoute($this->marketing, $moderation, allowed: false);

        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $names = $this->routing()->allowedNextDepartments($step)->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['Design', 'Editing'], $names);
        $this->assertNotContains('Marketing', $names);
        $this->assertNotContains('Moderation', $names);
    }

    public function test_the_allowed_departments_endpoint_answers_for_the_team_leader(): void
    {
        $this->allowRoute($this->marketing, $this->design);
        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->leader)
            ->getJson(route('tasks.steps.allowed-departments', $step))
            ->assertOk()
            ->assertJsonCount(1, 'departments')
            ->assertJsonPath('departments.0.name', 'Design');
    }

    /** A refused hop is a 422 sequencing/configuration error, not a 403. */
    public function test_a_refused_hop_returns_a_workflow_error_over_http(): void
    {
        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->leader)
            ->postJson(route('tasks.steps.transfer', $step), [
                'to_department_id' => $this->design->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'workflow_violation');
    }
}
