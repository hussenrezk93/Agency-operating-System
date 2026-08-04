<?php

namespace App\Services;

use App\Enums\ActivationState;
use App\Enums\LeadershipType;
use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BRD §6 — a department cannot exist without a primary Team Leader, so creation and
 * the primary appointment happen in ONE transaction: a half-created department with no
 * leader can never be persisted.
 */
class DepartmentService
{
    public function __construct(private readonly AuditService $audit) {}

    public function createWithPrimaryLeader(string $name, User $primaryLeader, User $actor): Department
    {
        if ($primaryLeader->substantiveRoleCode() !== RoleCode::TeamLeader) {
            throw ValidationException::withMessages([
                'primary_leader_id' => __('The primary leader must be a Team Leader.'),
            ]);
        }

        return DB::transaction(function () use ($name, $primaryLeader, $actor) {
            $department = Department::create(['name' => $name, 'is_active' => true]);

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

            $this->audit->log(
                action: 'department.created',
                entityType: 'department',
                entityId: $department->id,
                after: ['name' => $name, 'primary_leader_id' => $primaryLeader->id],
                actorId: $actor->id,
            );

            return $department->refresh();
        });
    }
}
