<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** BRD §8 — the creator edits a task's own data only until the first step is assigned. */
class TaskEditTest extends TestCase
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

    public function test_the_creator_edits_the_task_before_it_is_assigned(): void
    {
        $task = $this->newTask($this->marketing, $this->manager, ['title' => 'Original title']);

        $this->actingAs($this->manager)->get(route('tasks.edit-form', $task))->assertOk();

        $response = $this->actingAs($this->manager)->patch(route('tasks.update', $task), [
            'title' => 'Updated title',
            'brief' => 'Updated brief',
            'notes' => 'Updated notes',
            'priority' => 'urgent',
        ]);

        $response->assertRedirect(route('tasks.show', $task));
        $task->refresh();
        $this->assertSame('Updated title', $task->title);
        $this->assertSame('Updated brief', $task->brief);
        $this->assertSame('urgent', $task->priority->value);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $this->manager->id,
            'action' => 'task.updated',
            'entity_type' => 'task',
            'entity_id' => $task->id,
        ]);
    }

    public function test_a_non_creator_manager_cannot_edit_the_task(): void
    {
        $otherManager = $this->makeManager();
        $task = $this->newTask($this->marketing, $this->manager);

        $this->actingAs($otherManager)->get(route('tasks.edit-form', $task))->assertForbidden();
        $this->actingAs($otherManager)->patch(route('tasks.update', $task), [
            'title' => 'Hijacked title',
            'brief' => 'Hijacked brief',
        ])->assertForbidden();
    }

    public function test_editing_is_refused_once_the_first_step_has_ever_been_assigned(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $task = Task::find($task->id);

        $this->actingAs($this->leader)->get(route('tasks.edit-form', $task))->assertForbidden();
        $this->actingAs($this->leader)->patch(route('tasks.update', $task), [
            'title' => 'Too late',
            'brief' => 'Too late',
        ])->assertForbidden();
    }

    public function test_a_closed_task_cannot_be_edited(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $this->workflow()->cancelTask($task, $this->manager, 'No longer needed');

        $this->actingAs($this->manager)->patch(route('tasks.update', $task), [
            'title' => 'Too late',
            'brief' => 'Too late',
        ])->assertForbidden();
    }

    public function test_an_employee_cannot_edit_a_task(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->actingAs($this->employee)->get(route('tasks.edit-form', $task))->assertForbidden();
    }
}
