<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\User;

/**
 * BRD §19 — the audit log is visible to the Admin ONLY, and to nobody else, ever.
 * It is append-only: no role may create, update or delete entries through the
 * application (a database trigger enforces the same rule independently).
 * Reading the audit log grants the Admin no operational authority over tasks or chat.
 */
class AuditLogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Admin);
    }

    public function view(User $actor, AuditLog $log): bool
    {
        return $actor->hasRole(RoleCode::Admin);
    }

    public function export(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Admin);
    }

    public function create(User $actor): bool
    {
        return false; // only AuditService writes, never a user action
    }

    public function update(User $actor, AuditLog $log): bool
    {
        return false; // append-only
    }

    public function delete(User $actor, AuditLog $log): bool
    {
        return false; // append-only
    }
}
