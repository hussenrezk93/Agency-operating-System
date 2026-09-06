<?php

namespace App\Listeners;

use App\Events\TaskReopened;
use App\Services\NotificationService;

/**
 * Product decision 2026-09 — a Manager reopening a Completed task notifies the
 * assignee (their finished work is back in their queue, same shape as the assignee-only
 * ChangesRequested branch in NotifyOnTaskStepReviewed) and the department's effective
 * leader as a courtesy heads-up.
 */
class NotifyOnTaskReopened
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskReopened $event): void
    {
        $task = $event->task;
        $step = $event->step;

        $assignee = $step->activeAssignment?->assignee;

        if ($assignee !== null) {
            $this->notifications->notify(
                $assignee,
                'task.reopened',
                __('agencyos.notifications.messages.task_reopened_title'),
                __('agencyos.notifications.messages.task_reopened_body', ['task' => $task->title, 'reason' => $event->reason]),
                'task',
                $task->id,
            );
        }

        $leader = $step->department->effectiveLeader();

        if ($leader !== null && ($assignee === null || $leader->isNot($assignee))) {
            $this->notifications->notify(
                $leader,
                'task.reopened',
                __('agencyos.notifications.messages.task_reopened_title'),
                __('agencyos.notifications.messages.task_reopened_body', ['task' => $task->title, 'reason' => $event->reason]),
                'task',
                $task->id,
            );
        }
    }
}
