<?php

namespace Tests\Unit;

use App\Enums\WorkflowStatus;
use PHPUnit\Framework\TestCase;

/**
 * The transition map on its own — no database, no policies. If this file and
 * TaskWorkflowService ever disagree, the map wins: the service consults it before every
 * write, so a transition that is not listed here cannot be performed at all.
 */
class WorkflowStatusTransitionTest extends TestCase
{
    public function test_waiting_assignment_only_leads_to_in_progress_or_a_control_action(): void
    {
        $from = WorkflowStatus::WaitingAssignment;

        $this->assertTrue($from->canTransitionTo(WorkflowStatus::InProgress));
        $this->assertTrue($from->canTransitionTo(WorkflowStatus::Redirected));
        $this->assertTrue($from->canTransitionTo(WorkflowStatus::Cancelled));

        $this->assertFalse($from->canTransitionTo(WorkflowStatus::UnderReview));
        $this->assertFalse($from->canTransitionTo(WorkflowStatus::Approved));
    }

    public function test_in_progress_leads_to_under_review(): void
    {
        $this->assertTrue(WorkflowStatus::InProgress->canTransitionTo(WorkflowStatus::UnderReview));
    }

    /** The review step can never be skipped — this is the guard behind BRD §22.3. */
    public function test_in_progress_cannot_jump_straight_to_approved(): void
    {
        $this->assertFalse(WorkflowStatus::InProgress->canTransitionTo(WorkflowStatus::Approved));
    }

    /** Product decision 2026-09 — a TL's approval hands off to a mandatory Manager
     *  review instead of reaching Approved directly. */
    public function test_under_review_leads_to_pending_manager_review_or_changes_requested(): void
    {
        $from = WorkflowStatus::UnderReview;

        $this->assertTrue($from->canTransitionTo(WorkflowStatus::PendingManagerReview));
        $this->assertTrue($from->canTransitionTo(WorkflowStatus::ChangesRequested));
        $this->assertFalse($from->canTransitionTo(WorkflowStatus::Approved));
    }

    /** Product decision 2026-09 — Graphic's steps gain one stage in the middle:
     *  Content reviews them before the Manager ever sees them. */
    public function test_under_review_may_also_lead_to_content_review(): void
    {
        $this->assertTrue(WorkflowStatus::UnderReview->canTransitionTo(WorkflowStatus::PendingContentReview));
    }

    public function test_content_review_hands_off_to_the_manager_never_straight_to_approved(): void
    {
        $from = WorkflowStatus::PendingContentReview;

        $this->assertTrue($from->canTransitionTo(WorkflowStatus::PendingManagerReview));
        $this->assertTrue($from->canTransitionTo(WorkflowStatus::ChangesRequested));
        $this->assertFalse($from->canTransitionTo(WorkflowStatus::Approved));
    }

    public function test_content_review_is_not_terminal_and_still_allows_managing_outputs(): void
    {
        $this->assertFalse(WorkflowStatus::PendingContentReview->isTerminal());
        $this->assertFalse(WorkflowStatus::PendingContentReview->isEditableByAssignee());
        $this->assertTrue(WorkflowStatus::PendingContentReview->canManageOutputs());
    }

    /** The Manager's own review step — the true end of the review cycle. */
    public function test_pending_manager_review_leads_to_either_review_decision(): void
    {
        $from = WorkflowStatus::PendingManagerReview;

        $this->assertTrue($from->canTransitionTo(WorkflowStatus::Approved));
        $this->assertTrue($from->canTransitionTo(WorkflowStatus::ChangesRequested));
    }

    public function test_pending_manager_review_is_not_terminal_and_not_editable_by_assignee(): void
    {
        $this->assertFalse(WorkflowStatus::PendingManagerReview->isTerminal());
        $this->assertFalse(WorkflowStatus::PendingManagerReview->isEditableByAssignee());
    }

    /** Matches UnderReview's own inclusion — the assignee stays the current assignee,
     *  and so may still manage outputs, through both review stages. */
    public function test_pending_manager_review_still_allows_managing_outputs(): void
    {
        $this->assertTrue(WorkflowStatus::PendingManagerReview->canManageOutputs());
    }

    public function test_changes_requested_leads_back_to_under_review_on_resubmission(): void
    {
        $from = WorkflowStatus::ChangesRequested;

        $this->assertTrue($from->canTransitionTo(WorkflowStatus::UnderReview));
        $this->assertFalse($from->canTransitionTo(WorkflowStatus::Approved));
    }

    /**
     * Approved is the end of the step. Moving on creates a NEW step and finishing sets
     * tasks.lifecycle_status, so neither is expressible here (approved decision Q6).
     */
    public function test_approved_is_terminal(): void
    {
        $this->assertSame([], WorkflowStatus::Approved->allowedNextStatuses());
        $this->assertTrue(WorkflowStatus::Approved->isTerminal());
    }

    public function test_redirected_and_cancelled_are_terminal(): void
    {
        $this->assertSame([], WorkflowStatus::Redirected->allowedNextStatuses());
        $this->assertSame([], WorkflowStatus::Cancelled->allowedNextStatuses());
        $this->assertTrue(WorkflowStatus::Redirected->isTerminal());
        $this->assertTrue(WorkflowStatus::Cancelled->isTerminal());
    }

    /** Regression guard on the two helpers slice 1 already relied on. */
    public function test_only_in_progress_and_changes_requested_are_editable_by_the_assignee(): void
    {
        $editable = array_filter(
            WorkflowStatus::cases(),
            fn (WorkflowStatus $s): bool => $s->isEditableByAssignee(),
        );

        $this->assertEqualsCanonicalizing(
            [WorkflowStatus::InProgress, WorkflowStatus::ChangesRequested],
            array_values($editable),
        );
    }

    public function test_no_status_can_transition_to_itself(): void
    {
        foreach (WorkflowStatus::cases() as $status) {
            $this->assertFalse(
                $status->canTransitionTo($status),
                "{$status->value} must not transition to itself",
            );
        }
    }
}
