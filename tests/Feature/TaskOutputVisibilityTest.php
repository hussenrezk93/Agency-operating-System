<?php

namespace Tests\Feature;

use App\Enums\OutputAccessScope;
use App\Models\Department;
use App\Models\DepartmentOutputAccess;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * BRD §15 — a Team Leader only sees another department's approved output once Admin has
 * granted their department access to it via the department_output_access matrix.
 */
class TaskOutputVisibilityTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private Department $design;

    private User $manager;

    private User $marketingLeader;

    private User $designLeader;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment('Marketing');
        $this->design = $this->makeDepartment('Design');
        $this->manager = $this->makeManager();
        $this->marketingLeader = $this->makeTeamLeader($this->marketing);
        $this->designLeader = $this->makeTeamLeader($this->design);

        $this->allowRoute($this->marketing, $this->design);

        $marketingEmployee = $this->makeEmployee($this->marketing);
        [$this->task, $step] = $this->taskApproved($this->marketing, $this->marketingLeader, $marketingEmployee);
        $this->workflow()->sendToNextDepartment($step, $this->marketingLeader, $this->design, 'Ready for design');
    }

    public function test_a_tl_without_a_grant_cannot_see_the_other_departments_output(): void
    {
        $response = $this->actingAs($this->designLeader)->get(route('tasks.show', $this->task));

        $response->assertOk();
        $response->assertViewHas('previousOutputs', fn ($outputs) => $outputs->isEmpty());
    }

    public function test_a_tl_with_a_grant_sees_the_other_departments_output(): void
    {
        DepartmentOutputAccess::create([
            'viewer_department_id' => $this->design->id,
            'source_department_id' => $this->marketing->id,
            'scope' => OutputAccessScope::FinalOnly->value,
            'is_allowed' => true,
            'updated_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->designLeader)->get(route('tasks.show', $this->task));

        $response->assertOk();
        $response->assertViewHas('previousOutputs', fn ($outputs) => $outputs->isNotEmpty());
    }

    /**
     * Reported from production 2026-09-05 (TSK-2026-00026) — a task reopened and
     * redirected to another department whose Team Leader took the step HERSELF. The
     * matrix filter stripped the very links the step existed to act on, and it read as
     * though the outputs had been deleted. Q26 follows the seat, not the role: whoever
     * holds the step reads the work before it, Team Leader or not.
     */
    public function test_a_tl_who_holds_the_step_herself_still_sees_the_earlier_output(): void
    {
        $step = $this->task->refresh()->currentStep;
        $this->workflow()->assign(
            $step,
            $this->designLeader,
            $this->designLeader,
            now()->toDateString(),
            now()->addDay()->toDateString(),
        );

        $response = $this->actingAs($this->designLeader)->get(route('tasks.show', $this->task));

        $response->assertOk();
        $response->assertViewHas('previousOutputs', fn ($outputs) => $outputs->isNotEmpty());
    }

    /** An Employee holding the step never needed a grant, and still does not. */
    public function test_the_employee_holding_the_step_sees_the_earlier_output(): void
    {
        $designer = $this->makeEmployee($this->design);
        $step = $this->task->refresh()->currentStep;
        $this->workflow()->assign(
            $step,
            $this->designLeader,
            $designer,
            now()->toDateString(),
            now()->addDay()->toDateString(),
        );

        $response = $this->actingAs($designer)->get(route('tasks.show', $this->task));

        $response->assertOk();
        $response->assertViewHas('previousOutputs', fn ($outputs) => $outputs->isNotEmpty());
    }

    /** The matrix still governs a Team Leader who is only LOOKING — the fix above is
     *  about the person doing the work, and must not quietly widen BRD §15. */
    public function test_a_tl_who_assigned_the_step_to_someone_else_still_needs_a_grant(): void
    {
        $designer = $this->makeEmployee($this->design);
        $step = $this->task->refresh()->currentStep;
        $this->workflow()->assign(
            $step,
            $this->designLeader,
            $designer,
            now()->toDateString(),
            now()->addDay()->toDateString(),
        );

        $response = $this->actingAs($this->designLeader)->get(route('tasks.show', $this->task));

        $response->assertOk();
        $response->assertViewHas('previousOutputs', fn ($outputs) => $outputs->isEmpty());
    }

    public function test_the_manager_sees_every_departments_output_regardless_of_the_matrix(): void
    {
        $response = $this->actingAs($this->manager)->get(route('tasks.show', $this->task));

        $response->assertOk();
        $response->assertViewHas('previousOutputs', fn ($outputs) => $outputs->isNotEmpty());
    }
}
