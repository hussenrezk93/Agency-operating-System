<?php

namespace App\Listeners;

use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Events\TaskStepSubmitted;
use App\Models\User;
use App\Services\NotificationService;

/**
 * Appendix B — "Stage submitted for review" -> the effective TL, or the Manager when the
 * TL self-assigned the step (Q12: a self-assigned step is reviewed by the Manager).
 */
class NotifyOnTaskStepSubmitted
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TaskStepSubmitted $event): void
    {
        $step = $event->step;
        $task = $step->task;

        $reviewers = $step->isSelfAssigned()
            ? User::whereHas('role', fn ($q) => $q->where('code', RoleCode::Manager->value))
                ->where('status', UserStatus::Active->value)
                ->get()
            : collect([$step->department->effectiveLeader()])->filter();

        foreach ($reviewers as $reviewer) {
            $this->notifications->notify(
                $reviewer,
                'task.submitted',
                __('agencyos.notifications.messages.task_submitted_title'),
                __('agencyos.notifications.messages.task_submitted_body', ['task' => $task->title]),
                'task',
                $task->id,
            );
        }
    }
}
