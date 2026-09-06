<?php

namespace App\Enums;

/**
 * task_steps.workflow_status — where the STEP stands in the review cycle.
 * Approved decision Q6: the ERD vocabulary is authoritative. "Assigned" maps to
 * InProgress, and "Transferred" is expressed as a closed step plus a new step —
 * it is deliberately NOT a status here.
 * NEVER mix this with DeadlineStatus (Q7).
 *
 * PHASE 1B SLICE 2 — the transition map below is the single authority for which step
 * transitions exist. TaskWorkflowService is the only writer of workflow_status and it
 * consults this map before every write, so an illegal transition cannot be reached from
 * a service call, a controller, or a future queue job.
 *
 * "Submitted" is NOT a separate status: submitting hands the step to review in one move,
 * so Submitted and Under Review are the same state and `task_steps.submitted_at` records
 * the moment it happened. "Next Department" and "Completed" are likewise not step
 * statuses — a step that is Approved is finished, and onward movement either opens a NEW
 * step or closes the TASK (tasks.lifecycle_status). See TaskWorkflowService.
 */
enum WorkflowStatus: string
{
    case WaitingAssignment = 'waiting_assignment';
    case InProgress = 'in_progress';
    case UnderReview = 'under_review';
    /** Product decision 2026-09 — a Graphic step passes Content's review between its own
     *  Team Leader and the Manager. Only departments with special_role=graphic enter it;
     *  TaskWorkflowService::approve() decides, nothing here does. */
    case PendingContentReview = 'pending_content_review';
    case PendingManagerReview = 'pending_manager_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Redirected = 'redirected';
    case Cancelled = 'cancelled';

    /** The step is finished and can no longer change. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Redirected, self::Cancelled], true);
    }

    /** The assignee may still work on it. */
    public function isEditableByAssignee(): bool
    {
        return $this === self::InProgress || $this === self::ChangesRequested;
    }

    /**
     * Deliberately WIDER than isEditableByAssignee(): the assignee stays the "current
     * assignee" through Under Review too (submit() doesn't end the assignment, only
     * approve() does — see tasks/show.blade.php's own comment on this), so they may
     * still add or remove output links right up until the reviewer actually decides.
     * Submitting again and reassignment stay InProgress/ChangesRequested-only — this
     * helper is for output management alone, not a general "still editable" flag.
     */
    public function canManageOutputs(): bool
    {
        return $this === self::InProgress || $this === self::ChangesRequested
            || $this === self::UnderReview || $this === self::PendingContentReview
            || $this === self::PendingManagerReview;
    }

    /**
     * Every transition this status may legally make.
     *
     * Redirected and Cancelled are set by control actions (Manager redirect, task
     * cancellation) rather than by the review cycle, but they are listed as reachable
     * targets so those services validate through the same map instead of writing the
     * column directly.
     *
     * @return array<int, self>
     */
    public function allowedNextStatuses(): array
    {
        return match ($this) {
            self::WaitingAssignment => [self::InProgress, self::Redirected, self::Cancelled],
            // BRD §6 — disabling the assignee releases the step back to Waiting Assignment.
            // UnderReview is excluded: a submitted step's ball is in the reviewer's court.
            self::InProgress => [self::UnderReview, self::WaitingAssignment, self::Redirected, self::Cancelled],
            // Product decision 2026-09 — a TL's approval no longer reaches Approved
            // directly; it hands off to a mandatory Manager review first.
            // Both hand-offs are legal here because the next stage depends on the step's
            // DEPARTMENT, not on this status: Graphic goes to Content first, everyone
            // else straight to the Manager. approve() picks; this map only permits.
            self::UnderReview => [self::PendingContentReview, self::PendingManagerReview, self::ChangesRequested, self::Redirected, self::Cancelled],
            self::PendingContentReview => [self::PendingManagerReview, self::ChangesRequested, self::Redirected, self::Cancelled],
            self::PendingManagerReview => [self::Approved, self::ChangesRequested, self::Redirected, self::Cancelled],
            self::ChangesRequested => [self::UnderReview, self::WaitingAssignment, self::Redirected, self::Cancelled],
            // Approved is the end of the step. Onward movement creates a new step
            // (Send to Next Department) or completes the task (Finish Task).
            // TaskWorkflowService::reopen() is a deliberate, admin-style exception that
            // moves a closed task's final Approved step back to ChangesRequested — it
            // bypasses this map entirely (like cancelTask() already does for Cancelled)
            // rather than being added here, since nothing else about Approved should
            // ever be treated as non-terminal.
            self::Approved, self::Redirected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNextStatuses(), true);
    }
}
