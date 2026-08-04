<?php

namespace App\Services;

use App\Enums\DeadlineStatus;
use App\Enums\Priority;
use App\Enums\ReviewDecision;
use App\Enums\RoleCode;
use App\Enums\TaskEvent;
use App\Enums\TaskLifecycle;
use App\Enums\WorkflowStatus;
use App\Events\TaskCancelled;
use App\Events\TaskCompleted;
use App\Events\TaskHeld;
use App\Events\TaskRedirected;
use App\Events\TaskResumed;
use App\Events\TaskStepAssigned;
use App\Events\TaskStepFirstSeen;
use App\Events\TaskStepReviewed;
use App\Events\TaskStepSubmitted;
use App\Events\TaskStepTransferred;
use App\Exceptions\IllegalTransitionException;
use App\Exceptions\MissingOutputException;
use App\Exceptions\WorkflowException;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectHold;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\TaskStep;
use App\Models\TaskStepAssignment;
use App\Models\TaskStepComment;
use App\Models\TaskStepOutput;
use App\Models\TaskStepReview;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * PHASE 1B SLICE 2 — the ONLY writer of `task_steps.workflow_status`,
 * `tasks.lifecycle_status`, assignments, outputs, reviews and `task_status_history`.
 *
 * WHY THE SERVICE AUTHORIZES AS WELL AS THE CONTROLLER
 * The permission rules are DEFINED once, in TaskPolicy / TaskStepPolicy. They are
 * ENFORCED at two boundaries: the Form Request (so an unauthorized HTTP call is refused
 * before validation) and here (so no future console command, queue job or seeder can
 * reach a transition without them). That is one definition with two enforcement points,
 * not two copies of the rule — every method below delegates to the same policy.
 *
 * VOCABULARY (approved decision Q6 — the ERD is authoritative)
 *   "Assigned"        → WorkflowStatus::InProgress
 *   "Submitted"       → WorkflowStatus::UnderReview, with `submitted_at` recording when
 *   "Next Department" → a NEW step; the finished step stays Approved
 *   "Completed"       → tasks.lifecycle_status, never a step status
 * No new status value was added and no table was altered.
 *
 * PHASE 1B SLICE 3 — hold/resume accounting (Q5/Q9/Q23) and Manager redirect (BRD §10)
 * now live here too, alongside the transitions above: this class already owns
 * `lifecycle_status` and every `task_status_history` write, so pausing/resuming/
 * redirecting a task go through the same single writer instead of a second one.
 * `deadline_status` is moved to NotStarted → OnTime → Closed → Paused here; every
 * time-driven move (OnTime → DueSoon → Overdue) belongs to DeadlineService alone.
 *
 * DEFERRED, DELIBERATELY: notifications and email (Q27/Q28), the temporary-TL task
 * transfer (Q10) and review hand-back (Q7/Q17).
 */
class TaskWorkflowService
{
    public function __construct(
        private readonly TaskRoutingService $routing,
        private readonly AuditService $audit,
        private readonly DeadlineService $deadline,
    ) {}

    // ---------------------------------------------------------------- create

    /**
     * BRD §8 — the Manager or a Team Leader creates the task and names the first
     * department. The task enters the cycle immediately in Active: the first step is
     * created in Waiting Assignment so it lands on that department's effective Team
     * Leader, never directly on an employee (BRD §22.1).
     *
     * The draft/publish pair described in BRD §8 is NOT part of this slice; a task
     * created here is always Active.
     *
     * @param  array{title: string, brief: string, notes?: ?string, priority?: string,
     *               project_id?: ?int, first_department_id: int,
     *               reference_links?: array<int, array{url: string, label?: ?string}>}  $data
     */
    public function createTask(User $actor, array $data): Task
    {
        Gate::forUser($actor)->authorize('create', Task::class);

        $department = Department::findOrFail($data['first_department_id']);

        if (! $department->is_active) {
            throw ValidationException::withMessages([
                'first_department_id' => __('The receiving department is not active.'),
            ]);
        }

        $this->assertFirstDepartmentAllowed($department, $actor);

        // Q23 — a project on hold (or closed) accepts no new tasks.
        if (isset($data['project_id'])) {
            $project = Project::findOrFail($data['project_id']);

            if (! $project->acceptsNewTasks()) {
                throw ValidationException::withMessages([
                    'project_id' => __('This project does not accept new tasks right now.'),
                ]);
            }
        }

        return DB::transaction(function () use ($actor, $data, $department): Task {
            $task = Task::create([
                'task_code' => $this->nextTaskCode(),
                'project_id' => $data['project_id'] ?? null,
                'title' => $data['title'],
                'brief' => $data['brief'],
                'notes' => $data['notes'] ?? null,
                'priority' => $data['priority'] ?? Priority::Medium->value,
                'lifecycle_status' => TaskLifecycle::Active->value,
                'created_by' => $actor->id,
            ]);

            foreach ($data['reference_links'] ?? [] as $link) {
                $task->referenceLinks()->create([
                    'added_by' => $actor->id,
                    'url' => $link['url'],
                    'label' => $link['label'] ?? null,
                ]);
            }

            $step = $this->openStep($task, $department, sequenceNo: 1);

            $this->record($task, $step, TaskEvent::Created, $actor, null, null, null, [
                'first_department_id' => $department->id,
                'priority' => $task->priority->value,
                'project_id' => $task->project_id,
            ]);

            $this->record($task, $step, TaskEvent::SentToDepartment, $actor,
                null, WorkflowStatus::WaitingAssignment, null,
                ['department_id' => $department->id, 'sequence_no' => 1],
            );

            $this->audit->log(
                action: 'task.created',
                entityType: 'task',
                entityId: $task->id,
                after: [
                    'task_code' => $task->task_code,
                    'first_department_id' => $department->id,
                    'project_id' => $task->project_id,
                ],
                actorId: $actor->id,
            );

            return $task->refresh();
        });
    }

