<?php

namespace App\Support;

use App\Enums\DeadlineStatus;
use App\Enums\Priority;
use App\Enums\TaskLifecycle;
use App\Enums\WorkflowStatus;

/**
 * Maps the task enums to the approved prototype's existing badge CSS classes
 * (`public/assets/agencyos.css` — `.badge .b-*`, `.tag .p-*`) and a translated
 * label. Shared by every task view so the mapping is written once.
 */
final class TaskPresenter
{
    /**
     * @return array{class: string, label: string}
     *
     * $isSelfAssigned matters only for UnderReview: a self-assigned step's first
     * review is done by the Manager, not the department's Team Leader (Q12 — a TL
     * can never review a stage they executed themself), so the label has to say
     * "awaiting Manager review" there too, same as PendingManagerReview — otherwise
     * it names the wrong reviewer for the one person who can actually act on it.
     */
    public static function workflowBadge(WorkflowStatus $status, ?bool $isSelfAssigned = false): array
    {
        $class = match ($status) {
            WorkflowStatus::WaitingAssignment => 'b-waiting',
            WorkflowStatus::InProgress => 'b-progress',
            WorkflowStatus::UnderReview => 'b-review',
            WorkflowStatus::PendingContentReview => 'b-review',
            WorkflowStatus::PendingManagerReview => 'b-review',
            WorkflowStatus::ChangesRequested => 'b-changes',
            WorkflowStatus::Approved => 'b-approved',
            WorkflowStatus::Redirected => 'b-neutral',
            WorkflowStatus::Cancelled => 'b-cancel',
        };

        $labelKey = $status === WorkflowStatus::UnderReview && $isSelfAssigned
            ? WorkflowStatus::PendingManagerReview->value
            : $status->value;

        return ['class' => $class, 'label' => __('agencyos.tasks.workflow_status.'.$labelKey)];
    }

    /** @return array{class: string, label: string} */
    public static function lifecycleBadge(TaskLifecycle $status): array
    {
        $class = match ($status) {
            TaskLifecycle::Draft => 'b-neutral',
            TaskLifecycle::Active => 'b-neutral',
            TaskLifecycle::OnHold => 'b-hold',
            TaskLifecycle::Completed => 'b-done',
            TaskLifecycle::Cancelled => 'b-cancel',
        };

        return ['class' => $class, 'label' => __('agencyos.tasks.lifecycle_status.'.$status->value)];
    }

    /** @return array{class: string, label: string} */
    public static function deadlineBadge(DeadlineStatus $status): array
    {
        $class = match ($status) {
            DeadlineStatus::NotStarted, DeadlineStatus::OnTime, DeadlineStatus::Closed => 'b-neutral',
            DeadlineStatus::DueSoon => 'b-changes',
            DeadlineStatus::Overdue => 'b-overdue',
            DeadlineStatus::Paused => 'b-hold',
        };

        return ['class' => $class, 'label' => __('agencyos.tasks.deadline_status.'.$status->value)];
    }

    /** @return array{class: string, label: string} */
    public static function priorityTag(Priority $priority): array
    {
        $class = match ($priority) {
            Priority::Low => 'p-low',
            Priority::Medium => 'p-medium',
            Priority::High => 'p-high',
            Priority::Urgent => 'p-urgent',
        };

        return ['class' => $class, 'label' => __('agencyos.tasks.priority.'.$priority->value)];
    }
}
