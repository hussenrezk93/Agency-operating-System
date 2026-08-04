<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Enums\SnapshotType;
use App\Models\MonthlyPerformanceSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * BRD §17 — the combined Department + Team Leader + Employee report. Manager sees
 * everything; a TL sees only their own department's row, their own TL rows, and their
 * department's employees; an Employee is redirected to their own performance page
 * (the approved prototype does this client-side — here it's a real server redirect).
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

        $departments = MonthlyPerformanceSnapshot::ofType(SnapshotType::Department)
            ->forMonth($monthStart)
            ->with('department:id,name')
            ->when(! $isManager, fn ($q) => $q->where('department_id', $actor->department_id))
            ->get();

        $teamLeaders = MonthlyPerformanceSnapshot::ofType(SnapshotType::TlPersonal)
            ->forMonth($monthStart)
            ->with('user:id,full_name,department_id', 'user.department:id,name')
            ->when(! $isManager, fn ($q) => $q->where('user_id', $actor->id))
            ->get()
            ->map(function (MonthlyPerformanceSnapshot $personal) use ($monthStart) {
                $team = MonthlyPerformanceSnapshot::ofType(SnapshotType::TlTeam)
                    ->forMonth($monthStart)
                    ->where('user_id', $personal->user_id)
                    ->first();

                return ['personal' => $personal, 'team' => $team];
            });

        $employees = MonthlyPerformanceSnapshot::ofType(SnapshotType::Employee)
            ->forMonth($monthStart)
            ->with('user:id,full_name,department_id', 'user.department:id,name')
            ->when(! $isManager, fn ($q) => $q->where('department_id', $actor->department_id))
            ->when($isManager && $request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->get();

        return view('reports.index', [
            'monthStart' => $monthStart,
            'availableMonths' => MonthlyPerformanceSnapshot::query()->distinct()->orderByDesc('month_start')->pluck('month_start'),
            'departments' => $departments,
            'teamLeaders' => $teamLeaders,
            'employees' => $employees,
            'isManager' => $isManager,
        ]);
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