    // ---------------------------------------------------------------- assign

    /**
     * BRD §9.3 + approved decision Q21 — ONLY the effective Team Leader of the receiving
     * department picks the person and the dates. The Manager may not (TaskStepPolicy).
     * A Team Leader may name themselves, which flags the step for Manager review (Q12).
     *
     * Q8 — the authoritative deadline is written to the STEP as well as the assignment,
     * so replacing the employee later cannot silently move the department's commitment.
     */
    public function assign(
        TaskStep $step,
        User $actor,
        User $assignee,
        string $startDate,
        string $dueDate,
    ): TaskStepAssignment {
        Gate::forUser($actor)->authorize('assign', $step);

        $this->assertTaskMutable($step->task);
        $this->assertStepStatus($step, WorkflowStatus::WaitingAssignment);
        $this->assertTransitionAllowed($step, WorkflowStatus::InProgress);
        $this->assertAssignable($step, $assignee, $actor);

        return DB::transaction(function () use ($step, $actor, $assignee, $startDate, $dueDate): TaskStepAssignment {
            $assignment = $this->openAssignment($step, $actor, $assignee, $startDate, $dueDate);

            $this->moveStep($step, WorkflowStatus::InProgress, [
                'current_start_date' => $startDate,
                'current_due_at' => $this->dueAt($dueDate),
                'deadline_status' => DeadlineStatus::OnTime->value,
            ]);

            $this->record($step->task, $step, TaskEvent::Assigned, $actor,
                WorkflowStatus::WaitingAssignment, WorkflowStatus::InProgress, null, [
                    'assignee_id' => $assignee->id,
                    'is_self_assigned' => $assignment->is_self_assigned,
                    'start_date' => $startDate,
                    'due_date' => $dueDate,
                ],
            );

            $this->audit->log(
                action: 'task_step.assigned',
                entityType: 'task_step',
                entityId: $step->id,
                after: [
                    'assignee_id' => $assignee->id,
                    'due_date' => $dueDate,
                    'is_self_assigned' => $assignment->is_self_assigned,
                ],
                actorId: $actor->id,
            );

            TaskStepAssigned::dispatch($step, $assignment);

            return $assignment;
        });
    }

    /**
     * BRD §11 — replacing the employee requires a fresh deadline for the new one. The
     * step's review state is untouched: only who is working on it changes, so a step that
     * was in Changes Requested stays there and the new assignee inherits the same round.
     */
    public function reassign(
        TaskStep $step,
        User $actor,
        User $assignee,
        string $startDate,
        string $dueDate,
        string $reason,
    ): TaskStepAssignment {
        Gate::forUser($actor)->authorize('assign', $step);

        $this->assertTaskMutable($step->task);
        $this->assertStepStatus($step, WorkflowStatus::InProgress, WorkflowStatus::ChangesRequested);
        $this->assertAssignable($step, $assignee, $actor);

        return DB::transaction(function () use ($step, $actor, $assignee, $startDate, $dueDate, $reason): TaskStepAssignment {
            $previous = $step->activeAssignment;

            $previous?->forceFill([
                'ended_at' => now(),
                'end_reason' => $reason,
            ])->save();

            $assignment = $this->openAssignment($step, $actor, $assignee, $startDate, $dueDate);

            $step->forceFill([
                'current_start_date' => $startDate,
                'current_due_at' => $this->dueAt($dueDate),
            ])->save();

            $this->record($step->task, $step, TaskEvent::Reassigned, $actor,
                $step->workflow_status, $step->workflow_status, $reason, [
                    'previous_assignee_id' => $previous?->assignee_id,
                    'assignee_id' => $assignee->id,
                    'due_date' => $dueDate,
                ],
            );

            $this->audit->log(
                action: 'task_step.reassigned',
                entityType: 'task_step',
                entityId: $step->id,
                before: ['assignee_id' => $previous?->assignee_id],
                after: ['assignee_id' => $assignee->id, 'due_date' => $dueDate, 'reason' => $reason],
                actorId: $actor->id,
            );

            TaskStepAssigned::dispatch($step->refresh(), $assignment);

            return $assignment;
        });
    }

