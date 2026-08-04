<?php

namespace App\Listeners;

use App\Events\TaskStepAssigned;
use App\Services\NotificationService;

/** Appendix B — "Employee assigned/reassigned" -> the new assignee, plus the previous
 *  assignee when this dispatch followed a reassignment. */
class NotifyOnTaskStepAssigned
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskStepAssigned $event): void
    {
        $step = $event->step;
        $task = $step->task;

        $this->notifications->notify(
            $event->assignment->assignee,
            'task.assigned',
            __('agencyos.notifications.messages.task_assigned_title'),
            __('agencyos.notifications.messages.task_assigned_body', ['task' => $task->title]),
            'task',
            $task->id,
        );

        $previous = $step->assignments()
            ->whereNotNull('ended_at')
            ->where('assignee_id', '!=', $event->assignment->assignee_id)
            ->latest('ended_at')
            ->first();

        if ($previous !== null) {
            $this->notifications->notify(
                $previous->assignee,
                'task.reassigned_away',
                __('agencyos.notifications.messages.task_reassigned_away_title'),
                __('agencyos.notifications.messages.task_reassigned_away_body', ['task' => $task->title]),
                'task',
                $task->id,
            );
        }
    }
}
