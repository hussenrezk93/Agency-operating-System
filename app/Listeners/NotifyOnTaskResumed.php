<?php

namespace App\Listeners;

use App\Events\TaskResumed;
use App\Services\NotificationService;

/** Appendix B — "Resume" -> the current assignee (if any) and the effective TL. */
class NotifyOnTaskResumed
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskResumed $event): void
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
                'task.resumed',
                __('agencyos.notifications.messages.task_resumed_title'),
                __('agencyos.notifications.messages.task_resumed_body', ['task' => $task->title]),
                'task',
                $task->id,
            );
        }
    }
}
