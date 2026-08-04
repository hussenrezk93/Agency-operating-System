<?php

namespace App\Services;

use App\Exceptions\RoutingNotAllowedException;
use App\Models\Department;
use App\Models\DepartmentRoute;
use App\Models\TaskStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * BRD §9.9 / §15 / §22.4 — which department a step may be sent to next.
 *
 * The routing matrix (`department_routes`) is ADMIN-managed configuration; this service
 * only reads it. It never decides who is allowed to press "send" — that is
 * TaskStepPolicy::transfer(). Separating the two means the routing rule is asked exactly
 * one question ("is this hop permitted?") and the permission rule exactly one other
 * ("may this user move this step?"), so neither is duplicated.
 *
 * Four conditions must all hold:
 *   1. the target department is active;
 *   2. it is not the department the step is already in;
 *   3. the Admin matrix has an explicitly allowed route from → to;
 *   4. for a task inside a project, the target department participates in that project.
 *
 * Condition 4 is not in the BRD as a routing rule, but project membership — and therefore
 * WhatsApp invite eligibility (CR-001) and project visibility (BRD §7.2) — is derived from
 * the participating departments. Letting a project task reach a non-participating
 * department would put work in front of people who are not members of the project. Flagged
 * in the delivery report as a decision to confirm.
 */
class TaskRoutingService
{
    /** True when this hop is permitted. Never throws — use for building UI lists. */
    public function canTransferToDepartment(TaskStep $step, Department $target): bool
    {
        try {
            $this->assertTransferAllowed($step, $target);

            return true;
        } catch (RoutingNotAllowedException) {
            return false;
        }
    }

    /** The same rules, but explaining which one failed. */
    public function assertTransferAllowed(TaskStep $step, Department $target): void
    {
        if (! $target->is_active) {
            throw RoutingNotAllowedException::inactiveDepartment($target);
        }

        if ($target->id === $step->department_id) {
            throw RoutingNotAllowedException::sameDepartment($target);
        }

        if (! $this->routeIsAllowed($step->department_id, $target->id)) {
            throw RoutingNotAllowedException::notPermitted($step->department, $target);
        }

        $task = $step->task;

        if ($task->project_id !== null && ! $this->participatesInProject($task->project_id, $target->id)) {
            throw RoutingNotAllowedException::notInProject($target);
        }
    }

    /**
     * Every department this step may legally be sent to, in name order.
     *
     * @return Collection<int, Department>
     */
    public function allowedNextDepartments(TaskStep $step): Collection
    {
        return Department::query()
            ->where('is_active', true)
            ->whereKeyNot($step->department_id)
            ->orderBy('name')
            ->get()
            ->filter(fn (Department $target): bool => $this->canTransferToDepartment($step, $target))
            ->values();
    }

    private function routeIsAllowed(int $fromDepartmentId, int $toDepartmentId): bool
    {
        return DepartmentRoute::query()
            ->where('from_department_id', $fromDepartmentId)
            ->where('to_department_id', $toDepartmentId)
            ->where('is_allowed', true)
            ->exists();
    }

    private function participatesInProject(int $projectId, int $departmentId): bool
    {
        return DB::table('project_departments')
            ->where('project_id', $projectId)
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->exists();
    }
}
