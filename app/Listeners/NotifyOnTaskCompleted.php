<?php

namespace App\Listeners;

use App\Events\TaskCompleted;
use App\Services\NotificationService;

/** Appendix B — "Task completed" -> the creator and every participating department's effective TL. */
class NotifyOnTaskCompleted
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskCompleted $event): void
    {
        $task = $event->task;

        $this->notifications->notify(
            $task->creator,
            'task.completed',
            __('agencyos.notifications.messages.task_completed_title'),
            __('agencyos.notifications.messages.task_completed_body', ['task' => $task->title]),
            'task',
            $task->id,
        );

        $leaders = $task->steps->pluck('department')
            ->filter()
            ->unique('id')
            ->map(fn ($department) => $department->effectiveLeader())
            ->filter();

        foreach ($leaders as $leader) {
            if ($leader->is($task->creator)) {
                continue;
            }

            $this->notifications->notify(
                $leader,
                'task.completed',
                __('agencyos.notifications.messages.task_completed_title'),
                __('agencyos.notifications.messages.task_completed_body', ['task' => $task->title]),
                'task',
                $task->id,
            );
        }
    }
}
