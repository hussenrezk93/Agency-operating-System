<?php

namespace Tests\Feature;

use App\Enums\TaskEvent;
use App\Enums\TaskLifecycle;
use App\Enums\WorkflowStatus;
use App\Exceptions\IllegalTransitionException;
use App\Exceptions\MissingOutputException;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * PHASE 1B SLICE 2 — the workflow engine end to end, plus every move it must refuse.
 * Drives TaskWorkflowService directly; the HTTP layer is covered in TaskAuthorizationTest.
 */
class TaskWorkflowTest extends TestCase
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

    // ------------------------------------------------------------------ create

    /** BRD §22.1 — the task lands on the department's Team Leader, never on an employee. */
    public function test_creating_a_task_opens_step_one_in_waiting_assignment(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->assertSame(TaskLifecycle::Active, $task->lifecycle_status);
        $this->assertCount(1, $task->steps);

        $step = $task->currentStep;
        $this->assertSame(1, $step->sequence_no);
        $this->assertSame($this->marketing->id, $step->department_id);
        $this->assertSame(WorkflowStatus::WaitingAssignment, $step->workflow_status);
        $this->assertNull($step->activeAssignment, 'no employee may be attached at creation');
    }

    public function test_task_codes_are_sequential_and_unique(): void
    {
        $first = $this->newTask($this->marketing, $this->manager);
        $second = $this->newTask($this->marketing, $this->manager);

        $this->assertMatchesRegularExpression('/^TSK-\d{4}-\d{5}$/', $first->task_code);
        $this->assertNotSame($first->task_code, $second->task_code);
    }

    public function test_reference_links_are_stored_with_the_task(): void
    {
        $task = $this->newTask($this->marketing, $this->manager, [
            'reference_links' => [
                ['url' => 'https://drive.example.com/brief', 'label' => 'Client brief'],
                ['url' => 'https://drive.example.com/logo'],
            ],
        ]);

        $this->assertCount(2, $task->referenceLinks);
    }

    public function test_a_task_cannot_be_created_for_an_inactive_department(): void
    {
        $closed = Department::factory()->create(['is_active' => false]);

        $this->expectException(ValidationException::class);
        $this->newTask($closed, $this->manager);
    }

    /** BRD §8 — a Manager may start a task in any active department. */
    public function test_a_manager_may_create_a_task_in_any_department(): void
    {
        $elsewhere = $this->makeDepartment('Elsewhere');

        $task = $this->newTask($elsewhere, $this->manager);

        $this->assertSame($elsewhere->id, $task->currentStep->department_id);
    }

    /** BRD §8 — a Team Leader starts a task from their own department without any route. */
    public function test_a_team_leader_may_create_a_task_in_their_own_department(): void
    {
        $task = $this->newTask($this->marketing, $this->leader);

        $this->assertSame($this->marketing->id, $task->currentStep->department_id);
    }

    /** BRD §8 — or from a department the Admin routing matrix allows them to reach. */
    public function test_a_team_leader_may_create_a_task_in_an_allowed_department(): void
    {
        $design = $this->makeDepartment('Design');
        $this->allowRoute($this->marketing, $design);

        $task = $this->newTask($design, $this->leader);

        $this->assertSame($design->id, $task->currentStep->department_id);
    }

    /** BRD §8 — but never a department outside their own and the allowed set. */
    public function test_a_team_leader_cannot_create_a_task_in_a_disallowed_department(): void
    {
        $elsewhere = $this->makeDepartment('Elsewhere');

        $this->expectException(ValidationException::class);
        $this->newTask($elsewhere, $this->leader);
    }

    // ------------------------------------------------------------------ assign

    /** Q8 — the deadline is written to the STEP, not only to the assignment. */
    public function test_assigning_moves_the_step_to_in_progress_and_sets_the_step_deadline(): void
    {
        [, $step, $assignment] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->assertSame(WorkflowStatus::InProgress, $step->workflow_status);
        $this->assertSame($this->employee->id, $assignment->assignee_id);
        $this->assertFalse($assignment->is_self_assigned);
        $this->assertNotNull($step->current_due_at);
        $this->assertSame('23:59', $step->current_due_at->format('H:i'), 'BRD §11 — 23:59 Africa/Cairo');
    }

    /** Q12 — a Team Leader naming themselves flags the step for Manager review. */
    public function test_a_team_leader_may_self_assign_and_the_step_is_flagged(): void
    {
        [, $step, $assignment] = $this->taskInProgress($this->marketing, $this->leader, $this->leader);

        $this->assertTrue($assignment->is_self_assigned);
        $this->assertTrue($step->isSelfAssigned());
    }

    public function test_an_employee_from_another_department_cannot_be_assigned(): void
    {
        $outsider = $this->makeEmployee($this->makeDepartment('Design'));
        $task = $this->newTask($this->marketing, $this->manager);

        $this->expectException(ValidationException::class);
        $this->workflow()->assign(
            $task->currentStep, $this->leader, $outsider,
            now()->toDateString(), now()->addDay()->toDateString(),
        );
    }

    public function test_a_disabled_account_cannot_receive_work(): void
    {
        $disabled = $this->makeEmployee($this->marketing);
        $disabled->forceFill(['status' => 'inactive'])->save();

        $task = $this->newTask($this->marketing, $this->manager);

        $this->expectException(ValidationException::class);
        $this->workflow()->assign(
            $task->currentStep, $this->leader, $disabled->refresh(),
            now()->toDateString(), now()->addDay()->toDateString(),
        );
    }

    /** BRD §9.3 — one open assignment per step; reassigning closes the previous one. */
    public function test_reassigning_closes_the_previous_assignment_and_keeps_the_review_state(): void
    {
        [, $step, $first] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $replacement = $this->makeEmployee($this->marketing);

        $second = $this->workflow()->reassign(
            $step, $this->leader, $replacement,
            now()->toDateString(), now()->addDays(5)->toDateString(),
            'Original assignee reassigned to an urgent shoot',
        );

        $this->assertNotNull($first->refresh()->ended_at);
        $this->assertSame('Original assignee reassigned to an urgent shoot', $first->end_reason);
        $this->assertNull($second->ended_at);
        $this->assertSame(1, $step->assignments()->whereNull('ended_at')->count());
        $this->assertSame(WorkflowStatus::InProgress, $step->refresh()->workflow_status);
    }

    // ------------------------------------------------------------------ submit

    public function test_the_employee_adds_an_output_then_submits_for_review(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $output = $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-1');
        $this->workflow()->submit($step, $this->employee);

        $this->assertSame(1, $output->submission_no);
        $this->assertFalse($output->is_final);
        $this->assertSame(WorkflowStatus::UnderReview, $step->refresh()->workflow_status);
        $this->assertNotNull($step->submitted_at);
    }

    /** BRD §22.2 — the rule the whole review cycle depends on. */
    public function test_submitting_without_an_output_fails(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->expectException(MissingOutputException::class);
        $this->workflow()->submit($step, $this->employee);
    }

    /**
     * An output from the REJECTED round does not satisfy the new one — otherwise
     * "request changes" could be closed by resubmitting untouched work.
     */
    public function test_resubmitting_requires_a_new_output_for_the_new_round(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->workflow()->requestChanges($step, $this->leader, 'The cut is 12 seconds too long.');

        $this->expectException(MissingOutputException::class);
        $this->workflow()->submit($step->refresh(), $this->employee);
    }

    public function test_a_step_cannot_be_submitted_twice(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->expectException(IllegalTransitionException::class);
        $this->workflow()->submit($step, $this->employee);
    }

    // ------------------------------------------------------------------ review

    /** Q25 — approval makes EVERY link of the approved round final, not just the newest. */
    public function test_approval_marks_the_whole_submission_final_and_closes_the_assignment(): void
    {
        [, $step, $assignment] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-a');
        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-b');
        $this->workflow()->submit($step, $this->employee);

        $this->workflow()->approve($step->refresh(), $this->leader, 'Approved as is.');

        $this->assertSame(WorkflowStatus::Approved, $step->refresh()->workflow_status);
        $this->assertNotNull($step->approved_at);
        $this->assertSame(2, $step->outputs()->where('is_final', true)->count());
        $this->assertNotNull($assignment->refresh()->ended_at);
        $this->assertSame('approved', $assignment->end_reason);
    }

    /** BRD §9.7 — the work returns to the SAME employee. */
    public function test_requesting_changes_returns_the_step_to_the_same_employee(): void
    {
        [, $step, $assignment] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $review = $this->workflow()->requestChanges($step, $this->leader, 'Colour grade is too warm.');

        $this->assertSame(WorkflowStatus::ChangesRequested, $step->refresh()->workflow_status);
        $this->assertNull($step->submitted_at);
        $this->assertNull($assignment->refresh()->ended_at, 'the assignee keeps the step');
        $this->assertSame($this->employee->id, $step->activeAssignment->assignee_id);
        $this->assertSame('Colour grade is too warm.', $review->comment);
    }

    public function test_requesting_changes_without_a_comment_fails(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->expectException(ValidationException::class);
        $this->workflow()->requestChanges($step, $this->leader, '   ');
    }

    public function test_the_second_round_is_tracked_and_can_be_approved(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->workflow()->requestChanges($step, $this->leader, 'Trim the intro.');
        $step->refresh();

        $this->assertSame(2, $step->currentSubmissionNo());

        $second = $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-2');
        $this->workflow()->submit($step->refresh(), $this->employee);
        $this->workflow()->approve($step->refresh(), $this->leader);

        $this->assertSame(2, $second->refresh()->submission_no);
        $this->assertTrue($second->is_final);
        $this->assertFalse(
            $step->outputs()->where('submission_no', 1)->first()->is_final,
            'the rejected round never becomes final',
        );
    }

    /** Q12 — the Team Leader may not judge their own work; the Manager does. */
    public function test_a_self_assigned_step_is_reviewed_by_the_manager_not_the_team_leader(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->leader);

        $this->assertTrue($this->manager->can('review', $step));
        $this->assertFalse($this->leader->can('review', $step));

        $this->workflow()->approve($step, $this->manager);
        $this->assertSame(WorkflowStatus::Approved, $step->refresh()->workflow_status);
    }

    // ---------------------------------------------------------------- transfer

    public function test_sending_to_the_next_department_closes_this_step_and_opens_the_next(): void
    {
        $design = $this->makeDepartment('Design');
        $this->allowRoute($this->marketing, $design);

        [$task, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $next = $this->workflow()->sendToNextDepartment($step, $this->leader, $design);

        $this->assertSame(WorkflowStatus::Approved, $step->refresh()->workflow_status);
        $this->assertNotNull($step->completed_at);
        $this->assertSame(2, $next->sequence_no);
        $this->assertSame($design->id, $next->department_id);
        $this->assertSame(WorkflowStatus::WaitingAssignment, $next->workflow_status);
        $this->assertSame($next->id, $task->refresh()->current_step_id);
    }

    public function test_a_step_cannot_be_transferred_before_approval(): void
    {
        $design = $this->makeDepartment('Design');
        $this->allowRoute($this->marketing, $design);

        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->expectException(IllegalTransitionException::class);
        $this->workflow()->sendToNextDepartment($step, $this->leader, $design);
    }

    // ---------------------------------------------------------------- complete

    public function test_the_final_approved_step_completes_the_task(): void
    {
        [$task, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $completed = $this->workflow()->completeTask($step, $this->leader);

        $this->assertSame(TaskLifecycle::Completed, $completed->lifecycle_status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame($this->leader->id, $completed->completed_by);
        $this->assertNotNull($step->refresh()->completed_at);
        $this->assertTrue($completed->isClosed());
    }

    /** The three moves the brief calls forbidden — none of them is reachable. */
    public function test_a_task_in_progress_cannot_be_completed_directly(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->expectException(IllegalTransitionException::class);
        $this->workflow()->completeTask($step, $this->leader);
    }

    public function test_a_freshly_created_task_cannot_be_completed(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->expectException(IllegalTransitionException::class);
        $this->workflow()->completeTask($task->currentStep, $this->leader);
    }

    public function test_a_submitted_task_awaiting_review_cannot_be_completed(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->expectException(IllegalTransitionException::class);
        $this->workflow()->completeTask($step, $this->leader);
    }

    /** BRD §9.10 — only the LAST step may finish the task. */
    public function test_an_earlier_approved_step_cannot_finish_the_task(): void
    {
        $design = $this->makeDepartment('Design');
        $this->allowRoute($this->marketing, $design);

        [, $first] = $this->taskApproved($this->marketing, $this->leader, $this->employee);
        $this->workflow()->sendToNextDepartment($first, $this->leader, $design);

        // The Manager is used so the attempt reaches the sequence guard: the Marketing
        // Team Leader no longer leads the step that currently holds the task, so they
        // would be refused by the policy first for a different reason.
        $this->expectException(IllegalTransitionException::class);
        $this->workflow()->completeTask($first->refresh(), $this->manager);
    }

    // ------------------------------------------------------- closed and cancelled

    /**
     * BRD §22.6 — a completed task is read-only forever. The refusal lands in the
     * authorization layer rather than the state machine, because "the task is closed" is
     * the first thing every policy asks; the assertion below proves both layers agree.
     */
    public function test_a_completed_task_accepts_no_further_workflow_action(): void
    {
        [$task, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);
        $this->workflow()->completeTask($step, $this->leader);
        $step->refresh();

        $this->assertFalse($this->employee->can('addOutput', $step));
        $this->assertFalse($this->employee->can('submit', $step));
        $this->assertFalse($this->leader->can('assign', $step));
        $this->assertFalse($this->manager->can('cancel', $task->refresh()));

        $this->expectException(AuthorizationException::class);
        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/late');
    }

    public function test_cancelling_closes_every_live_step_and_open_assignment(): void
    {
        [$task, $step, $assignment] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $cancelled = $this->workflow()->cancelTask($task, $this->manager, 'Client withdrew the campaign.');

        $this->assertSame(TaskLifecycle::Cancelled, $cancelled->lifecycle_status);
        $this->assertSame('Client withdrew the campaign.', $cancelled->cancelled_reason);
        $this->assertSame($this->manager->id, $cancelled->cancelled_by);
        $this->assertSame(WorkflowStatus::Cancelled, $step->refresh()->workflow_status);
        $this->assertNotNull($assignment->refresh()->ended_at);
    }

    public function test_a_cancelled_task_cannot_continue(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $this->workflow()->cancelTask($task, $this->manager, 'Duplicate request.');

        $this->expectException(AuthorizationException::class);
        $this->workflow()->submit($step->refresh(), $this->employee);
    }

    public function test_a_cancelled_task_cannot_be_cancelled_again(): void
    {
        [$task] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $this->workflow()->cancelTask($task, $this->manager, 'Duplicate request.');

        $this->expectException(AuthorizationException::class);
        $this->workflow()->cancelTask($task->refresh(), $this->manager, 'Again.');
    }

    /** Q23 — a held task pauses; the workflow must not advance while it does. */
    public function test_a_task_on_hold_accepts_no_workflow_action(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $task->forceFill(['lifecycle_status' => TaskLifecycle::OnHold->value])->save();

        $this->expectException(IllegalTransitionException::class);
        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/x');
    }

    // ----------------------------------------------------------------- history

    /** BRD §9 / §19 — the timeline records every transition with both ends of the move. */
    public function test_every_transition_is_recorded_in_the_task_timeline(): void
    {
        $design = $this->makeDepartment('Design');
        $this->allowRoute($this->marketing, $design);

        [$task, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);
        $this->workflow()->sendToNextDepartment($step, $this->leader, $design);

        $events = TaskStatusHistory::where('task_id', $task->id)
            ->pluck('event_type')
            ->map(fn ($e): string => $e instanceof TaskEvent ? $e->value : (string) $e)
            ->all();

        foreach ([
            TaskEvent::Created, TaskEvent::SentToDepartment, TaskEvent::Assigned,
            TaskEvent::OutputAdded, TaskEvent::Submitted, TaskEvent::Approved,
            TaskEvent::Transferred,
        ] as $expected) {
            $this->assertContains($expected->value, $events, "missing timeline event {$expected->value}");
        }
    }

    public function test_the_approval_entry_records_both_ends_of_the_transition(): void
    {
        [$task] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $entry = TaskStatusHistory::where('task_id', $task->id)
            ->where('event_type', TaskEvent::Approved->value)
            ->firstOrFail();

        $this->assertSame(WorkflowStatus::UnderReview->value, $entry->from_status);
        $this->assertSame(WorkflowStatus::Approved->value, $entry->to_status);
        $this->assertSame($this->leader->id, $entry->changed_by);
        $this->assertSame($this->marketing->id, $entry->department_id);
        $this->assertSame('tl', $entry->context['actor_role']);
    }

    public function test_a_change_request_records_its_mandatory_reason(): void
    {
        [$task, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);
        $this->workflow()->requestChanges($step, $this->leader, 'Subtitles are missing.');

        $entry = TaskStatusHistory::where('task_id', $task->id)
            ->where('event_type', TaskEvent::ChangesRequested->value)
            ->firstOrFail();

        $this->assertSame('Subtitles are missing.', $entry->reason);
    }

    // ----------------------------------------------------------------- project

    public function test_a_project_task_is_linked_to_its_project(): void
    {
        $project = Project::factory()->create();
        $this->addDepartmentToProject($project, $this->marketing);

        $task = $this->newTask($this->marketing, $this->manager, ['project_id' => $project->id]);

        $this->assertSame($project->id, $task->project_id);
        $this->assertTrue($project->tasks->contains(fn (Task $t): bool => $t->is($task)));
    }
}
