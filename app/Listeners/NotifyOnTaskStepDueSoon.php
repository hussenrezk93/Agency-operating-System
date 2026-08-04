<?php

namespace App\Listeners;

use App\Events\TaskStepDueSoon;
use App\Services\NotificationService;

/** Appendix B — "24h before deadline" -> the assignee and the effective TL only. */
class NotifyOnTaskStepDueSoon
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskStepDueSoon $event): void
    {
        $step = $event->step;

        $recipients = collect([$step->activeAssignment?->assignee, $step->department->effectiveLeader()])
            ->filter()
            ->unique('id');

        foreach ($recipients as $recipient) {
            $this->notifications->notify(
                $recipient,
                'task.due_soon',
                __('agencyos.notifications.messages.task_due_soon_title'),
                __('agencyos.notifications.messages.task_due_soon_body', ['task' => $step->task->title]),
                'task',
                $step->task_id,
            );
        }
    }
}
