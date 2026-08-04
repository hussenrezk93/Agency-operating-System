<?php

namespace App\Services;

use App\Enums\RoleCode;
use App\Enums\SnapshotType;
use App\Enums\WorkflowStatus;
use App\Models\Department;
use App\Models\MonthlyPerformanceSnapshot;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * BRD §17 — the single place `due_steps`/`on_time_steps`/`overdue_steps` are counted.
 * Both scheduled commands (the final monthly snapshot and the daily provisional
 * refresh) and every live dashboard/report query go through this class, so "final" and
 * "in progress" numbers are never computed two different ways.
 *
 * A step belongs to the month its `current_due_at` falls in. On-time is reconstructed
 * from `approved_at <= current_due_at` — `deadline_status` becomes `Closed` the moment a
 * step closes and loses the distinction, but `approved_at` is written exactly once
 * (TaskWorkflowService::approve()) and `current_due_at` already carries any hold
 * extension (Phase 6), so "on-hold time excluded" needs no special-casing here.
 * Cancelled and Redirected steps are excluded entirely — administratively closed, not a
 * performance failure — matching the approved prototype's own stated formula text.
 */
class PerformanceService
{
    private const EXCLUDED_STATUSES = [
        WorkflowStatus::Cancelled->value,
        WorkflowStatus::Redirected->value,
    ];

    /** @return array{due: int, on_time: int, overdue: int, score: ?float} */
    public function calculateForUser(User $user, Carbon $monthStart, ?Carbon $asOf = null): array
    {
        return $this->calculate(
            fn (Builder $query) => $query->whereHas('assignments', fn ($q) => $q->where('assignee_id', $user->id)),
            $monthStart,
            $asOf,
        );
    }

    /** @return array{due: int, on_time: int, overdue: int, score: ?float} */
    public function calculateForDepartment(Department $department, Carbon $monthStart, ?Carbon $asOf = null): array
    {
        return $this->calculate(
            fn (Builder $query) => $query->where('department_id', $department->id),
            $monthStart,
            $asOf,
        );
    }

    /**
     * @param  callable(Builder): Builder  $scope
     * @return array{due: int, on_time: int, overdue: int, score: ?float}
     */
    private function calculate(callable $scope, Carbon $monthStart, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $monthEnd = $monthStart->clone()->endOfMonth();

        $base = $scope(TaskStep::query())
            ->whereNotNull('current_due_at')
            ->whereBetween('current_due_at', [$monthStart, $monthEnd])
            ->where('current_due_at', '<=', $asOf)
            ->whereNotIn('workflow_status', self::EXCLUDED_STATUSES);

        $due = (clone $base)->count();

        $onTime = (clone $base)
            ->where('workflow_status', WorkflowStatus::Approved->value)
            ->whereNotNull('approved_at')
            ->whereColumn('approved_at', '<=', 'current_due_at')
            ->count();

        $overdue = $due - $onTime;

        return [
            'due' => $due,
            'on_time' => $onTime,
            'overdue' => $overdue,
            'score' => MonthlyPerformanceSnapshot::calculateScore($due, $onTime),
        ];
    }

    /**
     * The orchestrator both `agencyos:performance-snapshot` and
     * `agencyos:performance-refresh` call: one `employee`/`tl_personal` row per
     * Employee/TeamLeader user (their own current effective role — see the Phase 9 plan's
     * stated simplification on mid-month role transitions), and one `department`+
     * `tl_team` pair per active department, computed once and written to both rows so
     * they can never disagree.
     */
    public function snapshotAll(Carbon $monthStart, ?Carbon $asOf = null): void
    {
        $monthStart = $monthStart->clone()->startOfMonth();

        User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('code', [RoleCode::Employee->value, RoleCode::TeamLeader->value]))
            ->get()
            ->each(function (User $user) use ($monthStart, $asOf): void {
                $stats = $this->calculateForUser($user, $monthStart, $asOf);
                $type = $user->hasRole(RoleCode::TeamLeader) ? SnapshotType::TlPersonal : SnapshotType::Employee;

                MonthlyPerformanceSnapshot::updateOrCreate(
                    ['user_id' => $user->id, 'month_start' => $monthStart->toDateString(), 'snapshot_type' => $type->value],
                    [
                        'department_id' => $user->department_id,
                        'due_steps' => $stats['due'],
                        'on_time_steps' => $stats['on_time'],
                        'overdue_steps' => $stats['overdue'],
                        'score' => $stats['score'],
                        'calculated_at' => now(),
                    ],
                );
            });

        Department::where('is_active', true)->get()->each(function (Department $department) use ($monthStart, $asOf): void {
            $stats = $this->calculateForDepartment($department, $monthStart, $asOf);

            MonthlyPerformanceSnapshot::updateOrCreate(
                ['department_id' => $department->id, 'user_id' => null, 'month_start' => $monthStart->toDateString(), 'snapshot_type' => SnapshotType::Department->value],
                [
                    'due_steps' => $stats['due'],
                    'on_time_steps' => $stats['on_time'],
                    'overdue_steps' => $stats['overdue'],
                    'score' => $stats['score'],
                    'calculated_at' => now(),
                ],
            );

            $leader = $department->effectiveLeader();

            if ($leader !== null) {
                MonthlyPerformanceSnapshot::updateOrCreate(
                    ['user_id' => $leader->id, 'month_start' => $monthStart->toDateString(), 'snapshot_type' => SnapshotType::TlTeam->value],
                    [
                        'department_id' => $department->id,
                        'due_steps' => $stats['due'],
                        'on_time_steps' => $stats['on_time'],
                        'overdue_steps' => $stats['overdue'],
                        'score' => $stats['score'],
                        'calculated_at' => now(),
                    ],
                );
            }
        });
    }
}
