<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\User;

/**
 * BRD §7.1/§15 — Manager or Team Leader may create a client (the BRD frames TL
 * creation as happening "inline during project creation," but that is a UX moment,
 * not a separately enforceable permission). Editing / deactivating a client stays
 * Manager-only.
 */
class ClientPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager, RoleCode::TeamLeader);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager, RoleCode::TeamLeader);
    }

    public function update(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }

    public function deactivate(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }

    public function reactivate(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }
}
