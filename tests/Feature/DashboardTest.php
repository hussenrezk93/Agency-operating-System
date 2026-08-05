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

    /** BRD §16.3 — a redirected step still waiting for the receiving TL to assign it. */
    public function test_the_manager_dashboard_lists_redirected_steps_awaiting_assignment(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $this->workflow()->redirect($task, $this->manager, $this->design, 'Wrong department, correcting');

        $response = $this->actingAs($this->manager)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('redirectedAwaitingAssignment', fn ($steps) => $steps->count() === 1
            && $steps->first()->department_id === $this->design->id);
    }

    /** BRD §16.2 — the TL sees whether the assignee has opened each of their tasks yet. */
    public function test_the_tl_dashboard_shows_whether_each_assignment_was_opened(): void
    {
        $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $before = $this->actingAs($this->leader)->get(route('dashboard'));
        $before->assertViewHas('tasksByEmployee', function ($byEmployee) {
            return $byEmployee->flatten()->every(fn ($a) => $a->first_seen_at === null);
        });

        $this->actingAs($this->employee)->getJson(route('tasks.mine'));

        $after = $this->actingAs($this->leader)->get(route('dashboard'));
        $after->assertViewHas('tasksByEmployee', function ($byEmployee) {
            return $byEmployee->flatten()->every(fn ($a) => $a->first_seen_at !== null);
        });
    }

    public function test_the_admin_keeps_the_generic_dashboard(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()->assertViewIs('dashboard');
    }
}
