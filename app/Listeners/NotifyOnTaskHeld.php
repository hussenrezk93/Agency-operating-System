<?php

namespace App\Listeners;

use App\Events\TaskHeld;
use App\Services\NotificationService;

/** Appendix B — "On Hold" -> the current assignee (if any) and the effective TL. */
class NotifyOnTaskHeld
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskHeld $event): void
    {
        $task = $event->task;
        $step = $task->currentStep;

        if ($step === null) {
            return;
        }

        $recipients = collect([$step->activeAssignment?->assignee, $step->department->effectiveLeader()])
            ->filter()
            ->unique('id');

        foreach ($recipients as $recipient) {
            $this->notifications->notify(
                $recipient,
                'task.held',
                __('agencyos.notifications.messages.task_held_title'),
                __('agencyos.notifications.messages.task_held_body', ['task' => $task->title]),
                'task',
                $task->id,
            );
        }
    }
}
