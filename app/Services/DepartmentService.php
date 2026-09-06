<?php

namespace App\Services;

use App\Enums\ActivationState;
use App\Enums\DepartmentSpecialRole;
use App\Enums\LeadershipType;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\DepartmentOutputAccess;
use App\Models\DepartmentRoute;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BRD §6 — a department cannot be ACTIVE without a primary Team Leader. Product
 * decision (2026-08): creation itself no longer requires one — Admin/Manager may
 * create a leaderless department, but it is persisted inactive (`is_active` false)
 * and stays unusable everywhere `is_active` is checked (task routing, project
 * assignment, user assignment, etc.) until assignPrimaryLeader() gives it one.
 */
class DepartmentService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function create(string $name, ?User $primaryLeader, User $actor): Department
    {
        if ($primaryLeader !== null && $primaryLeader->substantiveRoleCode() !== RoleCode::TeamLeader) {
            throw ValidationException::withMessages([
                'primary_leader_id' => __('The primary leader must be a Team Leader.'),
            ]);
        }

        return DB::transaction(function () use ($name, $primaryLeader, $actor) {
            $department = Department::create(['name' => $name, 'is_active' => $primaryLeader !== null]);

            if ($primaryLeader !== null) {
                $department->leadershipAssignments()->create([
                    'user_id' => $primaryLeader->id,
                    'assignment_type' => LeadershipType::Primary->value,
                    'start_date' => now()->toDateString(),
                    'is_active' => true,
                    'activation_state' => ActivationState::Active->value,
                    'assigned_by' => $actor->id,
                ]);

                // A TL leads exactly one department (BRD §6).
                $primaryLeader->forceFill(['department_id' => $department->id])->save();
            }

            $this->audit->log(
                action: 'department.created',
                entityType: 'department',
                entityId: $department->id,
                after: ['name' => $name, 'primary_leader_id' => $primaryLeader?->id],
                actorId: $actor->id,
            );

            return $department->refresh();
        });
    }

    /**
     * Gives a leaderless department its primary Team Leader and, as a direct
     * consequence, activates it — this is the only way a department created via
     * create() with no leader ever becomes usable.
     */
    public function assignPrimaryLeader(Department $department, User $primaryLeader, User $actor): Department
    {
        if ($department->primaryLeader() !== null) {
            throw ValidationException::withMessages([
                'primary_leader_id' => __('This department already has a primary leader.'),
            ]);
        }

        if ($primaryLeader->substantiveRoleCode() !== RoleCode::TeamLeader) {
            throw ValidationException::withMessages([
                'primary_leader_id' => __('The primary leader must be a Team Leader.'),
            ]);
        }

        return DB::transaction(function () use ($department, $primaryLeader, $actor) {
            $department->leadershipAssignments()->create([
                'user_id' => $primaryLeader->id,
                'assignment_type' => LeadershipType::Primary->value,
                'start_date' => now()->toDateString(),
                'is_active' => true,
                'activation_state' => ActivationState::Active->value,
                'assigned_by' => $actor->id,
            ]);

            $primaryLeader->forceFill(['department_id' => $department->id])->save();
            $department->update(['is_active' => true]);

            $this->audit->log(
                action: 'department.leader_assigned',
                entityType: 'department',
                entityId: $department->id,
                before: ['primary_leader_id' => null, 'is_active' => false],
                after: ['primary_leader_id' => $primaryLeader->id, 'is_active' => true],
                actorId: $actor->id,
            );

            return $department->refresh();
        });
    }

    /** BRD §15 — the Admin routing-permissions matrix `TaskRoutingService` reads from. */
    public function upsertRoute(int $fromDepartmentId, int $toDepartmentId, bool $isAllowed, User $actor): DepartmentRoute
    {
        return DB::transaction(function () use ($fromDepartmentId, $toDepartmentId, $isAllowed, $actor): DepartmentRoute {
            $before = DepartmentRoute::where('from_department_id', $fromDepartmentId)
                ->where('to_department_id', $toDepartmentId)
                ->first();

            $route = DepartmentRoute::updateOrCreate(
                ['from_department_id' => $fromDepartmentId, 'to_department_id' => $toDepartmentId],
                ['is_allowed' => $isAllowed, 'updated_by' => $actor->id, 'updated_at' => now()],
            );

            $this->audit->log(
                action: 'department_route.updated',
                entityType: 'department_route',
                entityId: $route->id,
                before: ['is_allowed' => $before?->is_allowed],
                after: ['from_department_id' => $fromDepartmentId, 'to_department_id' => $toDepartmentId, 'is_allowed' => $isAllowed],
                actorId: $actor->id,
            );

            return $route;
        });
    }

    /** BRD §15 — the Admin output-access matrix controlling cross-department output visibility. */
    public function upsertOutputAccess(int $viewerDepartmentId, int $sourceDepartmentId, string $scope, bool $isAllowed, User $actor): DepartmentOutputAccess
    {
        return DB::transaction(function () use ($viewerDepartmentId, $sourceDepartmentId, $scope, $isAllowed, $actor): DepartmentOutputAccess {
            $before = DepartmentOutputAccess::where('viewer_department_id', $viewerDepartmentId)
                ->where('source_department_id', $sourceDepartmentId)
                ->first();

            $rule = DepartmentOutputAccess::updateOrCreate(
                ['viewer_department_id' => $viewerDepartmentId, 'source_department_id' => $sourceDepartmentId],
                ['scope' => $scope, 'is_allowed' => $isAllowed, 'updated_by' => $actor->id, 'updated_at' => now()],
            );

            $this->audit->log(
                action: 'department_output_access.updated',
                entityType: 'department_output_access',
                entityId: $rule->id,
                before: ['is_allowed' => $before?->is_allowed, 'scope' => $before?->scope],
                after: ['viewer_department_id' => $viewerDepartmentId, 'source_department_id' => $sourceDepartmentId, 'scope' => $scope, 'is_allowed' => $isAllowed],
                actorId: $actor->id,
            );

            return $rule;
        });
    }

    public function rename(Department $department, string $name, User $actor): Department
    {
        $before = $department->name;

        $department->update(['name' => $name]);

        $this->audit->log(
            action: 'department.updated',
            entityType: 'department',
            entityId: $department->id,
            before: ['name' => $before],
            after: ['name' => $name],
            actorId: $actor->id,
        );

        return $department;
    }

    /** Daily Department Reports needs a stable identifier for Content/Moderator/
     *  Photography-Videography, independent of the freely-editable name (see
     *  DepartmentReportService's own doc comment). Admin sets it here, once. */
    public function updateSpecialRole(Department $department, ?DepartmentSpecialRole $specialRole, User $actor): Department
    {
        $before = $department->special_role;

        $department->update(['special_role' => $specialRole]);

        $this->audit->log(
            action: 'department.special_role_updated',
            entityType: 'department',
            entityId: $department->id,
            before: ['special_role' => $before?->value],
            after: ['special_role' => $specialRole?->value],
            actorId: $actor->id,
        );

        return $department;
    }

    /** BRD §6 — deactivating a department that still has active tasks alerts every Manager. */
    public function deactivate(Department $department, User $actor): Department
    {
        $department->update(['is_active' => false]);

        $this->audit->log(
            action: 'department.deactivated',
            entityType: 'department',
            entityId: $department->id,
            before: ['is_active' => true],
            after: ['is_active' => false],
            actorId: $actor->id,
        );

        if ($this->hasActiveTasks($department)) {
            $this->alertManagersOfActiveTasks($department);
        }

        return $department;
    }

    private function hasActiveTasks(Department $department): bool
    {
        return Task::query()
            ->whereHas('currentStep', fn ($q) => $q->where('department_id', $department->id))
            ->whereNotIn('lifecycle_status', ['completed', 'cancelled'])
            ->exists();
    }

    private function alertManagersOfActiveTasks(Department $department): void
    {
        User::query()
            ->whereHas('role', fn ($q) => $q->where('code', RoleCode::Manager->value))
            ->where('status', UserStatus::Active->value)
            ->get()
            ->each(function (User $manager) use ($department): void {
                $this->notifications->notify(
                    $manager,
                    'department.deactivated_with_active_tasks',
                    __('agencyos.notifications.messages.department_deactivated_title'),
                    __('agencyos.notifications.messages.department_deactivated_body', [
                        'department' => $department->name,
                    ]),
                    'department',
                    $department->id,
                    allowEmail: false,
                );
            });
    }

    public function reactivate(Department $department, User $actor): Department
    {
        $department->update(['is_active' => true]);

        $this->audit->log(
            action: 'department.reactivated',
            entityType: 'department',
            entityId: $department->id,
            before: ['is_active' => false],
            after: ['is_active' => true],
            actorId: $actor->id,
        );

        return $department;
    }
}
