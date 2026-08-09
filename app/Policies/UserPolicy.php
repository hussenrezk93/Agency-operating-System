<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\User;

/**
 * Account administration boundaries (originally BRD §15's Admin→Manager-only /
 * Manager→TL,Employee split; widened by explicit product decision to give Admin full
 * account administration — Admin now manages every non-Admin role, same range as
 * Manager plus Manager accounts themselves):
 *   Admin  → manages MANAGER, TEAM LEADER, and EMPLOYEE accounts.
 *   Manager→ manages TL + EMPLOYEE accounts.
 *   Nobody manages Admins through the app; TL/Employee manage nobody.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Admin, RoleCode::Manager);
    }

    public function manage(User $actor, User $subject): bool
    {
        $subjectRole = RoleCode::from($subject->role->code);

        if ($actor->hasRole(RoleCode::Admin)) {
            return in_array($subjectRole, [RoleCode::Manager, RoleCode::TeamLeader, RoleCode::Employee], true);
        }

        if ($actor->hasRole(RoleCode::Manager)) {
            return in_array($subjectRole, [RoleCode::TeamLeader, RoleCode::Employee], true);
        }

        return false;
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Admin, RoleCode::Manager);
    }

    /** The same manage() role range, applied at creation time when there is no subject yet. */
    public function createWithRole(User $actor, RoleCode $targetRole): bool
    {
        if ($actor->hasRole(RoleCode::Admin)) {
            return in_array($targetRole, [RoleCode::Manager, RoleCode::TeamLeader, RoleCode::Employee], true);
        }

        if ($actor->hasRole(RoleCode::Manager)) {
            return in_array($targetRole, [RoleCode::TeamLeader, RoleCode::Employee], true);
        }

        return false;
    }

    /**
     * BRD §17 — the same visibility every report/dashboard boundary already uses
     * (TaskPolicy/ProjectPolicy): a user always sees their own, a Manager sees everyone's,
     * a TL sees only their own department's.
     */
    public function viewPerformance(User $actor, User $subject): bool
    {
        if ($actor->is($subject)) {
            return true;
        }

        if ($actor->hasRole(RoleCode::Manager)) {
            return true;
        }

        return $actor->hasRole(RoleCode::TeamLeader)
            && $actor->department_id !== null
            && $actor->department_id === $subject->department_id;
    }
}
