<?php

namespace App\Policies;

use App\Enums\DepartmentReportType;
use App\Enums\DepartmentSpecialRole;
use App\Enums\RoleCode;
use App\Models\DepartmentDailyReport;
use App\Models\User;

/**
 * Pure WHO, no state — matches TaskPolicy/TaskStepPolicy's own division of labor. The
 * "not before it's submitted" half of visibility lives in
 * DepartmentDailyReport::scopeVisibleTo() (a query concern, same as
 * Task::approvedOutputsBefore() for Q26), not here. Admin and Employee are refused
 * throughout — same "never sees task content" boundary as everywhere else in this app.
 */
class DepartmentReportPolicy
{
    public function view(User $actor, DepartmentDailyReport $report): bool
    {
        if ($actor->hasRole(RoleCode::Manager)) {
            return true;
        }

        if (! $actor->hasRole(RoleCode::TeamLeader)) {
            return false;
        }

        if ($report->department_id === $actor->department_id) {
            return true;
        }

        return $report->type === DepartmentReportType::ModeratorHandoff
            && $actor->department?->special_role === DepartmentSpecialRole::Moderator;
    }

    /**
     * Product decision 2026-09 — in a department that writes its report collectively
     * (Sales), every active member writes their own part, Employees included. This is
     * the one place an Employee touches a department report at all, and it reaches
     * exactly one row: their own contribution, never anybody else's and never the
     * report itself. Whether the department works that way, and whether it is still
     * open to write in, are state — DepartmentReportService answers both.
     */
    public function contribute(User $actor, DepartmentDailyReport $report): bool
    {
        return $actor->department_id !== null
            && $actor->department_id === $report->department_id
            && ! $actor->hasRole(RoleCode::Admin);
    }

    /** Covers Content's TL submitting both of Content's rows — both carry Content's
     *  department_id, so this needs no special-casing for the handoff type. */
    public function submit(User $actor, DepartmentDailyReport $report): bool
    {
        return $report->department->effectiveLeader()?->is($actor) ?? false;
    }

    /** Editing an already-submitted report is the same WHO as submitting it in the
     *  first place — the service layer is what tells the two apart by state. */
    public function update(User $actor, DepartmentDailyReport $report): bool
    {
        return $this->submit($actor, $report);
    }

    /** Product decision 2026-09 — only the Manager approves; "not yet submitted" and
     *  "already approved" are state, so they belong in the service, not here. */
    public function approve(User $actor, DepartmentDailyReport $report): bool
    {
        return $actor->hasRole(RoleCode::Manager);
    }
}
