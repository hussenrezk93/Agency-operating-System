<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** PHASE 9 — each of the 4 roles gets its own dashboard. */
class DashboardTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private User $manager;

    private User $leader;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment('Marketing');
        $this->manager = $this->makeManager();
        $this->leader = $this->makeTeamLeader($this->marketing);
        $this->employee = $this->makeEmployee($this->marketing);
    }

    public function test_the_employee_sees_the_employee_dashboard_with_correct_current_count(): void
    {
        $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $response = $this->actingAs($this->employee)->get(route('dashboard'));

        $response->assertOk()->assertViewIs('dashboard.employee');
        $response->assertViewHas('current', 1);
    }

    public function test_the_tl_sees_the_tl_dashboard_with_department_scoped_counts(): void
    {
        $this->newTask($this->marketing, $this->manager);

        $response = $this->actingAs($this->leader)->get(route('dashboard'));

        $response->assertOk()->assertViewIs('dashboard.tl');
        $response->assertViewHas('waitingAssignment', 1);
    }

    public function test_the_manager_sees_the_manager_dashboard(): void
    {
        $this->newTask($this->marketing, $this->manager);

        $response = $this->actingAs($this->manager)->get(route('dashboard'));

        $response->assertOk()->assertViewIs('dashboard.manager');
        $response->assertViewHas('activeTasks', 1);
    }

    public function test_the_admin_keeps_the_generic_dashboard(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()->assertViewIs('dashboard');
    }
}
