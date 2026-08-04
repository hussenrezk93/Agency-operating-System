<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\User;

/** CONFIRMED (BRD §15): departments are managed by the Manager. */
class DepartmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Admin, RoleCode::Manager);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }

    public function update(User $actor, Department $department): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }

    public function deactivate(User $actor, Department $department): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }

    public function reactivate(User $actor, Department $department): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }

    // Disable-with-active-tasks behavior (BRD §6) is a TASK-dependent rule → later phase.

    /** BRD §17 — a Manager sees every department's report; a TL only their own. */
    public function viewPerformance(User $actor, Department $department): bool
    {
        if ($actor->hasRole(RoleCode::Manager)) {
            return true;
        }

        return $actor->hasRole(RoleCode::TeamLeader) && $actor->department_id === $department->id;
    }
}
