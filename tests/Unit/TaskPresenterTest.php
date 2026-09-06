<?php

namespace Tests\Unit;

use App\Enums\WorkflowStatus;
use App\Support\TaskPresenter;
use Tests\TestCase;

/**
 * workflowBadge() calls __() for its label, so this needs the Laravel TestCase (not
 * plain PHPUnit) even though nothing here touches the database.
 */
class TaskPresenterTest extends TestCase
{
    public function test_under_review_labels_the_team_leader_as_reviewer_by_default(): void
    {
        $badge = TaskPresenter::workflowBadge(WorkflowStatus::UnderReview);

        $this->assertSame(__('agencyos.tasks.workflow_status.under_review'), $badge['label']);
    }

    /**
     * Q12 — a self-assigned step's first review is done by the Manager, not the TL, so
     * the label must say so even while the step is still (technically) UnderReview.
     */
    public function test_a_self_assigned_under_review_step_is_labelled_as_awaiting_the_manager(): void
    {
        $badge = TaskPresenter::workflowBadge(WorkflowStatus::UnderReview, true);

        $this->assertSame(__('agencyos.tasks.workflow_status.pending_manager_review'), $badge['label']);
    }

    public function test_pending_manager_review_is_unaffected_by_the_self_assigned_flag(): void
    {
        $badge = TaskPresenter::workflowBadge(WorkflowStatus::PendingManagerReview, true);

        $this->assertSame(__('agencyos.tasks.workflow_status.pending_manager_review'), $badge['label']);
    }
}
