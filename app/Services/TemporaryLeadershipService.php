<?php

namespace App\Services;

use App\Enums\ActivationState;
use App\Enums\LeadershipType;
use App\Enums\RoleCode;
use App\Enums\RoleTransitionReason;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * APPROVED DECISIONS Q2, Q3, Q6, Q8, Q9, Q13, Q15, Q16.
 *
 * Implemented here (foundation, no task workflow involved):
 *   · eligibility rules — same department only, Employee only, one at a time
 *   · scheduling states — pending → active → ended, idempotent (Q13)
 *   · transactional appointment, early termination and replacement (Q8, Q16)
 *   · effective-role elevation / restoration through RoleTransitionService (Q2)
 *
 * DELIBERATELY NOT implemented (Phase 1B — needs the task tables):
 *   · Q10 transfer of the primary TL's self-assigned tasks to the temporary TL
 *   · Q7/Q17 hand-back of pending leadership reviews
 *   · Q15 blocking the start of leave until a valid temporary TL exists is enforced
 *     here at appointment level only; tying it to a leave record is Phase 1B.
 * The hooks below are marked TODO and intentionally throw nothing — no half-built
 * task logic ships in Phase 1A.1.
 */
class TemporaryLeadershipService
{
    public function __construct(
        private readonly RoleTransitionService $roles,
        private readonly AuditService $audit,
    ) {}

    /** Q3 + Q2 + Q6: who may be appointed temporary TL of this department. */
    public function assertEligible(User $candidate, Department $department): void
    {
        if ($candidate->substantiveRoleCode() !== RoleCode::Employee) {
            throw ValidationException::withMessages([
                'user_id' => __('Only an Employee may be appointed Temporary Team Leader.'),
            ]);
        }

        if ($candidate->department_id !== $department->id) {
            throw ValidationException::withMessages([
                'user_id' => __('The Temporary Team Leader must belong to the same department.'),
            ]);
        }

        if ($candidate->status->value !== 'active') {
            throw ValidationException::withMessages([
                'user_id' => __('Only an active account may be appointed.'),
            ]);
        }

        $alreadyCovering = DepartmentLeadershipAssignment::query()
            ->where('user_id', $candidate->id)
            ->where('is_active', true)
            ->whereIn('activation_state', [ActivationState::Pending->value, ActivationState::Active->value])
            ->exists();

        if ($alreadyCovering) {
            throw ValidationException::withMessages([
                'user_id' => __('This user already holds a leadership assignment.'),
            ]);
        }
    }

