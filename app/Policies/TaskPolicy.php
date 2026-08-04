<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Enums\TaskLifecycle;
use App\Models\Task;
use App\Models\User;

/**
 * PHASE 1B SLICE 2 — the deny-by-default placeholder is gone; these are the real rules.
 *
 * TASK-level authority lives here. STEP-level authority (assign, submit, review,
 * transfer, seen) lives in TaskStepPolicy, because those actions target one department's
 * turn rather than the whole task, and the answer depends on which step is being acted on.
 * Between the two classes every permission named in the slice brief exists exactly once.
 *
 * THE FOUR ROLES (BRD §5, §15 and the approved decision register)
 *
 *   Admin      Configuration and audit only. Never sees task content or outputs — an
 *              Admin who could read every task would defeat the point of BRD §15's
 *              separation, and BRD §19 grants the Admin the audit log instead.
 *   Manager    Full oversight of every task, creates tasks, cancels, finishes.
 *              Q21: may NOT pick the employee for a step or set its dates.
 *              Q12: reviews a step the effective Team Leader self-assigned.
 *   TL         Their own department's tasks. Q14: a primary Team Leader covered by a
 *              temporary one is VIEW ONLY and performs no leadership action.
 *   Employee   Only tasks they are, or were, assigned a step on.
 *
 * Authority is always resolved through Department::effectiveLeader() —
 * User::canActAsLeaderOf() — never through `users.role` alone, so a temporary Team Leader
 * is fully empowered and a covered primary is not.
 */
class TaskPolicy
{
    public function viewAny(User $actor): bool
    {
        return ! $actor->hasRole(RoleCode::Admin);
    }

    /**
     * Employees keep read access to tasks they worked on after their step closes, because
     * BRD §16.1 shows completed work on their own dashboard. A view-only primary Team
     * Leader still reads their department's tasks (Q14).
     */
    public function view(User $actor, Task $task): bool
    {
        if ($actor->hasRole(RoleCode::Admin)) {
            return false;
        }

        // A creator can always see their own task — most importantly a draft, which has
        // no steps yet for the department-scoped checks below to find anything through.
        if ($task->created_by === $actor->id) {
            return true;
        }

        if ($actor->hasRole(RoleCode::Manager)) {
            return true;
        }

        if ($actor->hasRole(RoleCode::TeamLeader)) {
            return $actor->department_id !== null
                && $task->steps()->where('department_id', $actor->department_id)->exists();
        }

        return $this->hasEverBeenAssigned($actor, $task);
    }

    /** BRD §8 — only the Manager and Team Leaders create tasks. */
    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager, RoleCode::TeamLeader);
    }

    /**
     * BRD §8 — the creator edits the task's own fields only until work starts. After that
     * the route changes through Redirect (Manager only), never by editing.
     */
    public function update(User $actor, Task $task): bool
    {
        if ($task->isClosed() || $task->created_by !== $actor->id) {
            return false;
        }

        return ! $task->steps()
            ->whereHas('assignments')
            ->exists();
    }

    /** BRD §8 — only the creator publishes their own draft. */
    public function publish(User $actor, Task $task): bool
    {
        return $task->lifecycle_status === TaskLifecycle::Draft && $task->created_by === $actor->id;
    }

    /** BRD §8 — delete is only ever available before workflow entry, i.e. while still a draft. */
    public function deleteDraft(User $actor, Task $task): bool
    {
        return $task->lifecycle_status === TaskLifecycle::Draft && $task->created_by === $actor->id;
    }

    /**
     * BRD §9.10 — Finish Task belongs to the Team Leader who holds the final approved
     * step. The Manager is included because they own the task's completion in BRD §15 and
     * because Q12 makes them the reviewer of a self-assigned step, which would otherwise
     * leave that step approved with nobody able to close it.
     */
    public function complete(User $actor, Task $task): bool
    {
        if ($task->isClosed() || $actor->hasRole(RoleCode::Admin)) {
            return false;
        }

        if ($actor->hasRole(RoleCode::Manager)) {
            return true;
        }

        $step = $task->currentStep;

        return $step !== null && $actor->canActAsLeaderOf($step->department_id);
    }

    /** BRD §10 — the Manager or the task's creator, with a mandatory reason. */
    public function cancel(User $actor, Task $task): bool
    {
        if ($task->isClosed() || $actor->hasRole(RoleCode::Admin)) {
            return false;
        }

        return $this->isManagerOrCreator($actor, $task);
    }

    /** BRD §10/§11 — same authority as cancel; only an Active task may enter hold. */
    public function hold(User $actor, Task $task): bool
    {
        return $task->lifecycle_status === TaskLifecycle::Active
            && $this->isManagerOrCreator($actor, $task);
    }

    /** Symmetric to hold() — only an on-hold task may resume. */
    public function resume(User $actor, Task $task): bool
    {
        return $task->isOnHold() && $this->isManagerOrCreator($actor, $task);
    }

    /**
     * BRD §10 / §15 permission matrix ("Redirect: No / Yes, with reason / No / No" for
     * Admin/Manager/TL/Employee) — Manager only, unlike hold/cancel which the creator
     * may also do.
     */
    public function redirect(User $actor, Task $task): bool
    {
        return ! $task->isClosed() && $actor->hasRole(RoleCode::Manager);
    }

    /**
     * Approved decision Q26 — the current assignee may read the FINAL APPROVED outputs of
     * earlier completed steps of the same task, and nothing else. The filtering itself is
     * Task::approvedOutputsBefore(); this only decides who may ask.
     */
    public function viewEarlierOutputs(User $actor, Task $task): bool
    {
        return $this->view($actor, $task);
    }

    /** The task timeline (BRD §9). Same audience as the task itself. */
    public function viewHistory(User $actor, Task $task): bool
    {
        return $this->view($actor, $task);
    }

    private function hasEverBeenAssigned(User $actor, Task $task): bool
    {
        return $task->steps()
            ->whereHas('assignments', fn ($query) => $query->where('assignee_id', $actor->id))
            ->exists();
    }

    private function isManagerOrCreator(User $actor, Task $task): bool
    {
        return $actor->hasRole(RoleCode::Manager) || $task->created_by === $actor->id;
    }
}
