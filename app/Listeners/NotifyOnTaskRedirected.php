<?php

namespace App\Listeners;

use App\Events\TaskRedirected;
use App\Services\NotificationService;

/** Appendix B — "Redirect" -> the previous assignee (if any), both departments' effective TLs, and the creator. */
class NotifyOnTaskRedirected
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskRedirected $event): void
    {
        $task = $event->task;

        $previousAssignee = $event->fromStep->assignments()
            ->whereNotNull('ended_at')
            ->latest('ended_at')
            ->first()?->assignee;

        $recipients = collect([
            $previousAssignee,
            $event->fromStep->department->effectiveLeader(),
            $event->toStep->department->effectiveLeader(),
            $task->creator,
        ])->filter()->unique('id');

        foreach ($recipients as $recipient) {
            $this->notifications->notify(
                $recipient,
                'task.redirected',
                __('agencyos.notifications.messages.task_redirected_title'),
                __('agencyos.notifications.messages.task_redirected_body', [
                    'task' => $task->title,
                    'department' => $event->toStep->department->name,
                ]),
                'task',
                $task->id,
            );
        }
    }
}
