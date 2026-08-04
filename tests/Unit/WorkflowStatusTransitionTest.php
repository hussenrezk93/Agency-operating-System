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

    public function test_under_review_leads_to_either_review_decision(): void
    {
        $from = WorkflowStatus::UnderReview;

        $this->assertTrue($from->canTransitionTo(WorkflowStatus::Approved));
        $this->assertTrue($from->canTransitionTo(WorkflowStatus::ChangesRequested));
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
