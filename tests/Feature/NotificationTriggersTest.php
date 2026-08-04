<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use App\Services\DeadlineService;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * PHASE 7 — every workflow trigger from the Phase 0 plan's §10 notification table that
 * is in scope for this phase, proven end to end: the domain event fires, its listener
 * resolves the right recipient(s), and a real `Notification` row lands for them.
 */
class NotificationTriggersTest extends TestCase
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

    private function notified(User $user, string $type): bool
    {
        return Notification::where('user_id', $user->id)->where('type', $type)->exists();
    }

    public function test_assigning_notifies_the_new_assignee(): void
    {
        $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->assertTrue($this->notified($this->employee, 'task.assigned'));
    }

    public function test_reassigning_notifies_the_previous_assignee(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $other = $this->makeEmployee($this->marketing);

        $this->workflow()->reassign($step, $this->leader, $other, now()->toDateString(), now()->addDays(2)->toDateString(), 'Reassigning');

        $this->assertTrue($this->notified($other, 'task.assigned'));
        $this->assertTrue($this->notified($this->employee, 'task.reassigned_away'));
    }

    public function test_first_seen_notifies_the_department_leader(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->workflow()->markSeen($step, $this->employee);

        $this->assertTrue($this->notified($this->leader, 'task.first_seen'));
    }

    public function test_submitting_notifies_the_department_leader(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/out');
        $this->workflow()->submit($step, $this->employee);

        $this->assertTrue($this->notified($this->leader, 'task.submitted'));
    }

    public function test_a_self_assigned_submission_notifies_the_manager_instead(): void
    {
        $task = $this->newTask($this->marketing, $this->leader);
        $step = $task->currentStep;
        $this->workflow()->assign($step, $this->leader, $this->leader, now()->toDateString(), now()->addDays(2)->toDateString());
        $this->workflow()->addOutput($step, $this->leader, 'https://drive.example.com/out');

        $this->workflow()->submit($step, $this->leader);

        $this->assertTrue($this->notified($this->manager, 'task.submitted'));
        $this->assertFalse($this->notified($this->leader, 'task.submitted'));
    }

    public function test_a_disabled_manager_is_excluded_from_the_self_assigned_review_notice(): void
    {
        $disabledManager = $this->makeManager();
        $disabledManager->forceFill(['status' => 'inactive'])->save();

        $task = $this->newTask($this->marketing, $this->leader);
        $step = $task->currentStep;
        $this->workflow()->assign($step, $this->leader, $this->leader, now()->toDateString(), now()->addDays(2)->toDateString());
        $this->workflow()->addOutput($step, $this->leader, 'https://drive.example.com/out');

        $this->workflow()->submit($step, $this->leader);

        $this->assertTrue($this->notified($this->manager, 'task.submitted'));
        $this->assertFalse($this->notified($disabledManager, 'task.submitted'));
    }

    public function test_approval_notifies_the_assignee(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->workflow()->approve($step, $this->leader);

        $this->assertTrue($this->notified($this->employee, 'task.approved'));
    }

    public function test_changes_requested_notifies_the_assignee(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->workflow()->requestChanges($step, $this->leader, 'Please redo the intro');

        $this->assertTrue($this->notified($this->employee, 'task.changes_requested'));
    }

    public function test_transferring_notifies_the_receiving_departments_leader(): void
    {
        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);
        $this->allowRoute($this->marketing, $this->design);
        $designLeader = $this->makeTeamLeader($this->design);

        $this->workflow()->sendToNextDepartment($step, $this->leader, $this->design);

        $this->assertTrue($this->notified($designLeader, 'task.arrived'));
    }

    public function test_completing_notifies_the_creator(): void
    {
        [$task, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->workflow()->completeTask($step, $this->leader);

        $this->assertTrue($this->notified($task->creator, 'task.completed'));
    }

    public function test_holding_notifies_the_assignee_and_leader(): void
    {
        [$task] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->workflow()->hold($task, $this->manager, 'Pausing for client input');

        $this->assertTrue($this->notified($this->employee, 'task.held'));
        $this->assertTrue($this->notified($this->leader, 'task.held'));
    }

    public function test_resuming_notifies_the_assignee_and_leader(): void
    {
        [$task] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $this->workflow()->hold($task, $this->manager, 'Pausing');

        $this->workflow()->resume($task->fresh(), $this->manager);

        $this->assertTrue($this->notified($this->employee, 'task.resumed'));
        $this->assertTrue($this->notified($this->leader, 'task.resumed'));
    }

    public function test_redirecting_notifies_both_departments_leaders_and_the_creator(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $designLeader = $this->makeTeamLeader($this->design);

        $this->workflow()->redirect($task, $this->manager, $this->design, 'Wrong department');

        $this->assertTrue($this->notified($designLeader, 'task.redirected'));
        $this->assertTrue($this->notified($this->manager, 'task.redirected'));
    }

    public function test_cancelling_notifies_the_creator_and_open_assignee(): void
    {
        [$task] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->workflow()->cancelTask($task, $this->manager, 'No longer needed');

        $this->assertTrue($this->notified($task->creator, 'task.cancelled'));
        $this->assertTrue($this->notified($this->employee, 'task.cancelled'));
    }

    public function test_a_step_entering_due_soon_notifies_the_assignee_and_leader(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        app(DeadlineService::class)->recomputeStep($step, $step->current_due_at->clone()->subHours(1));

        $this->assertTrue($this->notified($this->employee, 'task.due_soon'));
        $this->assertTrue($this->notified($this->leader, 'task.due_soon'));
    }

    public function test_a_step_becoming_overdue_notifies_the_assignee_and_leader(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        app(DeadlineService::class)->recomputeStep($step, $step->current_due_at->clone()->addDay());

        $this->assertTrue($this->notified($this->employee, 'task.overdue'));
        $this->assertTrue($this->notified($this->leader, 'task.overdue'));
    }

    public function test_cancelling_a_project_notifies_participating_department_leaders(): void
    {
        $project = Project::factory()->create(['created_by' => $this->manager->id]);
        $this->addDepartmentToProject($project, $this->marketing);

        app(ProjectService::class)->cancel($project, 'Client withdrew', $this->manager);

        $this->assertTrue($this->notified($this->leader, 'project.cancelled'));
    }

    /**
     * Q28 — email is never sent synchronously from the trigger itself (it's deferred to
     * the digest sweep, on top of Q28), so a workflow transition never touches mail at
     * all; and separately, a mail transport failure at digest time is recorded on the
     * delivery row without crashing the sweep (see NotificationDigestSweepTest for the
     * dedicated coverage of that half).
     */
    public function test_a_workflow_transition_never_sends_mail_itself(): void
    {
        Mail::shouldReceive('to')->never();

        $task = $this->newTask($this->marketing, $this->manager);
        $step = $task->currentStep;

        $this->workflow()->assign($step, $this->leader, $this->employee, now()->toDateString(), now()->addDays(2)->toDateString());

        $this->assertSame('in_progress', $step->fresh()->workflow_status->value);

        $notification = Notification::where('user_id', $this->employee->id)->where('type', 'task.assigned')->firstOrFail();
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'channel' => 'email',
            'status' => 'queued',
        ]);
    }
}
