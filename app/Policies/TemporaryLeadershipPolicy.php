<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\User;

/**
 * APPROVED DECISIONS Q2, Q8, Q13, Q16, Q18 — appointing, replacing, scheduling and
 * ending a temporary Team Leader is a MANAGER-ONLY operation.
 *
 * The Admin is a configuration and audit role and performs no daily operational
 * action unless the BRD grants it explicitly (BRD §5) — appointing a leader is not
 * such an action. A Team Leader may not appoint their own cover, and an Employee
 * never administers leadership.
 *
 * Gate-registered against DepartmentLeadershipAssignment, and additionally enforced
 * by route middleware plus FormRequest::authorize(), so the service can never be
 * reached through an exposed endpoint without passing this policy.
 */
class TemporaryLeadershipPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }

    public function appoint(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }

    public function replace(User $actor, DepartmentLeadershipAssignment $assignment): bool
    {
        return $actor->hasRole(RoleCode::Manager) && $assignment->isTemporary();
    }

    public function endEarly(User $actor, DepartmentLeadershipAssignment $assignment): bool
    {
        return $actor->hasRole(RoleCode::Manager) && $assignment->isTemporary();
    }

    /** Q18 — department membership changes stay Manager-only, never temporary TL. */
    public function manageMembership(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }
}