    // ------------------------------------------------------------ first seen

    /**
     * Approved decision Q19 / CR-006 — there is no Seen button. `first_seen_at` is
     * written exactly once and a later open never overwrites it.
     *
     * @return bool true when this call is the one that wrote the timestamp
     */
    public function markSeen(TaskStep $step, User $actor): bool
    {
        Gate::forUser($actor)->authorize('markSeen', $step);

        $assignment = $step->activeAssignment;

        if ($assignment === null || $assignment->hasBeenSeen()) {
            return false;
        }

        return DB::transaction(function () use ($step, $actor, $assignment): bool {
            // Re-read under the transaction so two concurrent opens cannot both write.
            $fresh = TaskStepAssignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->first();

            if ($fresh === null || $fresh->first_seen_at !== null) {
                return false;
            }

            $seenAt = now();
            $fresh->forceFill(['first_seen_at' => $seenAt])->save();

            $this->record($step->task, $step, TaskEvent::FirstSeen, $actor, null, null, null, [
                'assignee_id' => $fresh->assignee_id,
                'seen_at' => $seenAt->toIso8601String(),
            ]);

            $this->audit->log(
                action: 'task_step.first_seen',
                entityType: 'task_step',
                entityId: $step->id,
                after: ['assignee_id' => $fresh->assignee_id, 'seen_at' => $seenAt->toIso8601String()],
                actorId: $actor->id,
            );

            TaskStepFirstSeen::dispatch($step, $fresh);

            return true;
        });
    }

    /**
     * CR-006 (approved change to the BRD wording) — opening My Tasks marks the employee's
     * own eligible unseen steps as seen. Idempotent, and it never touches another user's
     * assignment, a closed task, or a step that is no longer live.
     *
     * @return int how many timestamps this call wrote
     */
    public function markMyTasksSeen(User $actor): int
    {
        $eligible = TaskStepAssignment::query()
            ->whereNull('ended_at')
            ->whereNull('first_seen_at')
            ->where('assignee_id', $actor->id)
            ->with('step.task')
            ->get();

        $written = 0;

        foreach ($eligible as $assignment) {
            $step = $assignment->step;

            if ($step === null || $step->workflow_status->isTerminal()) {
                continue;
            }

            if ($step->task === null || $step->task->isClosed()) {
                continue;
            }

            // Skip anything the policy would refuse instead of aborting the whole sweep:
            // opening My Tasks must never fail because one row became ineligible.
            if (Gate::forUser($actor)->denies('markSeen', $step)) {
                continue;
            }

            if ($this->markSeen($step, $actor)) {
                $written++;
            }
        }

        return $written;
    }

    // --------------------------------------------------------------- outputs

    /**
     * BRD §13 — links only; no file storage in v1. Outputs are immutable, so a correction
     * adds a new row. Q25: the row carries the submission round it belongs to, because
     * approving a submission makes ALL of that round's links final, not just the latest.
     */
    public function addOutput(TaskStep $step, User $actor, string $url, ?string $label = null): TaskStepOutput
    {
        Gate::forUser($actor)->authorize('addOutput', $step);

        $this->assertTaskMutable($step->task);
        $this->assertStepStatus($step, WorkflowStatus::InProgress, WorkflowStatus::ChangesRequested);

        return DB::transaction(function () use ($step, $actor, $url, $label): TaskStepOutput {
            $output = $step->outputs()->create([
                'added_by' => $actor->id,
                'submission_no' => $step->currentSubmissionNo(),
                'url' => $url,
                'label' => $label,
                'is_final' => false,
            ]);

            $this->record($step->task, $step, TaskEvent::OutputAdded, $actor, null, null, null, [
                'output_id' => $output->id,
                'submission_no' => $output->submission_no,
            ]);

            $this->audit->log(
                action: 'task_step.output_added',
                entityType: 'task_step',
                entityId: $step->id,
                after: ['output_id' => $output->id, 'submission_no' => $output->submission_no],
                actorId: $actor->id,
            );

            return $output;
        });
    }

    /** A correction: add the replacement first, then point the old row at it (BRD §13). */
    public function supersedeOutput(TaskStepOutput $old, TaskStepOutput $replacement, User $actor): void
    {
        Gate::forUser($actor)->authorize('addOutput', $old->step);

        if ($old->is_final) {
            throw new IllegalTransitionException(__('An approved final output cannot be superseded.'));
        }

        $old->forceFill(['superseded_by_output_id' => $replacement->id])->save();

        $this->audit->log(
            action: 'task_step.output_superseded',
            entityType: 'task_step',
            entityId: $old->task_step_id,
            before: ['output_id' => $old->id],
            after: ['output_id' => $old->id, 'superseded_by_output_id' => $replacement->id],
            actorId: $actor->id,
        );
    }

