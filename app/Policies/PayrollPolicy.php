<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\PayrollPeriod;
use App\Models\User;

/**
 * Product decision 2026-09 — salaries, pay periods and company spending are the
 * Manager's alone. Nobody else reads them: not a Team Leader (who otherwise sees their
 * department's performance), not an Admin (whose remit is configuration and audit, and
 * who is deliberately kept out of operational content everywhere in this app), and not
 * the employee whose salary it is.
 *
 * Registered for EmployeeSalary, PayrollPeriod and Expense alike — one rule, so it is
 * written once instead of three times over.
 */
class PayrollPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }

    public function update(User $actor, mixed $model = null): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }

    public function delete(User $actor, mixed $model = null): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }

    /** Paying a month out, and undoing that — the same authority either way. */
    public function close(User $actor, PayrollPeriod $period): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }
}
