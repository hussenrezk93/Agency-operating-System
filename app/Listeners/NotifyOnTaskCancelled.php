<?php

namespace App\Listeners;

use App\Events\TaskCancelled;
use App\Services\NotificationService;

/** Appendix B — "Cancel" -> the creator, every step's assignee cancelTask() just ended, and every touched department's effective TL. */
class NotifyOnTaskCancelled
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskCancelled $event): void
    {
        $task = $event->task->load('steps.assignments', 'steps.department');

        $assignees = $task->steps
            ->flatMap(fn ($step) => $step->assignments)
            ->filter(fn ($assignment) => $assignment->end_reason === 'task cancelled')
            ->map(fn ($assignment) => $assignment->assignee);

        $leaders = $task->steps->pluck('department')
            ->filter()
            ->unique('id')
            ->map(fn ($department) => $department->effectiveLeader());

        $recipients = collect([$task->creator])
            ->merge($assignees)
            ->merge($leaders)
            ->filter()
            ->unique('id');

        foreach ($recipients as $recipient) {
            $this->notifications->notify(
                $recipient,
                'task.cancelled',
                __('agencyos.notifications.messages.task_cancelled_title'),
                __('agencyos.notifications.messages.task_cancelled_body', ['task' => $task->title]),
                'task',
                $task->id,
            );
        }
    }
}
