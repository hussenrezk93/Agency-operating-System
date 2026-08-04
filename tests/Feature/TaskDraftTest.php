<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** BRD §8 — save as draft (no department chosen yet, no step opens) and delete before publish. */
class TaskDraftTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment('Marketing');
        $this->manager = $this->makeManager();
    }

    public function test_saving_a_task_with_no_department_creates_a_draft_with_no_step(): void
    {
        $response = $this->actingAs($this->manager)->post('/tasks', [
            'intent' => 'draft',
            'title' => 'Rough idea for a campaign',
            'brief' => 'Not fully scoped yet.',
        ]);

        $task = Task::where('title', 'Rough idea for a campaign')->firstOrFail();

        $response->assertRedirect(route('tasks.show', $task));
        $this->assertSame('draft', $task->lifecycle_status->value);
        $this->assertNull($task->current_step_id);
        $this->assertSame(0, $task->steps()->count());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'task.draft_saved',
            'entity_type' => 'task',
            'entity_id' => $task->id,
        ]);
    }

    public function test_the_creator_publishes_their_own_draft(): void
    {
        $task = Task::create([
            'task_code' => 'TSK-DRAFT-1',
            'title' => 'Draft task',
            'brief' => 'Brief',
            'lifecycle_status' => 'draft',
            'created_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->manager)->post(route('tasks.publish', $task), [
            'first_department_id' => $this->marketing->id,
        ]);

        $task->refresh();
        $response->assertRedirect(route('tasks.show', $task));
        $this->assertSame('active', $task->lifecycle_status->value);
        $this->assertNotNull($task->current_step_id);
        $this->assertSame('waiting_assignment', $task->currentStep->workflow_status->value);
        $this->assertSame($this->marketing->id, $task->currentStep->department_id);
    }

    public function test_a_non_creator_cannot_publish_someone_elses_draft(): void
    {
        $otherManager = $this->makeManager();
        $task = Task::create([
            'task_code' => 'TSK-DRAFT-2',
            'title' => 'Draft task',
            'brief' => 'Brief',
            'lifecycle_status' => 'draft',
            'created_by' => $this->manager->id,
        ]);

        $this->actingAs($otherManager)->post(route('tasks.publish', $task), [
            'first_department_id' => $this->marketing->id,
        ])->assertForbidden();
    }

    public function test_the_creator_deletes_their_own_draft(): void
    {
        $task = Task::create([
            'task_code' => 'TSK-DRAFT-3',
            'title' => 'Draft task',
            'brief' => 'Brief',
            'lifecycle_status' => 'draft',
            'created_by' => $this->manager->id,
        ]);

        $this->actingAs($this->manager)->delete(route('tasks.destroy', $task))
            ->assertRedirect(route('tasks.index'));

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'task.draft_deleted', 'entity_id' => $task->id]);
    }

    public function test_a_published_task_can_no_longer_be_deleted_as_a_draft(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->actingAs($this->manager)->delete(route('tasks.destroy', $task))->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }
}
