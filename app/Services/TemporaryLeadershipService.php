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
 *   · no two active temporary periods overlap on the same department (Q3/Q6) — MySQL has
 *     no range-exclusion constraint, so this is enforced here under a row lock, not by
 *     the schema; see assertNoTemporaryOverlap()'s own doc comment for the locking story
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

        $this->assertNoActiveLeadershipAssignment($candidate);
    }

    private function assertNoActiveLeadershipAssignment(User $candidate): void
    {
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
     * Q3/Q6 — no two ACTIVE TEMPORARY periods may overlap on the same department. This
     * used to be a PostgreSQL `EXCLUDE USING gist` constraint; MySQL has no equivalent
     * construct at all (no range types, no exclusion indexes), so on the MySQL cutover
     * this rule moved fully to the application layer.
     *
     * MUST be called from inside the caller's DB::transaction(), after this method has
     * already locked the department row — that lock (not a lock on the assignments being
     * compared, since a genuinely non-overlapping insert has no existing row to lock) is
     * what closes the race window two near-simultaneous appoint() calls for the same
     * department would otherwise have: only one transaction can hold the department's row
     * lock at a time, so the second caller's overlap check always sees the first caller's
     * not-yet-committed insert... except it can't, since the first transaction hasn't
     * committed yet — so the second caller BLOCKS on the row lock until the first
     * transaction commits or rolls back, and only then runs its own overlap check against
     * the now-committed (or absent, if rolled back) row. Two overlapping appointments can
     * never both succeed.
     */
    private function assertNoTemporaryOverlap(Department $department, Carbon $start, Carbon $end): void
    {
        Department::whereKey($department->id)->lockForUpdate()->first();

        $overlaps = DepartmentLeadershipAssignment::query()
            ->where('department_id', $department->id)
            ->where('assignment_type', LeadershipType::Temporary->value)
            ->where('is_active', true)
            ->where('start_date', '<=', $end->toDateString())
            ->where(function ($q) use ($start): void {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $start->toDateString());
            })
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_date' => __('This period overlaps with an existing temporary leadership period for this department.'),
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
            $this->assertNoTemporaryOverlap($department, $start, $end);

            // Re-checked under a row lock: closes the race where the same candidate is
            // appointed to two different departments at nearly the same moment — the
            // department-row lock above doesn't cover this, since it's a different
            // department each time.
            User::whereKey($candidate->id)->lockForUpdate()->first();
            $this->assertNoActiveLeadershipAssignment($candidate);

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
        // Guards against a stale/replayed call targeting an assignment that has already
        // ended (naturally, or via an earlier endEarly()/replace()): without this check,
        // restoreBaseRole() below only looks at the user's GLOBAL elevation flag, not
        // whether THIS assignment is the one currently granting it — so ending an old,
        // already-inactive assignment could silently strip a role the user holds today
        // through a completely different, still-active assignment.
        if (! $assignment->is_active || $assignment->activation_state !== ActivationState::Active) {
            throw ValidationException::withMessages([
                'assignment' => __('This temporary leadership assignment has already ended.'),
            ]);
        }

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
