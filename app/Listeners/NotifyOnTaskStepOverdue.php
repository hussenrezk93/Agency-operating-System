<?php

namespace App\Listeners;

use App\Events\TaskStepOverdue;
use App\Services\NotificationService;

/** Appendix B — "Deadline exceeded" -> the assignee and the effective TL only. */
class NotifyOnTaskStepOverdue
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskStepOverdue $event): void
    {
        $step = $event->step;

        $recipients = collect([$step->activeAssignment?->assignee, $step->department->effectiveLeader()])
            ->filter()
            ->unique('id');

        foreach ($recipients as $recipient) {
            $this->notifications->notify(
                $recipient,
                'task.overdue',
                __('agencyos.notifications.messages.task_overdue_title'),
                __('agencyos.notifications.messages.task_overdue_body', ['task' => $step->task->title]),
                'task',
                $step->task_id,
            );
        }
    }
}
