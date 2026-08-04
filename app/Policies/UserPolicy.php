<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\User;

/**
 * CONFIRMED slice of BRD §15 (account administration boundaries):
 *   Admin  → manages MANAGER accounts only.
 *   Manager→ manages TL + EMPLOYEE accounts.
 *   Nobody manages Admins through the app; TL/Employee manage nobody.
 * User CRUD screens themselves are a later phase — the policy exists so the rule
 * lives in exactly one place from day one.
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
            return $subjectRole === RoleCode::Manager;
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

    /**
     * The same Admin→Manager / Manager→TL,Employee mapping as manage(), applied at
     * creation time when there is no subject user yet — only the target role.
     */
    public function createWithRole(User $actor, RoleCode $targetRole): bool
    {
        if ($actor->hasRole(RoleCode::Admin)) {
            return $targetRole === RoleCode::Manager;
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
