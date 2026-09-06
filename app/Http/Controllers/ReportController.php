<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Enums\SnapshotType;
use App\Models\MonthlyPerformanceSnapshot;
use App\Models\PerformanceAdjustment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * BRD §17 — the monthly performance report. Manager sees every department; a TL sees
 * only their own; an Employee is redirected to their own performance page.
 *
 * Product decision 2026-09 — one table instead of the old department+employee pair:
 * rows are grouped by department, each group led by that department's Team Leader
 * (their `tl_personal` snapshot, i.e. their OWN delivery) followed by that
 * department's employees. The separate department-aggregate table was dropped.
 * A Manager can attach a bonus or deduction to any row for the month being viewed.
 */
class ReportController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $actor = $request->user();

        if ($actor->hasRole(RoleCode::Employee)) {
            return redirect()->route('performance.show');
        }

        $monthStart = $this->resolveMonth($request);
        $isManager = $actor->hasRole(RoleCode::Manager);

        // tl_personal alongside employee (never tl_team): every row in this table is one
        // person's own delivery, so a TL's row stays comparable to the rows beneath it
        // instead of silently showing their whole department's aggregate.
        $snapshots = MonthlyPerformanceSnapshot::query()
            ->whereIn('snapshot_type', [SnapshotType::TlPersonal->value, SnapshotType::Employee->value])
            ->forMonth($monthStart)
            ->with(['user:id,full_name,department_id,role_id', 'user.role:id,code', 'department:id,name'])
            ->when(! $isManager, fn ($q) => $q->where('department_id', $actor->department_id))
            ->get()
            ->pipe(fn (Collection $rows) => $this->oneRowPerPerson($rows));

        // Admins carry no snapshot at all (PerformanceService::snapshotAll() only scores
        // Employees and Team Leaders), so they never appear above — but the Manager still
        // needs somewhere to record their bonus or deduction. They get their own group
        // with no performance figures, since there is nothing to measure.
        $admins = $isManager
            ? User::query()
                ->whereHas('role', fn ($q) => $q->where('code', RoleCode::Admin->value))
                ->orderBy('full_name')
                ->get(['id', 'full_name'])
            : collect();

        return view('reports.index', [
            'monthStart' => $monthStart,
            'availableMonths' => MonthlyPerformanceSnapshot::query()->distinct()->orderByDesc('month_start')->pluck('month_start'),
            'groups' => $this->groupByDepartment($snapshots),
            'admins' => $admins,
            'adjustments' => $this->adjustmentsFor(
                $snapshots->pluck('user_id')->merge($admins->pluck('id')),
                $monthStart,
            ),
            'canAdjust' => $isManager,
        ]);
    }

    /**
     * A person promoted to (or demoted from) Team Leader mid-month keeps BOTH rows:
     * PerformanceService::snapshotAll() writes the type matching their CURRENT role and
     * never clears the one written under the old role, so the same person would appear
     * twice in their department's group with two different numbers. Keep the row that
     * matches who they are now and drop the stale one.
     *
     * @param  Collection<int, MonthlyPerformanceSnapshot>  $snapshots
     * @return Collection<int, MonthlyPerformanceSnapshot>
     */
    private function oneRowPerPerson(Collection $snapshots): Collection
    {
        return $snapshots
            ->groupBy('user_id')
            ->map(function (Collection $rows) {
                if ($rows->count() === 1) {
                    return $rows->first();
                }

                $wanted = $rows->first()->user?->hasRole(RoleCode::TeamLeader)
                    ? SnapshotType::TlPersonal
                    : SnapshotType::Employee;

                return $rows->firstWhere('snapshot_type', $wanted) ?? $rows->first();
            })
            ->values();
    }

    /**
     * One entry per department — the Team Leader's own row first, then their employees
     * by name — ordered by department name, with any department-less row last.
     *
     * @param  Collection<int, MonthlyPerformanceSnapshot>  $snapshots
     * @return Collection<int, array{department: ?string, rows: Collection<int, MonthlyPerformanceSnapshot>}>
     */
    private function groupByDepartment(Collection $snapshots): Collection
    {
        return $snapshots
            ->groupBy(fn (MonthlyPerformanceSnapshot $s) => $s->department_id ?? 0)
            ->map(fn (Collection $rows) => [
                'department' => $rows->first()->department?->name,
                'rows' => $rows
                    ->sortBy([
                        fn (MonthlyPerformanceSnapshot $a, MonthlyPerformanceSnapshot $b) => $this->rankOf($a) <=> $this->rankOf($b),
                        fn (MonthlyPerformanceSnapshot $a, MonthlyPerformanceSnapshot $b) => strcmp((string) $a->user?->full_name, (string) $b->user?->full_name),
                    ])
                    ->values(),
            ])
            ->sortBy(fn (array $group) => $group['department'] ?? "\u{FFFF}")
            ->values();
    }

    /** The Team Leader leads their own group; everyone else follows. */
    private function rankOf(MonthlyPerformanceSnapshot $snapshot): int
    {
        return $snapshot->snapshot_type === SnapshotType::TlPersonal ? 0 : 1;
    }

    /**
     * Every adjustment for the month, keyed by user_id — one query for the whole page
     * rather than one per row.
     *
     * @param  Collection<int, ?int>  $ids
     */
    private function adjustmentsFor(Collection $ids, Carbon $monthStart): Collection
    {
        $userIds = $ids->filter()->unique();

        if ($userIds->isEmpty()) {
            return collect();
        }

        return PerformanceAdjustment::query()
            ->forMonth($monthStart)
            ->whereIn('user_id', $userIds)
            ->with('createdBy:id,full_name')
            ->orderBy('id')
            ->get()
            ->groupBy('user_id');
    }

    private function resolveMonth(Request $request): Carbon
    {
        $month = $request->query('month');

        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        }

        return now()->startOfMonth();
    }
}
