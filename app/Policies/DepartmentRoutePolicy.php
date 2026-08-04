<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\User;

/**
 * BRD §15 — the inter-department routing matrix is Admin-owned configuration.
 * The routing DECISION at "Send to Next Department" belongs to TaskRoutingService;
 * this policy only guards who may edit the matrix it reads from.
 */
class DepartmentRoutePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Admin);
    }

    public function manage(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Admin);
    }
}
