<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * Phase 5 — the real Blade screens. Every action here reuses the same
 * TaskWorkflowService/policies the JSON API (TaskWorkflowTest/TaskAuthorizationTest)
 * already proves; this file proves the CLASSIC-BROWSER path around that engine:
 * a plain `->get()`/`->post()` (no Accept header) renders a view or redirects back,
 * it never dumps raw JSON.
 */
class TaskUiTest extends TestCase
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

    public function test_the_task_list_renders_for_every_role(): void
    {
        $this->newTask($this->marketing, $this->manager);

        foreach ([$this->manager, $this->leader, $this->employee] as $actor) {
            $this->actingAs($actor)->get('/tasks')->assertOk()->assertViewIs('tasks.index');
        }
    }

    public function test_an_employee_only_sees_tasks_they_are_assigned_to(): void
    {
        [$assignedTask] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $otherTask = $this->newTask($this->marketing, $this->manager);

        $response = $this->actingAs($this->employee)->get('/tasks');

        $response->assertSee($assignedTask->task_code);
        $response->assertDontSee($otherTask->task_code);
    }

    public function test_the_create_form_renders_for_manager_and_team_leader_but_not_employee(): void
    {
        $this->actingAs($this->manager)->get('/tasks/create')->assertOk()->assertViewIs('tasks.create');
        $this->actingAs($this->leader)->get('/tasks/create')->assertOk()->assertViewIs('tasks.create');
        $this->actingAs($this->employee)->get('/tasks/create')->assertForbidden();
    }

    public function test_a_classic_form_post_creates_a_task_and_redirects_to_its_page(): void
    {
        $response = $this->actingAs($this->manager)->post('/tasks', [
            'title' => 'Classic form task',
            'brief' => 'Created through the real Blade form.',
            'first_department_id' => $this->marketing->id,
            // Three blank reference-link rows, exactly like the create form always posts.
            'reference_links' => [
                ['url' => '', 'label' => ''],
                ['url' => '', 'label' => ''],
                ['url' => '', 'label' => ''],
            ],
        ]);

        $task = Task::where('title', 'Classic form task')->firstOrFail();

        $response->assertRedirect(route('tasks.show', $task));
        $this->assertSame($this->marketing->id, $task->currentStep->department_id);
    }

    public function test_the_task_detail_page_renders_with_the_right_actions_per_role(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        // The assignee (Employee) may add outputs and submit, not review.
        $asEmployee = $this->actingAs($this->employee)->get(route('tasks.show', $task));
        $asEmployee->assertOk()->assertViewIs('tasks.show');
        $asEmployee->assertViewHas('canAddOutput', true);
        $asEmployee->assertViewHas('canSubmit', true);
        $asEmployee->assertViewHas('canReview', false);

        // The department's Team Leader may not add outputs, but reviews once submitted.
        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/out');
        $this->workflow()->submit($step, $this->employee);

        $asLeader = $this->actingAs($this->leader)->get(route('tasks.show', $task));
        $asLeader->assertOk();
        $asLeader->assertViewHas('canReview', true);
        $asLeader->assertViewHas('canAddOutput', false);
    }

    public function test_a_classic_workflow_violation_redirects_back_with_an_error_not_json(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        // Submitting with no output is a WorkflowException (MissingOutputException).
        $response = $this->actingAs($this->employee)->post(route('tasks.steps.submit', $step));

        $response->assertRedirect();
        $response->assertSessionHasErrors('workflow');
        $this->assertStringNotContainsString('workflow_violation', $response->getContent() ?: '');
    }

    public function test_the_full_classic_form_workflow_redirects_at_every_step(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $step = $task->currentStep;

        $this->actingAs($this->leader)->post(route('tasks.steps.assign', $step), [
            'assignee_id' => $this->employee->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
        ])->assertRedirect(route('tasks.show', $task));

        $step->refresh();

        $this->actingAs($this->employee)->post(route('tasks.steps.outputs.store', $step), [
            'url' => 'https://drive.example.com/out',
        ])->assertRedirect(route('tasks.show', $task));

        $this->actingAs($this->employee)->post(route('tasks.steps.submit', $step))
            ->assertRedirect(route('tasks.show', $task));

        $this->actingAs($this->leader)->post(route('tasks.steps.review', $step), [
            'decision' => 'approved',
        ])->assertRedirect(route('tasks.show', $task));

        $step->refresh();

        $this->actingAs($this->leader)->post(route('tasks.steps.complete', $step))
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame('completed', $task->fresh()->lifecycle_status->value);
    }

    public function test_a_classic_cancel_redirects_to_the_task_page(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->actingAs($this->manager)
            ->post(route('tasks.cancel', $task), ['reason' => 'No longer needed'])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame('cancelled', $task->fresh()->lifecycle_status->value);
    }

    public function test_the_assignee_and_department_leader_can_add_and_see_comments(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->employee)
            ->post(route('tasks.steps.comments.store', $step), ['body' => 'Need the brand kit link'])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertDatabaseHas('task_step_comments', [
            'task_step_id' => $step->id,
            'body' => 'Need the brand kit link',
        ]);

        $this->actingAs($this->leader)->get(route('tasks.show', $task))
            ->assertSee('Need the brand kit link');
    }

    public function test_an_unrelated_employee_cannot_add_a_comment(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $outsider = $this->makeEmployee($this->makeDepartment('Design'));

        $this->actingAs($outsider)
            ->post(route('tasks.steps.comments.store', $step), ['body' => 'Not my business'])
            ->assertForbidden();
    }

    public function test_the_history_page_renders(): void
    {
        [$task] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->manager)->get(route('tasks.history', $task))
            ->assertOk()
            ->assertViewIs('tasks.history');
    }
}
