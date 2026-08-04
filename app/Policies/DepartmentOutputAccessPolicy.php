<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\User;

/**
 * BRD §15 "قاعدة مشاهدة المخرجات" — which department's TL may view another
 * department's outputs is Admin-owned configuration. Employees never receive this
 * general right (their in-task visibility is a separate, task-engine rule).
 */
class DepartmentOutputAccessPolicy
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
