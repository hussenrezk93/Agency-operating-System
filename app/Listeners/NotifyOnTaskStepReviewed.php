<?php

namespace App\Listeners;

use App\Enums\DepartmentSpecialRole;
use App\Enums\ReviewDecision;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Enums\WorkflowStatus;
use App\Events\TaskStepReviewed;
use App\Models\Department;
use App\Models\User;
use App\Services\NotificationService;

/**
 * Appendix B — "Stage approved or transferred" / "Changes Request", now across the two
 * review stages (product decision 2026-09): a change request notifies the assignee only,
 * whichever stage it came from (they're the one who has to act on it either way). An
 * approval notifies differently depending on $event->from: from UnderReview, the Team
 * Leader just approved and the step now needs a Manager's mandatory second look, so
 * every Manager is notified (nothing productive to tell the assignee yet — their work
 * isn't actually done). From PendingManagerReview, the Manager just approved — the
 * step is genuinely done, so this is today's original shape: assignee + department
 * leader.
 */
class NotifyOnTaskStepReviewed
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskStepReviewed $event): void
    {
        $step = $event->step;
        $task = $step->task;

        if ($event->review->decision === ReviewDecision::ChangesRequested) {
            $assignee = $step->activeAssignment?->assignee;

            if ($assignee !== null) {
                $this->notifications->notify(
                    $assignee,
                    'task.changes_requested',
                    __('agencyos.notifications.messages.task_changes_requested_title'),
                    __('agencyos.notifications.messages.task_changes_requested_body', ['task' => $task->title]),
                    'task',
                    $task->id,
                );
            }

            return;
        }

        // Whose turn it is now is simply where the step landed — moveStep() has already
        // written the new status onto this very object. Reading the destination beats
        // re-deriving it from $event->from, which would have to repeat approve()'s own
        // Graphic-goes-to-Content routing rule and could drift from it.
        if ($step->workflow_status === WorkflowStatus::PendingContentReview) {
            $contentLeader = Department::withSpecialRole(DepartmentSpecialRole::Content)?->effectiveLeader();

            if ($contentLeader !== null) {
                $this->notifications->notify(
                    $contentLeader,
                    'task.content_review_needed',
                    __('agencyos.notifications.messages.content_review_needed_title'),
                    __('agencyos.notifications.messages.content_review_needed_body', ['task' => $task->title]),
                    'task',
                    $task->id,
                );
            }

            return;
        }

        if ($step->workflow_status === WorkflowStatus::PendingManagerReview) {
            $managers = User::whereHas('role', fn ($q) => $q->where('code', RoleCode::Manager->value))
                ->where('status', UserStatus::Active->value)
                ->get();

            foreach ($managers as $manager) {
                $this->notifications->notify(
                    $manager,
                    'task.manager_review_needed',
                    __('agencyos.notifications.messages.manager_review_needed_title'),
                    __('agencyos.notifications.messages.manager_review_needed_body', ['task' => $task->title]),
                    'task',
                    $task->id,
                );
            }

            return;
        }

        $assignee = $step->activeAssignment?->assignee;

        if ($assignee !== null) {
            $this->notifications->notify(
                $assignee,
                'task.approved',
                __('agencyos.notifications.messages.task_approved_title'),
                __('agencyos.notifications.messages.task_approved_body', ['task' => $task->title]),
                'task',
                $task->id,
            );
        }

        $leader = $step->department->effectiveLeader();

        if ($leader !== null && $leader->isNot($event->review->reviewer)) {
            $this->notifications->notify(
                $leader,
                'task.approved',
                __('agencyos.notifications.messages.task_step_approved_title'),
                __('agencyos.notifications.messages.task_step_approved_body', ['task' => $task->title]),
                'task',
                $task->id,
            );
        }
    }
}
