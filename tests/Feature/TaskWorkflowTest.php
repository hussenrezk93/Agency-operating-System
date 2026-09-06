<?php

namespace Tests\Feature;

use App\Enums\DepartmentSpecialRole;
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

    /** Product decision 2026-09 — a reference link slot can be an uploaded image instead. */
    public function test_a_reference_link_can_be_stored_as_an_uploaded_image(): void
    {
        $task = $this->newTask($this->marketing, $this->manager, [
            'reference_links' => [
                ['url' => 'task-references/brief.jpg', 'is_upload' => true],
            ],
        ]);

        $link = $task->referenceLinks->sole();
        $this->assertTrue($link->is_upload);
        $this->assertStringContainsString('task-references/brief.jpg', $link->displayUrl());
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

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $this->employee->id,
            'action' => 'task_step.output_added',
            'entity_type' => 'task_step',
            'entity_id' => $step->id,
        ]);
    }

    /** Product decision 2026-09 — an output can be an uploaded image instead of a link. */
    public function test_an_uploaded_output_stores_the_path_and_the_upload_flag(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $output = $this->workflow()->addOutput($step, $this->employee, 'task-outputs/photo.jpg', isUpload: true);

        $this->assertTrue($output->is_upload);
        $this->assertSame('task-outputs/photo.jpg', $output->url);
        $this->assertStringContainsString('task-outputs/photo.jpg', $output->displayUrl());
    }

    public function test_a_link_output_is_not_flagged_as_an_upload(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $output = $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-1');

        $this->assertFalse($output->is_upload);
        $this->assertSame('https://drive.example.com/cut-1', $output->displayUrl());
    }

    /** Product decision 2026-09 — an uploaded output can be a video, detected off the extension. */
    public function test_an_uploaded_video_output_is_detected_as_a_video(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $video = $this->workflow()->addOutput($step, $this->employee, 'task-outputs/cut.mp4', isUpload: true);
        $image = $this->workflow()->addOutput($step->refresh(), $this->employee, 'task-outputs/cut.jpg', isUpload: true);

        $this->assertTrue($video->isVideo());
        $this->assertFalse($image->isVideo());
    }

    public function test_superseding_an_output_points_the_old_row_at_the_replacement_and_is_audited(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $original = $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-1');
        $replacement = $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-1-fixed');

        $this->workflow()->supersedeOutput($original, $replacement, $this->employee);

        $this->assertSame($replacement->id, $original->fresh()->superseded_by_output_id);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $this->employee->id,
            'action' => 'task_step.output_superseded',
            'entity_type' => 'task_step',
            'entity_id' => $step->id,
        ]);
    }

    public function test_the_employee_removes_an_output_they_added_by_mistake_before_submitting(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $wrong = $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/wrong-link');
        $this->workflow()->removeOutput($wrong, $this->employee);

        $this->assertNotNull($wrong->fresh()->removed_at);
        $this->assertTrue($step->outputsForCurrentSubmission()->isEmpty());

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $this->employee->id,
            'action' => 'task_step.output_removed',
            'entity_type' => 'task_step',
            'entity_id' => $step->id,
        ]);
    }

    /**
     * The assignee stays "current" through Under Review (submit() doesn't end the
     * assignment), so they may still add or remove a link while the reviewer hasn't
     * decided yet — this is the whole point of widening past isEditableByAssignee().
     */
    public function test_the_employee_can_still_add_and_remove_outputs_while_under_review(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $extra = $this->workflow()->addOutput($step->refresh(), $this->employee, 'https://drive.example.com/cut-2');
        $this->assertSame(WorkflowStatus::UnderReview, $step->refresh()->workflow_status);

        $this->workflow()->removeOutput($extra, $this->employee);
        $this->assertNotNull($extra->fresh()->removed_at);
    }

    public function test_a_removed_output_does_not_satisfy_the_submission_requirement(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $output = $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-1');
        $this->workflow()->removeOutput($output, $this->employee);

        $this->expectException(MissingOutputException::class);
        $this->workflow()->submit($step->refresh(), $this->employee);
    }

    /**
     * Q25 — once approved, retracting a link from the final round is too late. In
     * practice the assignment itself has already ended by then (approve() closes it),
     * so the coarse policy check ("are you still the current assignee") refuses this
     * before the finality check is ever reached — same authorization-first precedent
     * documented on TaskStepPolicy.
     */
    public function test_an_approved_outputs_link_cannot_be_removed(): void
    {
        [, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $final = $step->outputs()->where('is_final', true)->firstOrFail();

        $this->expectException(AuthorizationException::class);
        $this->workflow()->removeOutput($final, $this->employee);
    }

    public function test_removing_an_already_removed_output_fails(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $output = $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-1');
        $this->workflow()->removeOutput($output, $this->employee);

        $this->expectException(IllegalTransitionException::class);
        $this->workflow()->removeOutput($output->fresh(), $this->employee);
    }

    /** Same authority as addOutput() — only the current assignee, never the department TL. */
    public function test_only_the_assignee_may_remove_an_output(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $output = $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-1');

        $this->expectException(AuthorizationException::class);
        $this->workflow()->removeOutput($output, $this->leader);
    }

    /** BRD §22.2 — the rule the whole review cycle depends on. */
    public function test_submitting_without_an_output_fails(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->expectException(MissingOutputException::class);
        $this->workflow()->submit($step, $this->employee);
    }

    /** Photography/Videography's work often has no shareable link — submitting empty is allowed there. */
    public function test_the_photography_videography_department_may_submit_without_an_output(): void
    {
        $photography = $this->makeDepartment('Photography');
        $photography->forceFill(['special_role' => DepartmentSpecialRole::PhotographyVideography])->save();
        $leader = $this->makeTeamLeader($photography);
        $employee = $this->makeEmployee($photography);

        [, $step] = $this->taskInProgress($photography, $leader, $employee);

        $submitted = $this->workflow()->submit($step, $employee);

        $this->assertSame(WorkflowStatus::UnderReview, $submitted->workflow_status);
    }

    /** Product decision 2026-09 — Moderator publishes off-system, so it has no link to
     *  attach either; the exemption covers it alongside Photography/Videography. */
    public function test_the_moderator_department_may_submit_without_an_output(): void
    {
        $moderator = $this->makeDepartment('Moderator');
        $moderator->forceFill(['special_role' => DepartmentSpecialRole::Moderator])->save();
        $leader = $this->makeTeamLeader($moderator);
        $employee = $this->makeEmployee($moderator);

        [, $step] = $this->taskInProgress($moderator, $leader, $employee);

        $submitted = $this->workflow()->submit($step, $employee);

        $this->assertSame(WorkflowStatus::UnderReview, $submitted->workflow_status);
    }

    /** Every other department still owes an output — the exemption is not a free-for-all. */
    public function test_a_department_with_another_special_role_still_needs_an_output(): void
    {
        $content = $this->makeDepartment('Content');
        $content->forceFill(['special_role' => DepartmentSpecialRole::Content])->save();
        $leader = $this->makeTeamLeader($content);
        $employee = $this->makeEmployee($content);

        [, $step] = $this->taskInProgress($content, $leader, $employee);

        $this->expectException(MissingOutputException::class);
        $this->workflow()->submit($step, $employee);
    }

    /**
     * 2026-08 decision, reversing the original "must add something new" rule: the
     * assignee may resubmit the exact same link after "request changes" without
     * re-adding anything — the round-1 output is carried forward to round 2 and
     * shows up as this round's own output, satisfying submit() and later becoming
     * final on approval, same as if it had been added fresh.
     */
    public function test_resubmitting_without_a_new_output_carries_the_previous_links_forward(): void
    {
        [$task, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);
        $original = $step->outputs()->sole();

        $this->workflow()->requestChanges($step, $this->leader, 'The cut is 12 seconds too long.');

        $this->assertTrue($step->refresh()->outputsForCurrentSubmission()->contains('id', $original->id));

        $resubmitted = $this->workflow()->submit($step->refresh(), $this->employee);
        $this->assertSame(WorkflowStatus::UnderReview, $resubmitted->workflow_status);
        $this->assertSame(2, $original->fresh()->submission_no);

        $this->workflow()->approve($step->refresh(), $this->leader);
        $this->workflow()->approve($step->refresh(), $this->manager);
        $this->assertTrue($original->fresh()->is_final);
    }

    /** Carry-forward only fires when the round adds nothing of its own — removing the
     *  carried link and adding nothing new still requires something live to resubmit. */
    public function test_resubmitting_still_fails_if_the_carried_output_was_removed(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);
        $original = $step->outputs()->sole();

        $this->workflow()->requestChanges($step, $this->leader, 'The cut is 12 seconds too long.');
        $this->workflow()->removeOutput($original->fresh(), $this->employee);

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

    /** TL approval no longer finalizes anything by itself — it just hands off to the
     *  mandatory Manager stage; PendingManagerReview keeps the assignment open exactly
     *  like UnderReview does, for the same output-management reason. */
    public function test_tl_approval_hands_off_to_manager_review_without_finalizing_anything(): void
    {
        [, $step, $assignment] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-a');
        $this->workflow()->submit($step, $this->employee);

        $this->workflow()->approve($step->refresh(), $this->leader, 'Looks good to me.');

        $this->assertSame(WorkflowStatus::PendingManagerReview, $step->refresh()->workflow_status);
        $this->assertNull($step->approved_at);
        $this->assertSame(0, $step->outputs()->where('is_final', true)->count());
        $this->assertNull($assignment->refresh()->ended_at, 'the assignee keeps the step through Manager review too');
    }

    /** Q25 — approval makes EVERY link of the approved round final, not just the newest.
     *  Only the MANAGER's decision (the true terminal Approved) finalizes anything. */
    public function test_manager_approval_marks_the_whole_submission_final_and_closes_the_assignment(): void
    {
        [, $step, $assignment] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-a');
        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-b');
        $this->workflow()->submit($step, $this->employee);

        $this->workflow()->approve($step->refresh(), $this->leader);
        $this->workflow()->approve($step->refresh(), $this->manager, 'Approved as is.');

        $this->assertSame(WorkflowStatus::Approved, $step->refresh()->workflow_status);
        $this->assertNotNull($step->approved_at);
        $this->assertSame(2, $step->outputs()->where('is_final', true)->count());
        $this->assertNotNull($assignment->refresh()->ended_at);
        $this->assertSame('approved', $assignment->end_reason);
    }

    /** Only a Manager may act at PendingManagerReview — not the TL, not the assignee,
     *  not an Admin. */
    public function test_only_a_manager_may_act_at_pending_manager_review(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);
        $this->workflow()->approve($step, $this->leader);
        $step->refresh();

        $this->assertTrue($this->manager->can('review', $step));
        $this->assertFalse($this->leader->can('review', $step));
        $this->assertFalse($this->employee->can('review', $step));

        $this->expectException(AuthorizationException::class);
        $this->workflow()->approve($step, $this->leader);
    }

    /** A self-assigned step needs the Manager at BOTH stages — Q12 at UnderReview
     *  (unchanged), and mandatorily at PendingManagerReview regardless either way. */
    public function test_a_self_assigned_step_needs_the_manager_at_both_stages(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->leader);

        $this->assertTrue($this->manager->can('review', $step));
        $this->assertFalse($this->leader->can('review', $step));

        $this->workflow()->approve($step, $this->manager);
        $step->refresh();
        $this->assertSame(WorkflowStatus::PendingManagerReview, $step->workflow_status);

        $this->workflow()->approve($step, $this->manager);
        $this->assertSame(WorkflowStatus::Approved, $step->refresh()->workflow_status);
    }

    // ------------------------------------------------- Content reviews Graphic

    /**
     * Product decision 2026-09 — Graphic's work passes Content before the Manager, so
     * its chain is UnderReview → PendingContentReview → PendingManagerReview → Approved.
     * Keyed on special_role, never the department's name.
     */
    public function test_graphic_work_goes_to_content_before_the_manager(): void
    {
        [$graphic, $graphicLeader, $designer, $contentLeader] = $this->graphicAndContent();
        [, $step] = $this->taskUnderReview($graphic, $graphicLeader, $designer);

        $this->workflow()->approve($step, $graphicLeader);
        $this->assertSame(WorkflowStatus::PendingContentReview, $step->refresh()->workflow_status);

        $this->workflow()->approve($step, $contentLeader);
        $this->assertSame(WorkflowStatus::PendingManagerReview, $step->refresh()->workflow_status);
        $this->assertNull($step->approved_at, 'Content does not finalize anything either');

        $this->workflow()->approve($step, $this->manager);
        $this->assertSame(WorkflowStatus::Approved, $step->refresh()->workflow_status);
    }

    /** Every other department keeps the two-stage chain untouched. */
    public function test_a_non_graphic_department_never_enters_content_review(): void
    {
        $this->graphicAndContent();
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->workflow()->approve($step, $this->leader);

        $this->assertSame(WorkflowStatus::PendingManagerReview, $step->refresh()->workflow_status);
    }

    /** Only Content's own leader decides at that stage — not Graphic's leader (who
     *  already had their turn), not the assignee, not an Admin. */
    public function test_only_the_content_leader_may_act_at_content_review(): void
    {
        [$graphic, $graphicLeader, $designer, $contentLeader] = $this->graphicAndContent();
        [, $step] = $this->taskUnderReview($graphic, $graphicLeader, $designer);
        $this->workflow()->approve($step, $graphicLeader);
        $step->refresh();

        $this->assertTrue($contentLeader->can('review', $step));
        $this->assertFalse($graphicLeader->can('review', $step));
        $this->assertFalse($designer->can('review', $step));
        $this->assertFalse($this->makeAdmin()->can('review', $step));
        $this->assertFalse($this->manager->can('review', $step), 'the Manager waits for their own stage');

        $this->expectException(AuthorizationException::class);
        $this->workflow()->approve($step, $graphicLeader);
    }

    /** A change request from Content goes back to the designer, not to the Manager, and
     *  the resubmission re-enters the full Graphic → Content → Manager chain. */
    public function test_content_requesting_changes_returns_the_step_to_the_assignee(): void
    {
        [$graphic, $graphicLeader, $designer, $contentLeader] = $this->graphicAndContent();
        [, $step] = $this->taskUnderReview($graphic, $graphicLeader, $designer);
        $this->workflow()->approve($step, $graphicLeader);
        $step->refresh();

        $this->workflow()->requestChanges($step, $contentLeader, 'The headline copy is off-brand.');
        $this->assertSame(WorkflowStatus::ChangesRequested, $step->refresh()->workflow_status);

        $this->assertSame(
            TaskEvent::ContentChangesRequested,
            TaskStatusHistory::where('task_step_id', $step->id)->latest('id')->first()->event_type,
        );

        $this->workflow()->addOutput($step, $designer, 'https://drive.example.com/key-visual-v2');
        $this->workflow()->submit($step->refresh(), $designer);
        $this->workflow()->approve($step->refresh(), $graphicLeader);

        $this->assertSame(WorkflowStatus::PendingContentReview, $step->refresh()->workflow_status);
    }

    /** The history has to name WHO approved at each stage — a Content sign-off recorded
     *  as a plain "approved" would be indistinguishable from the Graphic TL's own. */
    public function test_a_content_approval_is_recorded_as_its_own_event(): void
    {
        [$graphic, $graphicLeader, $designer, $contentLeader] = $this->graphicAndContent();
        [, $step] = $this->taskUnderReview($graphic, $graphicLeader, $designer);

        $this->workflow()->approve($step, $graphicLeader);
        $this->workflow()->approve($step->refresh(), $contentLeader);

        $entry = TaskStatusHistory::where('task_step_id', $step->id)
            ->where('event_type', TaskEvent::ContentApproved->value)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(WorkflowStatus::PendingContentReview->value, $entry->from_status);
        $this->assertSame(WorkflowStatus::PendingManagerReview->value, $entry->to_status);
    }

    /** The reviewer must be able to OPEN a task that never touched their department —
     *  but only for as long as their turn lasts. */
    public function test_the_content_leader_sees_a_graphic_task_only_while_it_awaits_their_review(): void
    {
        [$graphic, $graphicLeader, $designer, $contentLeader] = $this->graphicAndContent();
        [$task, $step] = $this->taskUnderReview($graphic, $graphicLeader, $designer);

        $this->assertFalse($contentLeader->can('view', $task->refresh()), 'not their turn yet');

        $this->workflow()->approve($step, $graphicLeader);
        $this->assertTrue($contentLeader->can('view', $task->refresh()));

        $this->assertTrue($contentLeader->can('viewComments', $step->refresh()), 'the reviewer reads the thread');

        $this->workflow()->approve($step->refresh(), $contentLeader);
        $this->assertFalse($contentLeader->can('view', $task->refresh()), 'the turn is over');
    }

    /**
     * Graphic + Content with their leaders and one designer.
     *
     * @return array{0: Department, 1: User, 2: User, 3: User}
     */
    private function graphicAndContent(): array
    {
        $graphic = $this->makeDepartment('Graphic');
        $graphic->forceFill(['special_role' => DepartmentSpecialRole::Graphic->value])->save();

        $content = $this->makeDepartment('Content');
        $content->forceFill(['special_role' => DepartmentSpecialRole::Content->value])->save();

        return [
            $graphic,
            $this->makeTeamLeader($graphic),
            $this->makeEmployee($graphic),
            $this->makeTeamLeader($content),
        ];
    }

    /** Whichever stage requests changes, resubmission re-enters UnderReview and needs
     *  the full TL-then-Manager cycle again — no special-casing either stage. */
    public function test_manager_requesting_changes_sends_the_step_back_and_reenters_the_full_cycle(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);
        $this->workflow()->approve($step, $this->leader);
        $step->refresh();

        $review = $this->workflow()->requestChanges($step, $this->manager, 'The audio levels are off.');
        $this->assertSame(WorkflowStatus::ChangesRequested, $step->refresh()->workflow_status);
        $this->assertSame('The audio levels are off.', $review->comment);

        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/cut-2');
        $this->workflow()->submit($step->refresh(), $this->employee);
        $this->assertSame(WorkflowStatus::UnderReview, $step->refresh()->workflow_status);

        $this->workflow()->approve($step->refresh(), $this->leader);
        $this->assertSame(WorkflowStatus::PendingManagerReview, $step->refresh()->workflow_status);
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
        $this->workflow()->approve($step->refresh(), $this->manager);

        $this->assertSame(2, $second->refresh()->submission_no);
        $this->assertTrue($second->is_final);
        $this->assertFalse(
            $step->outputs()->where('submission_no', 1)->first()->is_final,
            'the rejected round never becomes final',
        );
    }

    /** Q12 — the Team Leader may not judge their own work at the UnderReview stage;
     *  the Manager does — and then reviews it again at PendingManagerReview, same as
     *  every other step. */
    public function test_a_self_assigned_step_is_reviewed_by_the_manager_not_the_team_leader(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->leader);

        $this->assertTrue($this->manager->can('review', $step));
        $this->assertFalse($this->leader->can('review', $step));

        $this->workflow()->approve($step, $this->manager);
        $this->assertSame(WorkflowStatus::PendingManagerReview, $step->refresh()->workflow_status);
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

    /** A TL's own approval is not enough — still blocked until the Manager also
     *  reviews it, same as before the TL approved at all. */
    public function test_a_step_cannot_be_transferred_while_only_the_tl_has_approved(): void
    {
        $design = $this->makeDepartment('Design');
        $this->allowRoute($this->marketing, $design);

        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);
        $this->workflow()->approve($step, $this->leader);
        $step->refresh();

        $this->assertSame(WorkflowStatus::PendingManagerReview, $step->workflow_status);

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

    /** Same as transfer — a TL's own approval alone is not enough to finish either. */
    public function test_a_task_cannot_be_completed_while_only_the_tl_has_approved(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);
        $this->workflow()->approve($step, $this->leader);

        $this->expectException(IllegalTransitionException::class);
        $this->workflow()->completeTask($step->refresh(), $this->leader);
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
            TaskEvent::ManagerApproved, TaskEvent::Transferred,
        ] as $expected) {
            $this->assertContains($expected->value, $events, "missing timeline event {$expected->value}");
        }
    }

    /** TaskEvent::Approved is now the TL-stage decision — it hands off to Manager
     *  review, it doesn't reach the true terminal Approved by itself. */
    public function test_the_tl_approval_entry_records_both_ends_of_the_transition(): void
    {
        [$task] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $entry = TaskStatusHistory::where('task_id', $task->id)
            ->where('event_type', TaskEvent::Approved->value)
            ->firstOrFail();

        $this->assertSame(WorkflowStatus::UnderReview->value, $entry->from_status);
        $this->assertSame(WorkflowStatus::PendingManagerReview->value, $entry->to_status);
        $this->assertSame($this->leader->id, $entry->changed_by);
        $this->assertSame($this->marketing->id, $entry->department_id);
        $this->assertSame('tl', $entry->context['actor_role']);
    }

    /** TaskEvent::ManagerApproved is the Manager-stage decision — the one that
     *  actually reaches the true terminal Approved. */
    public function test_the_manager_approval_entry_records_both_ends_of_the_transition(): void
    {
        [$task] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $entry = TaskStatusHistory::where('task_id', $task->id)
            ->where('event_type', TaskEvent::ManagerApproved->value)
            ->firstOrFail();

        $this->assertSame(WorkflowStatus::PendingManagerReview->value, $entry->from_status);
        $this->assertSame(WorkflowStatus::Approved->value, $entry->to_status);
        $this->assertSame($this->systemManager()->id, $entry->changed_by);
        $this->assertSame('manager', $entry->context['actor_role']);
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
