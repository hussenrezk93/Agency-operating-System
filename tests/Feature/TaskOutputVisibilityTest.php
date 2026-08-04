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

    public function test_the_manager_sees_every_departments_output_regardless_of_the_matrix(): void
    {
        $response = $this->actingAs($this->manager)->get(route('tasks.show', $this->task));

        $response->assertOk();
        $response->assertViewHas('previousOutputs', fn ($outputs) => $outputs->isNotEmpty());
    }
}