    /**
     * BRD §13 — stage comments are a private thread between the assignee and the
     * department's effective Team Leader; they are not a workflow transition, so they
     * are never written to the task_status_history timeline (TaskStepPolicy::addComment
     * mirrors viewComments()'s "who" rule).
     */
    public function addComment(TaskStep $step, User $actor, string $body): TaskStepComment
    {
        Gate::forUser($actor)->authorize('addComment', $step);

        $comment = $step->comments()->create([
            'author_id' => $actor->id,
            'body' => $body,
        ]);

        $this->audit->log(
            action: 'task_step.comment_added',
            entityType: 'task_step',
            entityId: $step->id,
            after: ['comment_id' => $comment->id],
            actorId: $actor->id,
        );

        return $comment;
    }

    // ---------------------------------------------------------------- submit

    /**
     * BRD §9.5 / §22.2 — submitting requires at least one output in the CURRENT round.
     * Links carried over from an earlier rejected round do not satisfy it, which is what
     * stops "request changes" from being closed by resubmitting the same work untouched.
     */
    public function submit(TaskStep $step, User $actor): TaskStep
    {
        Gate::forUser($actor)->authorize('submit', $step);

        $this->assertTaskMutable($step->task);
        $this->assertStepStatus($step, WorkflowStatus::InProgress, WorkflowStatus::ChangesRequested);
        $this->assertTransitionAllowed($step, WorkflowStatus::UnderReview);

        $submissionNo = $step->currentSubmissionNo();
        $isResubmission = $step->workflow_status === WorkflowStatus::ChangesRequested;

        $outputCount = $step->outputs()
            ->where('submission_no', $submissionNo)
            ->whereNull('superseded_by_output_id')
            ->count();

        if ($outputCount === 0) {
            throw MissingOutputException::forSubmission($submissionNo);
        }

        return DB::transaction(function () use ($step, $actor, $submissionNo, $isResubmission, $outputCount): TaskStep {
            $from = $step->workflow_status;

            $this->moveStep($step, WorkflowStatus::UnderReview, ['submitted_at' => now()]);

            $this->record($step->task, $step,
                $isResubmission ? TaskEvent::Resubmitted : TaskEvent::Submitted,
                $actor, $from, WorkflowStatus::UnderReview, null,
                ['submission_no' => $submissionNo, 'output_count' => $outputCount],
            );

            $this->audit->log(
                action: 'task_step.submitted',
                entityType: 'task_step',
                entityId: $step->id,
                after: ['submission_no' => $submissionNo, 'output_count' => $outputCount],
                actorId: $actor->id,
            );

            TaskStepSubmitted::dispatch($step, $submissionNo);

            return $step;
        });
    }

    // ---------------------------------------------------------------- review

    /**
     * BRD §9.6 / §22.3 + approved decision Q12 — the effective Team Leader reviews, except
     * on a step the Team Leader assigned to themselves, which only a Manager may judge.
     * Q25: approval marks every link of the approved round final.
     */
    public function approve(TaskStep $step, User $actor, ?string $comment = null): TaskStepReview
    {
        Gate::forUser($actor)->authorize('review', $step);

        $this->assertTaskMutable($step->task);
        $this->assertStepStatus($step, WorkflowStatus::UnderReview);
        $this->assertTransitionAllowed($step, WorkflowStatus::Approved);

        $submissionNo = $step->currentSubmissionNo();

        return DB::transaction(function () use ($step, $actor, $comment, $submissionNo): TaskStepReview {
            $review = $step->reviews()->create([
                'reviewer_id' => $actor->id,
                'submission_no' => $submissionNo,
                'decision' => ReviewDecision::Approved->value,
                'comment' => $comment,
            ]);

            // Q25 — the whole approved round becomes final, not only the newest link.
            $step->outputs()
                ->where('submission_no', $submissionNo)
                ->whereNull('superseded_by_output_id')
                ->update(['is_final' => true]);

            // The assignee's work on this step is finished.
            $step->activeAssignment?->forceFill([
                'ended_at' => now(),
                'end_reason' => 'approved',
            ])->save();

            $this->moveStep($step, WorkflowStatus::Approved, ['approved_at' => now()]);

            $this->record($step->task, $step, TaskEvent::Approved, $actor,
                WorkflowStatus::UnderReview, WorkflowStatus::Approved, $comment,
                ['submission_no' => $submissionNo, 'reviewer_role' => $actor->roleCode()->value],
            );

            $this->audit->log(
                action: 'task_step.approved',
                entityType: 'task_step',
                entityId: $step->id,
                after: ['submission_no' => $submissionNo, 'reviewer_id' => $actor->id],
                actorId: $actor->id,
            );

            TaskStepReviewed::dispatch($step, $review);

            return $review;
        });
    }

