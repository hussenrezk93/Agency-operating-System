<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\RoleCode;
use App\Enums\TaskLifecycle;
use App\Enums\WorkflowStatus;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectHold;
use App\Models\Task;
use App\Models\TaskHold;
use App\Models\TaskStep;
use App\Models\TaskStepAssignment;
use App\Models\TaskStepOutput;
use App\Models\TaskStepReview;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1B slice 1 — the task SCHEMA and its invariants.
 * The workflow engine itself is slice 2; nothing here drives a transition.
 */
class TaskSchemaTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private User $employee;

    private User $tl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->department = Department::factory()->create();
        $this->tl = User::factory()->role(RoleCode::TeamLeader)->inDepartment($this->department)->create();
        $this->employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->department)->create();
    }

    private function step(array $attrs = []): TaskStep
    {
        return TaskStep::factory()->create(array_merge([
            'task_id' => Task::factory(),
            'department_id' => $this->department->id,
        ], $attrs));
    }

    public function test_a_task_may_stand_alone_or_belong_to_a_project(): void
    {
        $standalone = Task::factory()->create();
        $project = Project::factory()->create();
        $inProject = Task::factory()->inProject($project)->create();

        $this->assertNull($standalone->project_id);
        $this->assertTrue($inProject->project->is($project));
        $this->assertTrue($project->tasks->contains($inProject));
    }

    public function test_task_code_is_unique(): void
    {
        Task::factory()->create(['task_code' => 'TSK-2026-00001']);

        $this->expectException(QueryException::class);
        Task::factory()->create(['task_code' => 'TSK-2026-00001']);
    }

    public function test_enum_casts_apply_to_tasks_and_steps(): void
    {
        $task = Task::factory()->urgent()->create();
        $step = $this->step(['task_id' => $task->id]);

        $this->assertSame(Priority::Urgent, $task->priority);
        $this->assertSame(TaskLifecycle::Active, $task->lifecycle_status);
        $this->assertSame(WorkflowStatus::WaitingAssignment, $step->workflow_status);
        $this->assertSame(0, Priority::Urgent->weight());
    }

    public function test_a_cancelled_task_must_carry_a_reason(): void
    {
        $task = Task::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('tasks')->where('id', $task->id)
            ->update(['lifecycle_status' => 'cancelled', 'cancelled_reason' => null]);
    }

    public function test_a_completed_task_must_record_who_closed_it_and_when(): void
    {
        $task = Task::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('tasks')->where('id', $task->id)
            ->update(['lifecycle_status' => 'completed', 'completed_at' => null]);
    }

    public function test_an_invalid_workflow_status_is_rejected_by_the_database(): void
    {
        $step = $this->step();

        $this->expectException(QueryException::class);
        DB::table('task_steps')->where('id', $step->id)->update(['workflow_status' => 'pending']);
    }

    public function test_step_sequence_is_unique_per_task(): void
    {
        $task = Task::factory()->create();
        $this->step(['task_id' => $task->id, 'sequence_no' => 1]);

        $this->expectException(QueryException::class);
        $this->step(['task_id' => $task->id, 'sequence_no' => 1]);
    }

    public function test_a_step_can_have_only_one_open_assignment(): void
    {
        $step = $this->step();
        $make = fn () => TaskStepAssignment::create([
            'task_step_id' => $step->id,
            'assignee_id' => $this->employee->id,
            'assigned_by' => $this->tl->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $make();

        $this->expectException(QueryException::class);
        $make();
    }

    public function test_a_closed_assignment_allows_a_new_one(): void
    {
        $step = $this->step();
        $first = TaskStepAssignment::create([
            'task_step_id' => $step->id,
            'assignee_id' => $this->employee->id,
            'assigned_by' => $this->tl->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'ended_at' => now(),
            'end_reason' => 'reassigned',
        ]);

        $second = TaskStepAssignment::create([
            'task_step_id' => $step->id,
            'assignee_id' => $this->tl->id,
            'assigned_by' => $this->tl->id,
            'is_self_assigned' => true,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->assertFalse($first->isOpen());
        $this->assertTrue($second->isOpen());
        $this->assertTrue($step->fresh()->activeAssignment->is($second));
        $this->assertTrue($step->fresh()->isSelfAssigned());
    }

    public function test_the_due_date_cannot_precede_the_start_date(): void
    {
        $step = $this->step();

        $this->expectException(QueryException::class);
        TaskStepAssignment::create([
            'task_step_id' => $step->id,
            'assignee_id' => $this->employee->id,
            'assigned_by' => $this->tl->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'due_date' => now()->toDateString(),
        ]);
    }

    public function test_first_seen_defaults_to_null_and_records_a_timestamp(): void
    {
        $step = $this->step();
        $assignment = TaskStepAssignment::create([
            'task_step_id' => $step->id,
            'assignee_id' => $this->employee->id,
            'assigned_by' => $this->tl->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertFalse($assignment->hasBeenSeen());

        $assignment->update(['first_seen_at' => now()]);

        $this->assertTrue($assignment->fresh()->hasBeenSeen());
    }

    public function test_requesting_changes_without_a_comment_is_rejected(): void
    {
        $step = $this->step();

        $this->expectException(QueryException::class);
        TaskStepReview::create([
            'task_step_id' => $step->id,
            'reviewer_id' => $this->tl->id,
            'decision' => 'changes_requested',
            'comment' => '   ',
        ]);
    }

    public function test_approving_without_a_comment_is_allowed(): void
    {
        $step = $this->step();

        $review = TaskStepReview::create([
            'task_step_id' => $step->id,
            'reviewer_id' => $this->tl->id,
            'decision' => 'approved',
        ]);

        $this->assertTrue($review->exists);
        $this->assertNull($review->comment);
    }

    /** Approved decision Q25 — outputs are grouped by submission, not by "latest row". */
    public function test_outputs_are_grouped_by_submission_and_can_be_superseded(): void
    {
        $step = $this->step();
        $make = fn (int $sub, string $url) => TaskStepOutput::create([
            'task_step_id' => $step->id,
            'added_by' => $this->employee->id,
            'submission_no' => $sub,
            'url' => $url,
        ]);

        $first = $make(1, 'https://drive.google.com/a');
        $second = $make(1, 'https://drive.google.com/b');
        $replacement = $make(2, 'https://drive.google.com/b-fixed');

        $second->update(['superseded_by_output_id' => $replacement->id]);

        $this->assertCount(2, $step->outputs()->where('submission_no', 1)->get());
        $this->assertTrue($second->fresh()->isSuperseded());
        $this->assertTrue($second->fresh()->supersededBy->is($replacement));
        $this->assertFalse($first->fresh()->isSuperseded());
    }

    public function test_an_output_cannot_supersede_itself(): void
    {
        $step = $this->step();
        $output = TaskStepOutput::create([
            'task_step_id' => $step->id,
            'added_by' => $this->employee->id,
            'url' => 'https://drive.google.com/x',
        ]);

        $this->expectException(QueryException::class);
        DB::table('task_step_outputs')->where('id', $output->id)
            ->update(['superseded_by_output_id' => $output->id]);
    }

    public function test_a_task_can_have_only_one_open_hold(): void
    {
        $task = Task::factory()->create();
        $make = fn () => TaskHold::create([
            'task_id' => $task->id,
            'created_by' => $this->tl->id,
            'reason' => 'Client is reviewing the script',
        ]);

        $make();

        $this->expectException(QueryException::class);
        $make();
    }

    public function test_a_hold_requires_a_non_empty_reason(): void
    {
        $task = Task::factory()->create();

        $this->expectException(QueryException::class);
        TaskHold::create([
            'task_id' => $task->id,
            'created_by' => $this->tl->id,
            'reason' => '   ',
        ]);
    }

    /** Q23 — a project hold owns the child task holds it created. */
    public function test_a_project_hold_owns_its_child_task_holds(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->inProject($project)->create();
        $manager = User::factory()->role(RoleCode::Manager)->create();

        $projectHold = ProjectHold::create([
            'project_id' => $project->id,
            'created_by' => $manager->id,
            'reason' => 'Client paused the engagement',
        ]);

        $taskHold = TaskHold::create([
            'task_id' => $task->id,
            'project_hold_id' => $projectHold->id,
            'created_by' => $manager->id,
            'reason' => 'Paused with the project',
        ]);

        $this->assertTrue($taskHold->isFromProjectHold());
        $this->assertTrue($projectHold->taskHolds->contains($taskHold));
        $this->assertTrue($project->openHold()->is($projectHold));
        $this->assertTrue($task->openHold()->is($taskHold));
    }

    public function test_a_project_can_have_only_one_open_hold(): void
    {
        $project = Project::factory()->create();
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $make = fn () => ProjectHold::create([
            'project_id' => $project->id,
            'created_by' => $manager->id,
            'reason' => 'Client paused the engagement',
        ]);

        $make();

        $this->expectException(QueryException::class);
        $make();
    }

    public function test_task_relationships_resolve(): void
    {
        $task = Task::factory()->create(['created_by' => $this->tl->id]);
        $step = $this->step(['task_id' => $task->id]);

        $task->update(['current_step_id' => $step->id]);

        $this->assertTrue($task->fresh()->currentStep->is($step));
        $this->assertTrue($task->fresh()->creator->is($this->tl));
        $this->assertTrue($step->task->is($task));
        $this->assertTrue($step->department->is($this->department));
        $this->assertTrue($this->department->taskSteps->contains($step));
        $this->assertTrue($this->tl->createdTasks->contains($task));
    }

    public function test_unfinished_tasks_exclude_completed_and_cancelled(): void
    {
        $project = Project::factory()->create();
        $manager = User::factory()->role(RoleCode::Manager)->create();

        $active = Task::factory()->inProject($project)->create();
        Task::factory()->inProject($project)->completed($manager)->create();
        Task::factory()->inProject($project)->cancelled($manager)->create();

        $unfinished = $project->unfinishedTasks();

        $this->assertCount(1, $unfinished);
        $this->assertTrue($unfinished->first()->is($active));
    }
}
