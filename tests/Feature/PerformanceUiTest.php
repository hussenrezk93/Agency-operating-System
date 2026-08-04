<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** PHASE 9 — UserPolicy::viewPerformance() enforced over HTTP. */
class PerformanceUiTest extends TestCase
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

    public function test_a_user_can_always_view_their_own_performance(): void
    {
        $this->actingAs($this->employee)->get(route('performance.show'))
            ->assertOk()->assertViewIs('performance.show');
    }

    public function test_a_manager_can_view_anyones_performance(): void
    {
        $this->actingAs($this->manager)->get(route('performance.show', $this->employee))
            ->assertOk();
    }

    public function test_a_tl_can_view_a_same_department_employee(): void
    {
        $this->actingAs($this->leader)->get(route('performance.show', $this->employee))
            ->assertOk();
    }

    public function test_a_tl_cannot_view_a_different_departments_employee(): void
    {
        $outsider = $this->makeEmployee($this->design);

        $this->actingAs($this->leader)->get(route('performance.show', $outsider))
            ->assertForbidden();
    }

    public function test_an_employee_cannot_view_someone_elses_performance(): void
    {
        $other = $this->makeEmployee($this->marketing);

        $this->actingAs($this->employee)->get(route('performance.show', $other))
            ->assertForbidden();
    }
}