    /**
     * BRD §9.7 — the step goes back to the SAME employee and the comment is mandatory.
     * The database enforces the comment too (`tsr_comment_required_check`); rejecting it
     * here first turns a constraint violation into a readable message.
     */
    public function requestChanges(TaskStep $step, User $actor, string $comment): TaskStepReview
    {
        Gate::forUser($actor)->authorize('review', $step);

        $this->assertTaskMutable($step->task);
        $this->assertStepStatus($step, WorkflowStatus::UnderReview);
        $this->assertTransitionAllowed($step, WorkflowStatus::ChangesRequested);

        if (trim($comment) === '') {
            throw ValidationException::withMessages([
                'comment' => __('A comment is required when requesting changes.'),
            ]);
        }

        $submissionNo = $step->currentSubmissionNo();

        return DB::transaction(function () use ($step, $actor, $comment, $submissionNo): TaskStepReview {
            $review = $step->reviews()->create([
                'reviewer_id' => $actor->id,
                'submission_no' => $submissionNo,
                'decision' => ReviewDecision::ChangesRequested->value,
                'comment' => $comment,
            ]);

            $this->moveStep($step, WorkflowStatus::ChangesRequested, ['submitted_at' => null]);

            $this->record($step->task, $step, TaskEvent::ChangesRequested, $actor,
                WorkflowStatus::UnderReview, WorkflowStatus::ChangesRequested, $comment,
                [
                    'submission_no' => $submissionNo,
                    'returned_to_assignee_id' => $step->activeAssignment?->assignee_id,
                ],
            );

            $this->audit->log(
                action: 'task_step.changes_requested',
                entityType: 'task_step',
                entityId: $step->id,
                after: ['submission_no' => $submissionNo, 'reviewer_id' => $actor->id],
                actorId: $actor->id,
            );

            TaskStepReviewed::dispatch($step, $review);

            return $review;
        });
    }

    // -------------------------------------------------------------- transfer

    /**
     * BRD §9.8 / §9.9 — after approval the current Team Leader either sends the work on or
     * finishes the task. Sending on closes this step and opens the next one; the finished
     * step keeps its Approved status, which is why "Transferred" is not a status (Q6).
     */
    public function sendToNextDepartment(
        TaskStep $step,
        User $actor,
        Department $target,
        ?string $reason = null,
    ): TaskStep {
        Gate::forUser($actor)->authorize('transfer', $step);

        $this->assertTaskMutable($step->task);
        $this->assertStepStatus($step, WorkflowStatus::Approved);
        $this->routing->assertTransferAllowed($step, $target);

        return DB::transaction(function () use ($step, $actor, $target, $reason): TaskStep {
            $task = $step->task;

            $this->closeStep($step);

            $next = $this->openStep($task, $target, $this->nextSequenceNo($task));

            $this->record($task, $step, TaskEvent::Transferred, $actor,
                WorkflowStatus::Approved, WorkflowStatus::Approved, $reason, [
                    'from_department_id' => $step->department_id,
                    'to_department_id' => $target->id,
                    'next_step_id' => $next->id,
                ],
            );

            $this->record($task, $next, TaskEvent::SentToDepartment, $actor,
                null, WorkflowStatus::WaitingAssignment, $reason, [
                    'department_id' => $target->id,
                    'sequence_no' => $next->sequence_no,
                ],
            );

            $this->audit->log(
                action: 'task_step.transferred',
                entityType: 'task',
                entityId: $task->id,
                before: ['department_id' => $step->department_id, 'step_id' => $step->id],
                after: ['department_id' => $target->id, 'step_id' => $next->id, 'reason' => $reason],
                actorId: $actor->id,
            );

            TaskStepTransferred::dispatch($step, $next);

            return $next;
        });
    }

    // -------------------------------------------------------------- complete

    /**
     * BRD §9.10 / §22.6 — Finish Task is available only from an APPROVED step that is the
     * last one on the task. A task in progress, submitted, or awaiting review can never
     * jump to Completed, and a completed task is read-only forever.
     */
    public function completeTask(TaskStep $step, User $actor): Task
    {
        $task = $step->task;

        Gate::forUser($actor)->authorize('complete', $task);

        $this->assertTaskMutable($task);
        $this->assertStepStatus($step, WorkflowStatus::Approved);

        $lastSequenceNo = (int) $task->steps()->max('sequence_no');

        if ($step->sequence_no !== $lastSequenceNo) {
            throw IllegalTransitionException::notFinalStep($step->sequence_no, $lastSequenceNo);
        }

        return DB::transaction(function () use ($task, $step, $actor): Task {
            $this->closeStep($step);

            $task->forceFill([
                'lifecycle_status' => TaskLifecycle::Completed->value,
                'completed_at' => now(),
                'completed_by' => $actor->id,
                'current_step_id' => $step->id,
            ])->save();

            $this->record($task, $step, TaskEvent::Completed, $actor,
                WorkflowStatus::Approved, null, null,
                ['final_step_id' => $step->id, 'steps' => $task->steps()->count()],
            );

            $this->audit->log(
                action: 'task.completed',
                entityType: 'task',
                entityId: $task->id,
                after: ['completed_by' => $actor->id, 'final_step_id' => $step->id],
                actorId: $actor->id,
            );

            TaskCompleted::dispatch($task->refresh());

            return $task;
        });
    }

