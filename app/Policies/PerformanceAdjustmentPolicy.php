<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\PerformanceAdjustment;
use App\Models\User;

/**
 * Product decision 2026-09 — bonuses and deductions are the Manager's call alone. A
 * Team Leader reads the reports page (their own department) but never sets money on
 * it; Admin stays out, same "never touches operational content" boundary it has
 * everywhere else in this app.
 */
class PerformanceAdjustmentPolicy
{
    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }

    /** Correcting a mistyped amount means deleting the row and adding it again —
     *  there is no edit, so the reason attached to an amount can never drift. */
    public function delete(User $actor, PerformanceAdjustment $adjustment): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }
}
