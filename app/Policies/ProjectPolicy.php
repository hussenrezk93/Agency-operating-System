<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Enums\RoleCode;
use App\Models\Project;
use App\Models\User;

/**
 * APPROVED DECISIONS Q22 (complete / cancel) and Q23 (hold — approved change request).
 *
 * AUTHORITY is confirmed and implemented here:
 *   the Manager, or the original project creator (including a Team Leader creator),
 *   may complete, cancel or hold a project. A closed project can never be reopened.
 *
 * The resulting BEHAVIOR is NOT implemented in Phase 1A.1 because it requires the task
 * tables: cancellation must cascade to unfinished tasks, and Hold must pause task
 * deadlines (Q22, Q23). Those services arrive in Phase 1B. Authorization living here
 * first means the rule is written once and cannot drift.
 */
class ProjectPolicy
{
    public function viewAny(User $actor): bool
    {
        return ! $actor->hasRole(RoleCode::Admin); // Admin does not see operational data
    }

    public function view(User $actor, Project $project): bool
    {
        if ($actor->hasRole(RoleCode::Admin)) {
            return false;
        }

        if ($actor->hasRole(RoleCode::Manager)) {
            return true;
        }

        // TL and Employee: only projects their department participates in (BRD §7.2).
        return $actor->department_id !== null
            && $project->departments()->where('departments.id', $actor->department_id)->exists();
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager, RoleCode::TeamLeader);
    }

    public function update(User $actor, Project $project): bool
    {
        return ! $project->isClosed() && $this->isManagerOrCreator($actor, $project);
    }

    /** Q22 — Manager or the original creator. */
    public function complete(User $actor, Project $project): bool
    {
        return ! $project->isClosed() && $this->isManagerOrCreator($actor, $project);
    }

    /** Q22 — Manager or the original creator; a reason is mandatory (enforced in the request). */
    public function cancel(User $actor, Project $project): bool
    {
        return ! $project->isClosed() && $this->isManagerOrCreator($actor, $project);
    }

    /** Q23 (approved change request) — same authority as complete/cancel. */
    public function hold(User $actor, Project $project): bool
    {
        return $project->status->acceptsNewTasks() && $this->isManagerOrCreator($actor, $project);
    }

    public function resume(User $actor, Project $project): bool
    {
        return $project->status === ProjectStatus::OnHold
            && $this->isManagerOrCreator($actor, $project);
    }

    private function isManagerOrCreator(User $actor, Project $project): bool
    {
        return $actor->hasRole(RoleCode::Manager) || $project->created_by === $actor->id;
    }
}
