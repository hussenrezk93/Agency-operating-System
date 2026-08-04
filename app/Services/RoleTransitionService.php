<?php

namespace App\Services;

use App\Enums\RoleCode;
use App\Enums\RoleTransitionReason;
use App\Enums\RoleTransitionType;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleTransition;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * APPROVED DECISION Q2 — safe, reversible, fully traceable effective-role changes.
 *
 * `users.role_id` holds the EFFECTIVE role; `users.base_role_id` preserves the
 * substantive role while an elevation is active. The original value is therefore never
 * destroyed, and every change leaves a `user_role_transitions` row plus an audit entry.
 * Both operations are idempotent, so the scheduler (Q13) may run repeatedly.
 */
class RoleTransitionService
{
    public function __construct(private readonly AuditService $audit) {}

    /** Employee → Team Leader for the duration of a temporary assignment. */
    public function elevateToTeamLeader(
        User $user,
        DepartmentLeadershipAssignment $assignment,
        RoleTransitionReason $reason = RoleTransitionReason::TemporaryTlStart,
        ?int $performedBy = null,
    ): void {
        if ($user->isTemporarilyElevated()) {
            return; // idempotent — already elevated
        }

        DB::transaction(function () use ($user, $assignment, $reason, $performedBy): void {
            $fromRoleId = $user->role_id;
            $toRoleId = $this->roleId(RoleCode::TeamLeader);

            $user->forceFill([
                'base_role_id' => $fromRoleId,   // preserve the substantive role
                'role_id' => $toRoleId,
            ])->save();

            $this->record($user, $fromRoleId, $toRoleId, RoleTransitionType::Elevation,
                $reason, $assignment, $performedBy);

            $this->audit->log(
                action: 'user.role_elevated',
                entityType: 'user',
                entityId: $user->id,
                before: ['effective_role' => Role::find($fromRoleId)?->code],
                after: [
                    'effective_role' => RoleCode::TeamLeader->value,
                    'base_role' => Role::find($fromRoleId)?->code,
                    'leadership_assignment_id' => $assignment->id,
                    'reason' => $reason->value,
                ],
                actorId: $performedBy,
            );
        });
    }

    /** Return the user to the preserved substantive role. */
    public function restoreBaseRole(
        User $user,
        ?DepartmentLeadershipAssignment $assignment,
        RoleTransitionReason $reason,
        ?int $performedBy = null,
    ): void {
        if (! $user->isTemporarilyElevated()) {
            return; // idempotent — nothing to restore
        }

        DB::transaction(function () use ($user, $assignment, $reason, $performedBy): void {
            $fromRoleId = $user->role_id;
            $toRoleId = $user->base_role_id;

            if ($toRoleId === null) {
                throw new RuntimeException('Base role missing — refusing to guess the original role.');
            }

            $user->forceFill(['role_id' => $toRoleId, 'base_role_id' => null])->save();

            $this->record($user, $fromRoleId, $toRoleId, RoleTransitionType::Restoration,
                $reason, $assignment, $performedBy);

            $this->audit->log(
                action: 'user.role_restored',
                entityType: 'user',
                entityId: $user->id,
                before: ['effective_role' => Role::find($fromRoleId)?->code],
                after: [
                    'effective_role' => Role::find($toRoleId)?->code,
                    'leadership_assignment_id' => $assignment?->id,
                    'reason' => $reason->value,
                ],
                actorId: $performedBy,
            );
        });
    }

    private function record(
        User $user,
        int $fromRoleId,
        int $toRoleId,
        RoleTransitionType $type,
        RoleTransitionReason $reason,
        ?DepartmentLeadershipAssignment $assignment,
        ?int $performedBy,
    ): void {
        UserRoleTransition::create([
            'user_id' => $user->id,
            'from_role_id' => $fromRoleId,
            'to_role_id' => $toRoleId,
            'transition_type' => $type->value,
            'reason' => $reason->value,
            'leadership_assignment_id' => $assignment?->id,
            'performed_by' => $performedBy,
        ]);
    }

    private function roleId(RoleCode $code): int
    {
        return Role::where('code', $code->value)->firstOrFail()->id;
    }
}
