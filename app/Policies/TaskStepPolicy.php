<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\TaskStep;
use App\Models\User;

/**
 * PHASE 1B SLICE 2 — who may act on one department's turn.
 *
 * DIVISION OF LABOUR WITH THE STATE MACHINE
 * This class answers only "may this person act on this step at all". Whether the specific
 * move is legal right now — transfer requires Approved, review requires Under Review,
 * submit requires In Progress or Changes Requested — belongs to WorkflowStatus and
 * TaskWorkflowService, and is deliberately NOT repeated here. Two reasons:
 *   · One definition. A status precondition written in both places drifts, and the copy in
 *     the policy is the one nobody remembers to update.
 *   · Correct HTTP semantics. "You may not do this" is 403; "not from this state" is 422.
 *     If the policy also enforced status, every mistimed action would return 403 and the
 *     interface could not tell a permission problem from a sequencing one.
 * What this class does check is the coarse question of whether the step is still live at
 * all, because a settled step or a closed task is not actionable by anybody.
 *
 * Every leadership answer goes through User::canActAsLeaderOf(), which resolves
 * Department::effectiveLeader(): a temporary Team Leader gets the full operational
 * permission set (Q11) and a primary Team Leader covered by one is view-only (Q14).
 *
 * The three rules worth stating in full, because they are the ones most easily lost:
 *
 *   Q21  The Manager MUST NOT choose the employee for a step or set its dates. Oversight
 *        is not assignment authority. assign() therefore refuses the Manager outright.
 *   Q12  A step the effective Team Leader assigned to themselves is reviewed by a
 *        MANAGER. A Team Leader can never approve their own work, so review() flips the
 *        expected reviewer based on `is_self_assigned` rather than trusting the role.
 *   Q20  `first_seen_at` is readable ONLY by the effective Team Leader of that
 *        department — not the employee it belongs to, not the Manager, not another
 *        Team Leader.
 */
class TaskStepPolicy
{
    public function view(User $actor, TaskStep $step): bool
    {
        if ($actor->hasRole(RoleCode::Admin)) {
            return false;
        }

        if ($actor->hasRole(RoleCode::Manager)) {
            return true;
        }

        if ($actor->hasRole(RoleCode::TeamLeader)) {
            return $actor->department_id === $step->department_id;
        }

        return $step->assignments()->where('assignee_id', $actor->id)->exists();
    }

    /**
     * Q21 — the effective Team Leader of the receiving department, and nobody else. Covers
     * both the first assignment and a later replacement; the service tells the two apart
     * by the step's status.
     */
    public function assign(User $actor, TaskStep $step): bool
    {
        return $this->stepIsLive($step) && $actor->canActAsLeaderOf($step->department_id);
    }

    /** BRD §13 — only the person the step is currently assigned to adds outputs. */
    public function addOutput(User $actor, TaskStep $step): bool
    {
        return $this->stepIsLive($step) && $this->isCurrentAssignee($actor, $step);
    }

    /** BRD §9.5 — the assignee submits their own work; nobody submits on their behalf. */
    public function submit(User $actor, TaskStep $step): bool
    {
        return $this->stepIsLive($step) && $this->isCurrentAssignee($actor, $step);
    }

    /**
     * BRD §9.6 + Q12. The reviewer is the effective Team Leader of the department, unless
     * the Team Leader assigned the step to themselves, in which case it is the Manager.
     * In no case may the person who did the work review it.
     */
    public function review(User $actor, TaskStep $step): bool
    {
        if (! $this->stepIsLive($step) || $this->isCurrentAssignee($actor, $step)) {
            return false;
        }

        if ($step->isSelfAssigned()) {
            return $actor->hasRole(RoleCode::Manager);
        }

        return $actor->canActAsLeaderOf($step->department_id);
    }

    /** Aliases — approving and requesting changes are two halves of one authority. */
    public function approve(User $actor, TaskStep $step): bool
    {
        return $this->review($actor, $step);
    }

    public function requestChanges(User $actor, TaskStep $step): bool
    {
        return $this->review($actor, $step);
    }

    /**
     * BRD §9.8 — after approval the Team Leader who holds the step routes it onward.
     * The Manager is excluded on purpose: their route-correction tool is Redirect
     * (BRD §10), which carries a mandatory reason and its own audit trail.
     *
     * stepIsLive() is deliberately not used here: a transferable step is Approved, which
     * is terminal by design (Q6), so only a closed task rules the action out.
     */
    public function transfer(User $actor, TaskStep $step): bool
    {
        return ! $step->task->isClosed() && $actor->canActAsLeaderOf($step->department_id);
    }

    /** Q19 — the assignee opening their own live step. */
    public function markSeen(User $actor, TaskStep $step): bool
    {
        return $this->stepIsLive($step) && $this->isCurrentAssignee($actor, $step);
    }

    /** Q20 — the effective Team Leader of this department only. */
    public function viewFirstSeen(User $actor, TaskStep $step): bool
    {
        return $actor->canActAsLeaderOf($step->department_id);
    }

    /**
     * BRD §13 — stage comments belong to the assignee and the department's Team Leader,
     * and are never visible to the next department.
     */
    public function viewComments(User $actor, TaskStep $step): bool
    {
        return $this->isCurrentAssignee($actor, $step)
            || $actor->canActAsLeaderOf($step->department_id);
    }

    /** BRD §13 — the same private thread: the assignee and the department's effective Team Leader. */
    public function addComment(User $actor, TaskStep $step): bool
    {
        return $this->stepIsLive($step) && $this->viewComments($actor, $step);
    }

    private function isCurrentAssignee(User $actor, TaskStep $step): bool
    {
        return $step->activeAssignment?->assignee_id === $actor->id;
    }

    /** Still in the review cycle: not approved, redirected or cancelled, task still open. */
    private function stepIsLive(TaskStep $step): bool
    {
        return ! $step->workflow_status->isTerminal() && ! $step->task->isClosed();
    }
}
