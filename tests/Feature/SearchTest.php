<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** PHASE 9 — BRD §16 search: task number/title and project name, Urgent first, visibility-scoped. */
class SearchTest extends TestCase
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

    public function test_matches_a_task_by_its_code(): void
    {
        $task = $this->newTask($this->marketing, $this->manager, ['title' => 'Launch teaser video']);

        $response = $this->actingAs($this->manager)->get(route('search.index', ['q' => $task->task_code]));

        $response->assertOk()->assertSee($task->task_code);
    }

    public function test_matches_a_task_by_title(): void
    {
        $this->newTask($this->marketing, $this->manager, ['title' => 'Design the new landing page']);

        $response = $this->actingAs($this->manager)->get(route('search.index', ['q' => 'landing page']));

        $response->assertOk()->assertSee('Design the new landing page');
    }

    public function test_matches_a_project_by_name(): void
    {
        $project = Project::factory()->create(['name' => 'Q3 Marketing Campaign', 'created_by' => $this->manager->id]);

        $response = $this->actingAs($this->manager)->get(route('search.index', ['q' => 'Marketing Campaign']));

        $response->assertOk()->assertSee($project->name);
    }

    public function test_urgent_tasks_are_sorted_first(): void
    {
        $normal = $this->newTask($this->marketing, $this->manager, ['title' => 'Report normal', 'priority' => Priority::Medium->value]);
        $urgent = $this->newTask($this->marketing, $this->manager, ['title' => 'Report urgent', 'priority' => Priority::Urgent->value]);

        $response = $this->actingAs($this->manager)->get(route('search.index', ['q' => 'Report']));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, $normal->task_code), strpos($content, $urgent->task_code));
    }

    public function test_an_employee_never_sees_a_task_they_were_never_assigned(): void
    {
        $task = $this->newTask($this->marketing, $this->manager, ['title' => 'Confidential planning doc']);

        $response = $this->actingAs($this->employee)->get(route('search.index', ['q' => 'Confidential']));

        $response->assertOk()->assertDontSee('Confidential planning doc');
    }

    public function test_an_admin_is_forbidden_from_search(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('search.index', ['q' => 'anything']))->assertForbidden();
    }
}