    // ---------------------------------------------------------------- cancel

    /**
     * BRD §10 — the Manager or the task's creator cancels, with a mandatory reason. Every
     * live step is closed as Cancelled and every open assignment is ended, so nothing is
     * left pointing at work nobody will do. A cancelled task is never reopened.
     */
    public function cancelTask(Task $task, User $actor, string $reason): Task
    {
        Gate::forUser($actor)->authorize('cancel', $task);

        $this->assertTaskMutable($task);

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => __('A reason is required when cancelling a task.'),
            ]);
        }

        return DB::transaction(function () use ($task, $actor, $reason): Task {
            foreach ($task->steps as $step) {
                if ($step->workflow_status->isTerminal()) {
                    continue;
                }

                $from = $step->workflow_status;

                $step->activeAssignment?->forceFill([
                    'ended_at' => now(),
                    'end_reason' => 'task cancelled',
                ])->save();

                $this->moveStep($step, WorkflowStatus::Cancelled, [
                    'deadline_status' => DeadlineStatus::Closed->value,
                ]);

                $this->record($task, $step, TaskEvent::Cancelled, $actor,
                    $from, WorkflowStatus::Cancelled, $reason,
                    ['department_id' => $step->department_id],
                );
            }

            $task->forceFill([
                'lifecycle_status' => TaskLifecycle::Cancelled->value,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancelled_reason' => $reason,
            ])->save();

            $this->audit->log(
                action: 'task.cancelled',
                entityType: 'task',
                entityId: $task->id,
                after: ['cancelled_by' => $actor->id, 'reason' => $reason],
                actorId: $actor->id,
            );

            $cancelled = $task->refresh();
            TaskCancelled::dispatch($cancelled);

            return $cancelled;
        });
    }

    // ------------------------------------------------------------ hold / resume

    /**
     * BRD §10/§11 — the Manager or the task's creator pauses the deadline clock with a
     * mandatory reason. Only the current live step's clock is paused; every earlier step
     * is already Approved/Redirected/Cancelled and its `deadline_status` stays Closed.
     *
     * $projectHold is set only when this hold was caused by a project-level hold (Q23) —
     * see ProjectService::hold(). A task held individually never carries one.
     */
    public function hold(Task $task, User $actor, string $reason, ?ProjectHold $projectHold = null): Task
    {
        $task->refresh();

        Gate::forUser($actor)->authorize('hold', $task);

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => __('A reason is required when placing a task on hold.'),
            ]);
        }

        return DB::transaction(function () use ($task, $actor, $reason, $projectHold): Task {
            $hold = $task->holds()->create([
                'created_by' => $actor->id,
                'project_hold_id' => $projectHold?->id,
                'reason' => $reason,
                'started_at' => now(),
            ]);

            $step = $task->currentStep;

            if ($step !== null && ! $step->workflow_status->isTerminal()) {
                $step->forceFill(['deadline_status' => DeadlineStatus::Paused->value])->save();
            }

            $task->forceFill(['lifecycle_status' => TaskLifecycle::OnHold->value])->save();

            $this->record($task, $step, TaskEvent::OnHold, $actor, null, null, $reason, [
                'hold_id' => $hold->id,
                'cascaded_from_project_hold_id' => $projectHold?->id,
            ]);

            $this->audit->log(
                action: 'task.held',
                entityType: 'task',
                entityId: $task->id,
                after: ['reason' => $reason, 'hold_id' => $hold->id],
                actorId: $actor->id,
            );

            $held = $task->refresh();
            TaskHeld::dispatch($held);

            return $held;
        });
    }

    /**
     * BRD §11 / Q5/Q9 — resuming ends the open hold, stores how long it lasted, and
     * extends the current step's due date (and its active assignment's due date, Q8) by
     * exactly that duration. Never guesses from wall-clock re-derivation later.
     */
    public function resume(Task $task, User $actor): Task
    {
        $task->refresh();

        Gate::forUser($actor)->authorize('resume', $task);

        return DB::transaction(function () use ($task, $actor): Task {
            $hold = $task->openHold();
            $pausedSeconds = $hold !== null ? max(0, (int) round($hold->started_at->diffInSeconds(now()))) : 0;

            $hold?->forceFill([
                'ended_at' => now(),
                'resumed_by' => $actor->id,
                'paused_seconds' => $pausedSeconds,
            ])->save();

            // Flip the task back to Active FIRST — DeadlineService::recomputeStep() skips
            // any step whose task is still on hold, so the reclassification below would
            // be silently ignored if it ran before this write.
            $task->forceFill(['lifecycle_status' => TaskLifecycle::Active->value])->save();

            $step = $task->currentStep;

            if ($step !== null && $step->current_due_at !== null) {
                $newDueAt = $step->current_due_at->clone()->addSeconds($pausedSeconds);

                $step->forceFill(['current_due_at' => $newDueAt])->save();
                $step->activeAssignment?->forceFill(['due_date' => $newDueAt->toDateString()])->save();

                $this->deadline->recomputeStep($step->refresh());
            } elseif ($step !== null) {
                $step->forceFill(['deadline_status' => DeadlineStatus::NotStarted->value])->save();
            }

            $this->record($task, $step, TaskEvent::Resumed, $actor, null, null, null, [
                'hold_id' => $hold?->id,
                'paused_seconds' => $pausedSeconds,
            ]);

            $this->audit->log(
                action: 'task.resumed',
                entityType: 'task',
                entityId: $task->id,
                after: ['hold_id' => $hold?->id, 'paused_seconds' => $pausedSeconds],
                actorId: $actor->id,
            );

            $resumed = $task->refresh();
            TaskResumed::dispatch($resumed);

            return $resumed;
        });
    }

    // -------------------------------------------------------------- redirect

    /**
     * BRD §10 — a Manager-only correction that bypasses the routing matrix entirely
     * (unlike sendToNextDepartment/TaskRoutingService). It may target a department the
     * task has already visited; the database forbids only redirecting a step to itself.
     * Available from any live (non-terminal) step — once a step is Approved the normal
     * tool is Transfer, not Redirect.
     */
    public function redirect(Task $task, User $actor, Department $target, string $reason): TaskStep
    {
        Gate::forUser($actor)->authorize('redirect', $task);

        $this->assertTaskMutable($task);

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => __('A reason is required when redirecting a task.'),
            ]);
        }

        if (! $target->is_active) {
            throw ValidationException::withMessages([
                'department_id' => __('The target department is not active.'),
            ]);
        }

        $current = $task->currentStep;

        if ($current === null) {
            throw new WorkflowException(__('The task has no live step to redirect.'));
        }

        $this->assertTransitionAllowed($current, WorkflowStatus::Redirected);

        if ($target->id === $current->department_id) {
            throw ValidationException::withMessages([
                'department_id' => __('The task is already in this department.'),
            ]);
        }

        return DB::transaction(function () use ($task, $actor, $target, $current, $reason): TaskStep {
            $from = $current->workflow_status;

            $current->activeAssignment?->forceFill([
                'ended_at' => now(),
                'end_reason' => 'redirected',
            ])->save();

            $this->moveStep($current, WorkflowStatus::Redirected, [
                'deadline_status' => DeadlineStatus::Closed->value,
            ]);

            $next = $this->openStep($task, $target, $this->nextSequenceNo($task));

            $redirect = $task->redirects()->create([
                'from_step_id' => $current->id,
                'to_step_id' => $next->id,
                'to_department_id' => $target->id,
                'redirected_by' => $actor->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            $this->record($task, $current, TaskEvent::Redirected, $actor,
                $from, WorkflowStatus::Redirected, $reason, [
                    'redirect_id' => $redirect->id,
                    'from_department_id' => $current->department_id,
                    'to_department_id' => $target->id,
                    'next_step_id' => $next->id,
                ],
            );

            $this->record($task, $next, TaskEvent::SentToDepartment, $actor,
                null, WorkflowStatus::WaitingAssignment, $reason, [
                    'department_id' => $target->id,
                    'sequence_no' => $next->sequence_no,
                ],
            );

            $this->audit->log(
                action: 'task.redirected',
                entityType: 'task',
                entityId: $task->id,
                before: ['department_id' => $current->department_id, 'step_id' => $current->id],
                after: ['department_id' => $target->id, 'step_id' => $next->id, 'reason' => $reason],
                actorId: $actor->id,
            );

            TaskRedirected::dispatch($task, $current, $next);

            return $next;
        });
    }

    // ------------------------------------------------------------ guard rails

    /** BRD §8 — a Team Leader starts a task only from their own department or one they're allowed to route to. */
    private function assertFirstDepartmentAllowed(Department $department, User $actor): void
    {
        if (! $actor->hasRole(RoleCode::TeamLeader)) {
            return;
        }

        $allowed = array_merge(
            [$actor->department_id],
            Department::find($actor->department_id)?->allowedNextDepartmentIds() ?? [],
        );

        if (! in_array($department->id, $allowed, true)) {
            throw ValidationException::withMessages([
                'first_department_id' => __('You may only start a task in your own department or a department you are allowed to route to.'),
            ]);
        }
    }

    /** Completed, cancelled and on-hold tasks accept no workflow action. */
    private function assertTaskMutable(Task $task): void
    {
        // Always evaluate the latest lifecycle state; a step may hold a stale task relation.
        $task->refresh();

        if ($task->isClosed()) {
            throw IllegalTransitionException::taskClosed($task->lifecycle_status);
        }

        if ($task->isOnHold()) {
            throw IllegalTransitionException::taskOnHold();
        }
    }

    private function assertStepStatus(TaskStep $step, WorkflowStatus ...$expected): void
    {
        if (! in_array($step->workflow_status, $expected, true)) {
            throw IllegalTransitionException::wrongStatus($step->workflow_status, $expected);
        }
    }

    private function assertTransitionAllowed(TaskStep $step, WorkflowStatus $target): void
    {
        if (! $step->workflow_status->canTransitionTo($target)) {
            throw IllegalTransitionException::between($step->workflow_status, $target);
        }
    }

    /**
     * BRD §6 / §9.3 — one person, from the receiving department, on an active account.
     * A Team Leader naming themselves is the only case where the assignee is not a plain
     * employee of the department.
     */
    private function assertAssignable(TaskStep $step, User $assignee, User $actor): void
    {
        if ($assignee->isSignInBlocked()) {
            throw ValidationException::withMessages([
                'assignee_id' => __('A disabled account cannot receive work.'),
            ]);
        }

        if ($assignee->department_id !== $step->department_id) {
            throw ValidationException::withMessages([
                'assignee_id' => __('The assignee must belong to the department that owns this step.'),
            ]);
        }

        $isSelf = $assignee->is($actor);

        if (! $isSelf && ! $assignee->hasRole(RoleCode::Employee)) {
            throw ValidationException::withMessages([
                'assignee_id' => __('Only an employee of the department, or the Team Leader themselves, may be assigned.'),
            ]);
        }
    }

    // ------------------------------------------------------------- primitives

    private function openStep(Task $task, Department $department, int $sequenceNo): TaskStep
    {
        $step = $task->steps()->create([
            'department_id' => $department->id,
            'sequence_no' => $sequenceNo,
            'workflow_status' => WorkflowStatus::WaitingAssignment->value,
            'deadline_status' => DeadlineStatus::NotStarted->value,
        ]);

        $task->forceFill(['current_step_id' => $step->id])->save();

        return $step;
    }

    private function closeStep(TaskStep $step): void
    {
        $step->forceFill([
            'completed_at' => now(),
            'deadline_status' => DeadlineStatus::Closed->value,
        ])->save();
    }

    private function openAssignment(
        TaskStep $step,
        User $actor,
        User $assignee,
        string $startDate,
        string $dueDate,
    ): TaskStepAssignment {
        return $step->assignments()->create([
            'assignee_id' => $assignee->id,
            'assigned_by' => $actor->id,
            'is_self_assigned' => $assignee->is($actor),
            'start_date' => $startDate,
            'due_date' => $dueDate,
        ]);
    }

    /**
     * The single place `workflow_status` is written. Callers have already proved the
     * transition is legal; this keeps the write itself in one method so a new action
     * cannot introduce a second path.
     *
     * @param  array<string, mixed>  $extra
     */
    private function moveStep(TaskStep $step, WorkflowStatus $to, array $extra = []): void
    {
        $step->forceFill(array_merge(['workflow_status' => $to->value], $extra))->save();
    }

    /** BRD §11 — a due date always ends at 23:59 Africa/Cairo. */
    private function dueAt(string $dueDate): Carbon
    {
        return Carbon::parse($dueDate, config('app.timezone'))->setTime(23, 59, 0);
    }

    private function nextSequenceNo(Task $task): int
    {
        return ((int) $task->steps()->max('sequence_no')) + 1;
    }

    /**
     * TSK-YYYY-NNNNN. `task_code` is unique in the database, so the loop closes the race
     * between two simultaneous creations rather than trusting the count.
     */
    private function nextTaskCode(): string
    {
        $year = now()->year;
        $prefix = "TSK-{$year}-";

        $last = Task::query()
            ->where('task_code', 'like', $prefix.'%')
            ->orderByDesc('task_code')
            ->value('task_code');

        $next = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        for ($attempt = 0; $attempt < 50; $attempt++, $next++) {
            $candidate = $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);

            if (! Task::query()->where('task_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new WorkflowException(__('Could not allocate a task code.'));
    }

    /**
     * The task timeline (BRD §9, §19). Written here and nowhere else.
     *
     * @param  array<string, mixed>  $context
     */
    private function record(
        Task $task,
        ?TaskStep $step,
        TaskEvent $event,
        ?User $actor,
        ?WorkflowStatus $from,
        ?WorkflowStatus $to,
        ?string $reason = null,
        array $context = [],
    ): TaskStatusHistory {
        return TaskStatusHistory::create([
            'task_id' => $task->id,
            'task_step_id' => $step?->id,
            'event_type' => $event->value,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'changed_by' => $actor?->id,
            'department_id' => $step?->department_id,
            'reason' => $reason,
            'context' => array_merge(
                $actor !== null ? ['actor_role' => $actor->roleCode()->value] : [],
                $context,
            ),
        ]);
    }
}
