<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\User;

/**
 * Originally BRD §15's "departments are managed by the Manager"; widened by explicit
 * product decision to give Admin the same full department management Manager has.
 */
class DepartmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Admin, RoleCode::Manager);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Admin, RoleCode::Manager);
    }

    public function update(User $actor, Department $department): bool
    {
        return $actor->hasRole(RoleCode::Admin, RoleCode::Manager);
    }

    public function deactivate(User $actor, Department $department): bool
    {
        return $actor->hasRole(RoleCode::Admin, RoleCode::Manager);
    }

    public function reactivate(User $actor, Department $department): bool
    {
        return $actor->hasRole(RoleCode::Admin, RoleCode::Manager);
    }

    // BRD §6 — deactivation with active tasks is still ALLOWED; it is not blocked here.
    // DepartmentService::deactivate() alerts every Manager instead (see its own doc comment).

    /** BRD §17 — a Manager sees every department's report; a TL only their own. */
    public function viewPerformance(User $actor, Department $department): bool
    {
        if ($actor->hasRole(RoleCode::Manager)) {
            return true;
        }

        return $actor->hasRole(RoleCode::TeamLeader) && $actor->department_id === $department->id;
    }
}
