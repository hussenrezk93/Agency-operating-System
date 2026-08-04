<?php

namespace App\Listeners;

use App\Enums\ReviewDecision;
use App\Events\TaskStepReviewed;
use App\Services\NotificationService;

/**
 * Appendix B — "Stage approved or transferred" / "Changes Request". Approval notifies
 * the assignee and the effective TL; a change request notifies the assignee only
 * (they're the one who has to act on it).
 */
class NotifyOnTaskStepReviewed
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskStepReviewed $event): void
    {
        $step = $event->step;
        $task = $step->task;
        $assignee = $step->activeAssignment?->assignee;

        if ($assignee === null) {
            return;
        }

        if ($event->review->decision === ReviewDecision::ChangesRequested) {
            $this->notifications->notify(
                $assignee,
                'task.changes_requested',
                __('agencyos.notifications.messages.task_changes_requested_title'),
                __('agencyos.notifications.messages.task_changes_requested_body', ['task' => $task->title]),
                'task',
                $task->id,
            );

            return;
        }

        $this->notifications->notify(
            $assignee,
            'task.approved',
            __('agencyos.notifications.messages.task_approved_title'),
            __('agencyos.notifications.messages.task_approved_body', ['task' => $task->title]),
            'task',
            $task->id,
        );

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