    /**
     * Q9 + Q13: appoint a temporary TL. A period starting today activates immediately
     * (and elevates the role); a future period is stored as `pending` and activated by
     * the scheduler without any manual step.
     */
    public function appoint(
        Department $department,
        User $candidate,
        Carbon $start,
        Carbon $end,
        string $reason,
        User $actor,
    ): DepartmentLeadershipAssignment {
        $this->assertEligible($candidate, $department);

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => __('The end date must be on or after the start date.'),
            ]);
        }

        return DB::transaction(function () use ($department, $candidate, $start, $end, $reason, $actor) {
            $startsNow = $start->isToday() || $start->isPast();

            $assignment = DepartmentLeadershipAssignment::create([
                'department_id' => $department->id,
                'user_id' => $candidate->id,
                'assignment_type' => LeadershipType::Temporary->value,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'is_active' => true,
                'activation_state' => $startsNow
                    ? ActivationState::Active->value
                    : ActivationState::Pending->value,
                'reason' => $reason,
                'assigned_by' => $actor->id,
            ]);

            if ($startsNow) {
                $this->roles->elevateToTeamLeader($candidate, $assignment,
                    RoleTransitionReason::TemporaryTlStart, $actor->id);
                // TODO(Phase 1B · Q10): transfer the primary TL's self-assigned active
                // tasks to this temporary TL, preserving assignment history and deadlines.
            }

            $this->audit->log(
                action: 'temporary_tl.appointed',
                entityType: 'department_leadership_assignment',
                entityId: $assignment->id,
                after: [
                    'department' => $department->name,
                    'user_id' => $candidate->id,
                    'period' => $start->toDateString().' → '.$end->toDateString(),
                    'activation_state' => $assignment->activation_state->value,
                    'reason' => $reason,
                ],
                actorId: $actor->id,
            );

            return $assignment;
        });
    }

    /** Q8: end early — permissions and role return immediately, in one transaction. */
    public function endEarly(
        DepartmentLeadershipAssignment $assignment,
        User $actor,
        RoleTransitionReason $reason = RoleTransitionReason::TemporaryTlEarlyEnd,
    ): void {
        DB::transaction(function () use ($assignment, $actor, $reason): void {
            $assignment->forceFill([
                'is_active' => false,
                'activation_state' => ActivationState::Ended->value,
                'ended_early_at' => now(),
                'ended_by' => $actor->id,
            ])->save();

            $this->roles->restoreBaseRole($assignment->user, $assignment, $reason, $actor->id);

            // TODO(Phase 1B · Q7/Q17): return pending leadership reviews to the primary TL.
            // Personally self-assigned tasks stay with the user as Employee — no action.

            $this->audit->log(
                action: 'temporary_tl.ended_early',
                entityType: 'department_leadership_assignment',
                entityId: $assignment->id,
                before: ['activation_state' => ActivationState::Active->value],
                after: ['activation_state' => ActivationState::Ended->value, 'reason' => $reason->value],
                actorId: $actor->id,
            );
        });
    }

    /**
     * Q16: swap the temporary TL mid-period. One transaction, so the department is
     * never left with two effective temporary leaders or none.
     */
    public function replace(
        DepartmentLeadershipAssignment $current,
        User $replacement,
        Carbon $end,
        string $reason,
        User $actor,
    ): DepartmentLeadershipAssignment {
        $department = $current->department;
        $this->assertEligible($replacement, $department);

        return DB::transaction(function () use ($current, $replacement, $end, $reason, $actor, $department) {
            $this->endEarly($current, $actor, RoleTransitionReason::TemporaryTlReplaced);

            $new = $this->appoint($department, $replacement, now(), $end, $reason, $actor);

            $current->forceFill(['replaced_by_assignment_id' => $new->id])->save();

            $this->audit->log(
                action: 'temporary_tl.replaced',
                entityType: 'department_leadership_assignment',
                entityId: $current->id,
                before: ['user_id' => $current->user_id],
                after: ['user_id' => $replacement->id, 'new_assignment_id' => $new->id],
                actorId: $actor->id,
            );

            return $new;
        });
    }

    /**
     * Q13: the scheduled transition pass. Idempotent by design — running it twice
     * changes nothing. Invoked by the `agencyos:process-leadership-transitions` command.
     *
     * @return array{activated:int, ended:int}
     */
    public function processScheduledTransitions(?Carbon $asOf = null): array
    {
        $today = ($asOf ?? now())->toDateString();
        $activated = 0;
        $ended = 0;

        DepartmentLeadershipAssignment::query()
            ->where('assignment_type', LeadershipType::Temporary->value)
            ->where('is_active', true)
            ->where('activation_state', ActivationState::Pending->value)
            ->where('start_date', '<=', $today)
            ->with('user')
            ->each(function (DepartmentLeadershipAssignment $a) use (&$activated): void {
                DB::transaction(function () use ($a): void {
                    $a->forceFill(['activation_state' => ActivationState::Active->value])->save();
                    $this->roles->elevateToTeamLeader($a->user, $a,
                        RoleTransitionReason::TemporaryTlStart, null);
                    $this->audit->log('temporary_tl.auto_activated',
                        'department_leadership_assignment', $a->id, [], [], null);
                });
                $activated++;
            });

        DepartmentLeadershipAssignment::query()
            ->where('assignment_type', LeadershipType::Temporary->value)
            ->where('is_active', true)
            ->where('activation_state', ActivationState::Active->value)
            ->whereNotNull('end_date')
            ->where('end_date', '<', $today)
            ->with('user')
            ->each(function (DepartmentLeadershipAssignment $a) use (&$ended): void {
                DB::transaction(function () use ($a): void {
                    $a->forceFill([
                        'is_active' => false,
                        'activation_state' => ActivationState::Ended->value,
                    ])->save();
                    $this->roles->restoreBaseRole($a->user, $a,
                        RoleTransitionReason::TemporaryTlEnd, null);
                    // TODO(Phase 1B · Q7): hand pending reviews back to the primary TL.
                    $this->audit->log('temporary_tl.auto_ended',
                        'department_leadership_assignment', $a->id, [], [], null);
                });
                $ended++;
            });

        return ['activated' => $activated, 'ended' => $ended];
    }
}
