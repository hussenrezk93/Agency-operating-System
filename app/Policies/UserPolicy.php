<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\User;

/**
 * Account administration boundaries (originally BRD §15's Admin→Manager-only /
 * Manager→TL,Employee split; widened by explicit product decision to give Admin full
 * account administration — Admin now manages every role including other Admins; and
 * again in 2026-09 to give Manager the same peer-management as Admin got):
 *   Admin  → manages ADMIN (except itself), MANAGER, TEAM LEADER, and EMPLOYEE accounts.
 *   Manager→ manages MANAGER (except itself), TEAM LEADER, and EMPLOYEE accounts.
 *   TL/Employee manage nobody.
 *
 * Product decision (2026-08, accepted as a deliberate security trade-off): Admin may
 * now create AND fully manage other Admin accounts — edit, reset password, disable —
 * not just create them as before. The one guard kept regardless: an Admin can never
 * manage() its OWN account through this admin panel (self-lockout prevention — could
 * otherwise disable the only admin with no recovery path short of direct database
 * access); use /profile to edit your own name/email instead.
 *
 * Product decision (2026-09): Manager gets the identical peer-management widening —
 * see other Managers in the users list and fully manage them, but never itself, same
 * self-lockout guard as Admin's. Manager still does not reach Admin accounts.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Admin, RoleCode::Manager);
    }

    public function manage(User $actor, User $subject): bool
    {
        if ($actor->is($subject)) {
            return false;
        }

        $subjectRole = RoleCode::from($subject->role->code);

        if ($actor->hasRole(RoleCode::Admin)) {
            return in_array($subjectRole, [RoleCode::Admin, RoleCode::Manager, RoleCode::TeamLeader, RoleCode::Employee], true);
        }

        if ($actor->hasRole(RoleCode::Manager)) {
            return in_array($subjectRole, [RoleCode::Manager, RoleCode::TeamLeader, RoleCode::Employee], true);
        }

        return false;
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Admin, RoleCode::Manager);
    }

    /** manage()'s role range at creation time, PLUS Admin creating another Admin (see class doc). */
    public function createWithRole(User $actor, RoleCode $targetRole): bool
    {
        if ($actor->hasRole(RoleCode::Admin)) {
            return in_array($targetRole, [RoleCode::Admin, RoleCode::Manager, RoleCode::TeamLeader, RoleCode::Employee], true);
        }

        if ($actor->hasRole(RoleCode::Manager)) {
            return in_array($targetRole, [RoleCode::Manager, RoleCode::TeamLeader, RoleCode::Employee], true);
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
