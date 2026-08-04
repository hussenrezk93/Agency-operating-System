<?php

namespace App\Listeners;

use App\Events\TaskStepFirstSeen;
use App\Services\NotificationService;

/** Appendix B — "First task open by employee" -> the department's effective TL. */
class NotifyOnTaskStepFirstSeen
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskStepFirstSeen $event): void
    {
        $step = $event->step;
        $leader = $step->department->effectiveLeader();

        if ($leader === null) {
            return;
        }

        $this->notifications->notify(
            $leader,
            'task.first_seen',
            __('agencyos.notifications.messages.task_first_seen_title'),
            __('agencyos.notifications.messages.task_first_seen_body', [
                'task' => $step->task->title,
                'assignee' => $event->assignment->assignee->full_name,
            ]),
            'task',
            $step->task_id,
        );
    }
}
