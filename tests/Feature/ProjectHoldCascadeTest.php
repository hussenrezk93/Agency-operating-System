<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * Q23 — closing the gap ProjectService's own docblock named: holding a project now
 * pauses its unfinished tasks, tagged with `project_hold_id`, and resuming the project
 * resumes exactly those (never a task that was already held individually beforehand).
 */
class ProjectHoldCascadeTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment();
        $this->manager = $this->makeManager();

        $this->project = Project::factory()->create(['created_by' => $this->manager->id]);
        $this->addDepartmentToProject($this->project, $this->marketing);
    }

    private function projects(): ProjectService
    {
        return app(ProjectService::class);
    }

    public function test_holding_a_project_pauses_its_unfinished_tasks_and_tags_the_hold(): void
    {
        $task = $this->newTask($this->marketing, $this->manager, ['project_id' => $this->project->id]);

        $held = $this->projects()->hold($this->project, 'Client asked us to pause', $this->manager);
        $projectHold = $held->openHold();

        $this->assertTrue($task->fresh()->isOnHold());
        $this->assertDatabaseHas('task_holds', [
            'task_id' => $task->id,
            'project_hold_id' => $projectHold->id,
            'ended_at' => null,
        ]);
    }

    public function test_a_task_already_held_individually_is_left_alone_by_the_cascade(): void
    {
        $individuallyHeld = $this->newTask($this->marketing, $this->manager, ['project_id' => $this->project->id]);
        $this->workflow()->hold($individuallyHeld, $this->manager, 'Paused before the project was');

        $this->projects()->hold($this->project, 'Project-wide pause', $this->manager);

        $this->assertDatabaseHas('task_holds', [
            'task_id' => $individuallyHeld->id,
            'project_hold_id' => null,
            'reason' => 'Paused before the project was',
        ]);
    }

    public function test_resuming_the_project_resumes_only_the_cascaded_holds(): void
    {
        $individuallyHeld = $this->newTask($this->marketing, $this->manager, ['project_id' => $this->project->id]);
        $this->workflow()->hold($individuallyHeld, $this->manager, 'Paused before the project was');

        $cascaded = $this->newTask($this->marketing, $this->manager, ['project_id' => $this->project->id]);

        $this->projects()->hold($this->project, 'Project-wide pause', $this->manager);
        $this->projects()->resume($this->project, $this->manager);

        $this->assertTrue($individuallyHeld->fresh()->isOnHold(), 'the pre-existing individual hold must survive the project resume');
        $this->assertFalse($cascaded->fresh()->isOnHold());
    }

    public function test_creating_a_task_under_an_on_hold_project_is_refused(): void
    {
        $this->projects()->hold($this->project, 'Awaiting sign-off', $this->manager);

        $this->expectException(ValidationException::class);

        $this->newTask($this->marketing, $this->manager, ['project_id' => $this->project->id]);
    }

    /** BAC#10 — cancelling a project cancels every one of its unfinished tasks too. */
    public function test_cancelling_a_project_cancels_its_unfinished_tasks(): void
    {
        $task = $this->newTask($this->marketing, $this->manager, ['project_id' => $this->project->id]);

        $this->projects()->cancel($this->project, 'Client cancelled the engagement', $this->manager);

        $this->assertTrue($task->fresh()->isClosed());
        $this->assertSame('cancelled', $task->fresh()->lifecycle_status->value);
    }

    public function test_cancelling_a_project_leaves_an_already_completed_task_alone(): void
    {
        $employee = $this->makeEmployee($this->marketing);
        $leader = $this->makeTeamLeader($this->marketing);
        $task = $this->newTask($this->marketing, $this->manager, ['project_id' => $this->project->id]);
        $step = $task->currentStep;

        $this->workflow()->assign($step, $leader, $employee, now()->toDateString(), now()->addDays(3)->toDateString());
        $this->workflow()->addOutput($step, $employee, 'https://drive.example.com/final');
        $this->workflow()->submit($step, $employee);
        $this->workflow()->approve($step, $leader);
        $this->workflow()->approve($step->refresh(), $this->manager);
        $this->workflow()->completeTask($step->fresh(), $leader);

        $this->projects()->cancel($this->project, 'Wrapping up', $this->manager);

        $this->assertSame('completed', $task->fresh()->lifecycle_status->value);
    }

    public function test_cancelling_a_project_leaves_an_individually_held_task_alone(): void
    {
        $individuallyHeld = $this->newTask($this->marketing, $this->manager, ['project_id' => $this->project->id]);
        $this->workflow()->hold($individuallyHeld, $this->manager, 'Paused before the project was');

        $this->projects()->cancel($this->project, 'Client cancelled the engagement', $this->manager);

        $this->assertTrue($individuallyHeld->fresh()->isOnHold());
    }
}
