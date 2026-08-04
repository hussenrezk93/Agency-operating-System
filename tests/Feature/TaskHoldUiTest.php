<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * PHASE 1B SLICE 3 — the real Hold/Resume/Redirect forms on the task detail page, over
 * the classic browser path (same shape as TaskUiTest for the rest of the task screen).
 */
class TaskHoldUiTest extends TestCase
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

    public function test_the_task_page_offers_hold_to_the_manager_and_not_the_employee(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->actingAs($this->manager)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertViewHas('canHold', true)
            ->assertViewHas('canRedirect', true);

        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $employeeOwnTask = $step->task;

        $this->actingAs($this->employee)->get(route('tasks.show', $employeeOwnTask))
            ->assertOk()
            ->assertViewHas('canHold', false)
            ->assertViewHas('canRedirect', false);
    }

    public function test_a_classic_hold_redirects_with_a_flash_message(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->actingAs($this->manager)
            ->post(route('tasks.hold', $task), ['reason' => 'Client requested a pause'])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame('on_hold', $task->fresh()->lifecycle_status->value);
    }

    public function test_a_classic_hold_without_a_reason_redirects_back_with_errors(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->actingAs($this->manager)
            ->post(route('tasks.hold', $task), [])
            ->assertRedirect()
            ->assertSessionHasErrors('reason');
    }

    public function test_a_classic_resume_redirects_with_a_flash_message(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $this->workflow()->hold($task, $this->manager, 'Pausing');

        $this->actingAs($this->manager)
            ->post(route('tasks.resume', $task))
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame('active', $task->fresh()->lifecycle_status->value);
    }

    public function test_a_classic_redirect_moves_the_task_to_the_target_department(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->actingAs($this->manager)
            ->post(route('tasks.redirect', $task), [
                'department_id' => $this->design->id,
                'reason' => 'Wrong department, correcting',
            ])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame($this->design->id, $task->fresh()->currentStep->department_id);
    }

    public function test_a_team_leader_cannot_redirect(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->actingAs($this->leader)
            ->post(route('tasks.redirect', $task), [
                'department_id' => $this->design->id,
                'reason' => 'Trying anyway',
            ])
            ->assertForbidden();
    }
}
