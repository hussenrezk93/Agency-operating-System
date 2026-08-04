<?php

namespace App\Listeners;

use App\Events\TaskStepTransferred;
use App\Services\NotificationService;

/** Appendix B — "Task arrives at department" -> the receiving department's effective TL. */
class NotifyOnTaskStepTransferred
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskStepTransferred $event): void
    {
        $toStep = $event->toStep;
        $leader = $toStep->department->effectiveLeader();

        if ($leader === null) {
            return;
        }

        $this->notifications->notify(
            $leader,
            'task.arrived',
            __('agencyos.notifications.messages.task_arrived_title'),
            __('agencyos.notifications.messages.task_arrived_body', [
                'task' => $toStep->task->title,
                'department' => $toStep->department->name,
            ]),
            'task',
            $toStep->task_id,
        );
    }
}
