<?php

namespace App\Http\Controllers;

use App\Enums\DeadlineStatus;
use App\Enums\DepartmentSpecialRole;
use App\Enums\LeadershipType;
use App\Enums\ProjectStatus;
use App\Enums\RoleCode;
use App\Enums\SnapshotType;
use App\Enums\TaskLifecycle;
use App\Enums\UserStatus;
use App\Enums\WorkflowStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\MonthlyPerformanceSnapshot;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskRedirect;
use App\Models\TaskStep;
use App\Models\TaskStepAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** BRD §16 — one dashboard per role, each showing what that role actually acts on. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return match ($user->roleCode()) {
            RoleCode::Employee => $this->employeeDashboard($user),
            RoleCode::TeamLeader => $this->tlDashboard($user),
            RoleCode::Manager => $this->managerDashboard($user),
            default => $this->adminDashboard($user),
        };
    }

    private function employeeDashboard(User $user): View
    {
        $openAssignments = $user->openStepAssignments()->with('step.task:id,title,task_code')->get();

        return view('dashboard.employee', [
            'current' => $openAssignments->where('step.workflow_status', WorkflowStatus::InProgress)->count(),
            'changesRequested' => $openAssignments->where('step.workflow_status', WorkflowStatus::ChangesRequested)->count(),
            'overdue' => $openAssignments->where('step.deadline_status', DeadlineStatus::Overdue)->count(),
            'completed' => Task::whereHas('steps.assignments', fn ($q) => $q->where('assignee_id', $user->id))
                ->where('lifecycle_status', TaskLifecycle::Completed->value)
                ->count(),
            'score' => $this->currentMonthSnapshot($user, SnapshotType::Employee),
            'myTasks' => $openAssignments->take(10),
            // My activity: status-change events I personally caused, last 14 days —
            // real history from task_status_history.changed_by, not a fabricated series.
            'activitySeries' => $this->dailyStatusHistoryEvents(changedBy: $user->id),
            // Free — no new query, just re-shaping the same $openAssignments already
            // loaded above.
            // No Waiting assignment segment here: an employee's own assignments are
            // never in that state — it belongs to a step before anyone is assigned.
            'statusSegments' => $this->statusSegments(
                $openAssignments->countBy(fn ($a) => $a->step->workflow_status->value)->all(),
                includeWaitingAssignment: false,
            ),
            'upcomingDeadlines' => $openAssignments
                ->filter(fn ($a) => $a->step->current_due_at !== null)
                ->sortBy('step.current_due_at')
                ->take(5),
            'recentNotifications' => $user->notifications()->latest('created_at')->limit(5)->get(),
        ]);
    }

    private function tlDashboard(User $user): View
    {
        $departmentId = $user->department_id;
        $deptSteps = TaskStep::where('department_id', $departmentId);

        $tasksByEmployee = TaskStepAssignment::whereNull('ended_at')
            ->whereHas('step', fn ($q) => $q->where('department_id', $departmentId))
            ->with(['assignee:id,full_name', 'step.task:id,title,task_code'])
            ->get()
            ->groupBy('assignee_id');

        $statusCounts = (clone $deptSteps)
            ->select('workflow_status', DB::raw('count(*) as total'))
            ->groupBy('workflow_status')
            ->pluck('total', 'workflow_status');

        // Product decision 2026-09 — Content's leader also reviews Graphic's steps, which
        // sit in another department, so their review queue is not a plain department
        // scope like every other number on this dashboard.
        $content = Department::withSpecialRole(DepartmentSpecialRole::Content);
        $reviewsForContent = $content !== null && $user->canActAsLeaderOf($content->id);

        $reviewQueue = fn (): Builder => TaskStep::query()
            ->where(function (Builder $q) use ($departmentId, $reviewsForContent): void {
                // Q12 — a step the leader assigned to themself is the Manager's to
                // review, so it is not a review awaiting THEM. Matches the same rule in
                // TaskController's "awaiting my review" filter, which this card links to.
                $q->where(fn (Builder $own) => $own
                    ->where('department_id', $departmentId)
                    ->where('workflow_status', WorkflowStatus::UnderReview->value)
                    ->whereDoesntHave('activeAssignment', fn (Builder $a) => $a->where('is_self_assigned', true)));

                if ($reviewsForContent) {
                    $q->orWhere('workflow_status', WorkflowStatus::PendingContentReview->value);
                }
            });

        return view('dashboard.tl', [
            'waitingAssignment' => (clone $deptSteps)->where('workflow_status', WorkflowStatus::WaitingAssignment->value)->count(),
            'inProgress' => (clone $deptSteps)->where('workflow_status', WorkflowStatus::InProgress->value)->count(),
            'awaitingReview' => $reviewQueue()->count(),
            'overdue' => (clone $deptSteps)->where('deadline_status', DeadlineStatus::Overdue->value)->count(),
            'waitingAssignmentSteps' => (clone $deptSteps)->where('workflow_status', WorkflowStatus::WaitingAssignment->value)
                ->with('task:id,title,task_code')->limit(10)->get(),
            'submissionsAwaitingReview' => $reviewQueue()
                ->with(['task:id,title,task_code', 'activeAssignment.assignee:id,full_name'])->limit(10)->get(),
            'tasksByEmployee' => $tasksByEmployee,
            'myTasks' => $user->openStepAssignments()->where('is_self_assigned', true)->with('step.task:id,title,task_code')->get(),
            'personalScore' => $this->currentMonthSnapshot($user, SnapshotType::TlPersonal),
            'teamScore' => $this->currentMonthSnapshot($user, SnapshotType::TlTeam),
            'nextDeadlines' => (clone $deptSteps)->whereNotNull('current_due_at')
                ->whereIn('workflow_status', [WorkflowStatus::InProgress->value, WorkflowStatus::ChangesRequested->value])
                ->orderBy('current_due_at')->with('task:id,title,task_code')->limit(5)->get(),
            // Department status transitions, last 14 days — real history, same
            // task_status_history mechanism the Admin/Manager charts use, just scoped.
            'activitySeries' => $this->dailyStatusHistoryEvents(departmentId: $departmentId),
            'statusSegments' => $this->statusSegments($statusCounts->all()),
            // Immutable assignment timestamps, unlike workflow_status — a real
            // week-over-week comparison for this one department.
            'newAssignmentsTrend' => $this->weekOverWeekCount(
                fn ($start, $end) => TaskStepAssignment::whereHas('step', fn ($q) => $q->where('department_id', $departmentId))
                    ->whereBetween('assigned_at', [$start, $end])->count(),
            ),
            'recentNotifications' => $user->notifications()->latest('created_at')->limit(5)->get(),
        ]);
    }

    private function managerDashboard(User $user): View
    {
        $monthStart = now()->startOfMonth();

        return view('dashboard.manager', [
            'activeTasks' => Task::where('lifecycle_status', TaskLifecycle::Active->value)->count(),
            // Task creation timestamps are immutable, unlike lifecycle_status (a live
            // field with no history log) — a real week-over-week comparison is only
            // honest here, on a count of NEW tasks, not on a snapshot like "active now".
            'newTasksTrend' => $this->weekOverWeekCount(
                fn ($start, $end) => Task::whereBetween('created_at', [$start, $end])->count(),
            ),
            'overdueStepsCount' => TaskStep::where('deadline_status', DeadlineStatus::Overdue->value)->count(),
            'waitingAssignment' => TaskStep::where('workflow_status', WorkflowStatus::WaitingAssignment->value)->count(),
            'activeProjectsCount' => Project::where('status', ProjectStatus::Active->value)->count(),
            'overdueSteps' => TaskStep::where('deadline_status', DeadlineStatus::Overdue->value)
                ->with(['task:id,title,task_code', 'department:id,name'])->limit(10)->get(),
            'onHoldTasks' => Task::where('lifecycle_status', TaskLifecycle::OnHold->value)
                ->with('currentStep.department:id,name')->limit(10)->get(),
            // Product decision 2026-09 — every step a TL approved now waits on a
            // mandatory Manager review before it can route/finish; this is the real
            // "awaiting Manager review" pool (previously just self-assigned steps
            // stuck at UnderReview per the narrower Q12 carve-out).
            'awaitingManagerReview' => TaskStep::where('workflow_status', WorkflowStatus::PendingManagerReview->value)
                ->with(['task:id,title,task_code', 'department:id,name'])->limit(10)->get(),
            // BRD §16.3 — a step a Manager's Redirect sent somewhere new, still waiting
            // for that department's TL to pick it up.
            'redirectedAwaitingAssignment' => TaskStep::whereIn('id', TaskRedirect::query()->select('to_step_id'))
                ->where('workflow_status', WorkflowStatus::WaitingAssignment->value)
                ->with(['task:id,title,task_code', 'department:id,name'])->limit(10)->get(),
            'departmentScores' => MonthlyPerformanceSnapshot::ofType(SnapshotType::Department)
                ->forMonth($monthStart)->with('department:id,name')->get(),
            'activeTempTls' => DepartmentLeadershipAssignment::currentlyActive()
                ->where('assignment_type', LeadershipType::Temporary->value)
                ->with(['user:id,full_name', 'department:id,name'])->get(),
            'activeProjects' => Project::where('status', ProjectStatus::Active->value)
                ->with('client:id,name')->latest('id')->limit(10)->get(),
            // Org-wide status transitions, last 14 days — real history from
            // task_status_history, the same mechanism TL/Employee use, just unscoped.
            'activitySeries' => $this->dailyStatusHistoryEvents(),
            'statusSegments' => $this->statusSegments(
                TaskStep::query()->select('workflow_status', DB::raw('count(*) as total'))
                    ->groupBy('workflow_status')->pluck('total', 'workflow_status')->all(),
            ),
            'upcomingDeadlines' => TaskStep::whereNotNull('current_due_at')
                ->where('current_due_at', '<=', now()->addDays(7))
                ->whereIn('workflow_status', [WorkflowStatus::InProgress->value, WorkflowStatus::ChangesRequested->value, WorkflowStatus::WaitingAssignment->value])
                ->with(['task:id,title,task_code', 'department:id,name'])
                ->orderBy('current_due_at')->limit(6)->get(),
            'recentTransfers' => TaskRedirect::with([
                'task:id,title,task_code', 'fromStep.department:id,name', 'toDepartment:id,name', 'redirectedBy:id,full_name',
            ])->latest('created_at')->limit(5)->get(),
        ]);
    }

    /**
     * BRD §15 says Admin "grants no right to view output content or task detail" — a
     * later, explicit product decision widens that to allow aggregate COUNTS per
     * department (what's moving, what's stuck), while keeping the original boundary
     * intact for anything that would actually be content: no task titles, no output
     * text, no assignee names, no links into a task's page. Every query below is a
     * grouped count; nothing here can be clicked through to a task or project.
     */
    private function adminDashboard(User $user): View
    {
        $stepStatusCounts = DB::table('task_steps')
            ->select('department_id', 'workflow_status', DB::raw('count(*) as total'))
            ->groupBy('department_id', 'workflow_status')
            ->get()
            ->groupBy('department_id');

        $overdueCounts = DB::table('task_steps')
            ->where('deadline_status', DeadlineStatus::Overdue->value)
            ->select('department_id', DB::raw('count(*) as total'))
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        // A real "approaching deadline, not yet overdue" count — DeadlineService already
        // maintains this as its own deadline_status (Q9), so the health legend's third
        // row is a genuine breakdown, not a fabricated middle value.
        $dueSoonCount = DB::table('task_steps')->where('deadline_status', DeadlineStatus::DueSoon->value)->count();

        $headcounts = DB::table('users')
            ->where('status', UserStatus::Active->value)
            ->whereNotNull('department_id')
            ->select('department_id', DB::raw('count(*) as total'))
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        $activeProjectCounts = DB::table('project_departments')
            ->join('projects', 'projects.id', '=', 'project_departments.project_id')
            ->where('project_departments.is_active', true)
            ->where('projects.status', ProjectStatus::Active->value)
            ->select('project_departments.department_id as department_id', DB::raw('count(distinct projects.id) as total'))
            ->groupBy('project_departments.department_id')
            ->pluck('total', 'department_id');

        $departmentSummaries = Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Department $department) use ($stepStatusCounts, $overdueCounts, $headcounts, $activeProjectCounts): array {
                $statuses = collect($stepStatusCounts->get($department->id, []))->pluck('total', 'workflow_status');

                return [
                    'name' => $department->name,
                    'waitingAssignment' => (int) ($statuses[WorkflowStatus::WaitingAssignment->value] ?? 0),
                    'inProgress' => (int) ($statuses[WorkflowStatus::InProgress->value] ?? 0),
                    'underReview' => (int) ($statuses[WorkflowStatus::UnderReview->value] ?? 0),
                    // Not shown as its own column — folded into "open steps" below,
                    // since a step here is still live work, just bounced back once.
                    'changesRequested' => (int) ($statuses[WorkflowStatus::ChangesRequested->value] ?? 0),
                    'overdue' => (int) ($overdueCounts[$department->id] ?? 0),
                    'activeProjects' => (int) ($activeProjectCounts[$department->id] ?? 0),
                    'headcount' => (int) ($headcounts[$department->id] ?? 0),
                ];
            });

        // The headline row above the table is just this same data summed across
        // departments — one query result, two views of it, so the two can never drift.
        // activeProjects is the one exception: a project can be linked to several
        // departments at once (project_departments is many-to-many), so summing the
        // per-department distinct counts double-counts any project that spans more
        // than one — a single 6-department project would read as "6 active projects"
        // org-wide. Counted directly here instead, the same distinct-project query
        // the TL/Manager dashboards already use.
        $totals = [
            'waitingAssignment' => $departmentSummaries->sum('waitingAssignment'),
            'inProgress' => $departmentSummaries->sum('inProgress'),
            'underReview' => $departmentSummaries->sum('underReview'),
            'overdue' => $departmentSummaries->sum('overdue'),
            'activeProjects' => Project::where('status', ProjectStatus::Active->value)->count(),
        ];

        // A live snapshot, not the BRD §17 monthly Performance Score (which judges
        // people/departments over a month and stays off this page) — just "of what's
        // currently open, how much is overdue," reduced to one number and a band.
        // `overdue` is a deadline FLAG (Q7), not its own workflow status — a step
        // already counted in waiting/inProgress/underReview/changesRequested can ALSO
        // be overdue, so it must never be added again on top of those four or every
        // overdue step would be double-counted in the denominator.
        $openSteps = $totals['waitingAssignment'] + $totals['inProgress'] + $totals['underReview']
            + $departmentSummaries->sum('changesRequested');
        // Distinct from "0 open steps = a clean, healthy 100": this flag lets the view
        // show a real empty state ("No data yet") instead of implying a measurement
        // that was never actually taken.
        $hasHealthData = $openSteps > 0;
        $healthScore = $hasHealthData ? (int) round((1 - $totals['overdue'] / $openSteps) * 100) : 100;
        $healthBand = match (true) {
            $healthScore >= 80 => 'success',
            $healthScore >= 50 => 'warning',
            default => 'danger',
        };
        // healthScore already treats "due soon" steps as on-time; pulling dueSoonRate
        // back out of it gives three real, additive legend rows (on time / due soon /
        // overdue) instead of just the two the score itself distinguishes.
        $dueSoonRate = $hasHealthData ? (int) round($dueSoonCount / $openSteps * 100) : 0;
        $onTimeRate = max(0, $healthScore - $dueSoonRate);

        // A real, working range control (7/14/30 days) — never a decorative button with
        // nothing behind it.
        $chartRangeDays = (int) request('range', 14);
        $chartRangeDays = in_array($chartRangeDays, [7, 14, 30], true) ? $chartRangeDays : 14;

        return view('dashboard', [
            'user' => $user,
            'roleLabel' => __('agencyos.roles.'.$user->roleCode()->value),
            'departmentSummaries' => $departmentSummaries,
            'totals' => $totals,
            'healthScore' => $healthScore,
            'hasHealthData' => $hasHealthData,
            'onTimeRate' => $onTimeRate,
            'dueSoonRate' => $dueSoonRate,
            'healthBand' => $healthBand,
            'overdueRate' => 100 - $healthScore,
            'chartRangeDays' => $chartRangeDays,
            'activitySeries' => $this->dailyTaskActivity($chartRangeDays),
            'newTasksTrend' => $this->weekOverWeekCount(
                fn ($start, $end) => Task::whereBetween('created_at', [$start, $end])->count(),
            ),
            // Recent Activity: the existing, Admin-exclusive audit log (BRD §19) —
            // system-wide, not just this Admin's own actions, same as the full audit
            // log page. A short label per row via AuditLogPresenter; the underlying
            // AuditLogController/page is untouched.
            'recentActivity' => AuditLog::with('actor:id,full_name')->latest('created_at')->limit(5)->get(),
            'newestTeamMembers' => User::where('status', UserStatus::Active->value)
                ->latest('created_at')->with('department:id,name')->limit(5)->get(),
        ]);
    }

    /**
     * Tasks created per day for the last N days (default 14) — a real, honest activity
     * trend built from `tasks.created_at` (an immutable timestamp), not a fabricated
     * series. Counts only: no task titles or links, same boundary as the rest of this
     * dashboard.
     *
     * @return list<array{date: Carbon, count: int}>
     */
    private function dailyTaskActivity(int $days = 14): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $byDay = DB::table('tasks')
            ->where('created_at', '>=', $start)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($start, $byDay): array {
                $date = $start->copy()->addDays($offset);

                return ['date' => $date, 'count' => (int) ($byDay[$date->toDateString()] ?? 0)];
            })
            ->all();
    }

    /**
     * Workflow status transitions per day for the last 14 days, from
     * `task_status_history` — an existing, already-populated event log (BRD §10
     * control actions and the ordinary review cycle both write to it), not a new
     * tracking mechanism. Optionally scoped to one department or one actor, which is
     * what makes this the same method for Manager (unscoped), TL (department_id) and
     * Employee (changed_by) — never department-content, just a per-day count.
     *
     * @return list<array{date: Carbon, count: int}>
     */
    private function dailyStatusHistoryEvents(?int $departmentId = null, ?int $changedBy = null): array
    {
        $start = now()->subDays(13)->startOfDay();

        $byDay = DB::table('task_status_history')
            ->where('created_at', '>=', $start)
            ->when($departmentId !== null, fn ($q) => $q->where('department_id', $departmentId))
            ->when($changedBy !== null, fn ($q) => $q->where('changed_by', $changedBy))
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, 13))
            ->map(function (int $offset) use ($start, $byDay): array {
                $date = $start->copy()->addDays($offset);

                return ['date' => $date, 'count' => (int) ($byDay[$date->toDateString()] ?? 0)];
            })
            ->all();
    }

    /**
     * Turns a plain [workflow_status_value => count] map into <x-donut-chart> segments,
     * using the SAME colors the app's existing status badges already use
     * (.b-waiting/.b-progress/.b-review/.b-changes in agencyos.css) so a segment means
     * the same thing here as it does everywhere else in the UI.
     *
     * @param  array<string, int>  $counts
     * @return list<array{label: string, count: int, color: string}>
     */
    private function statusSegments(array $counts, bool $includeWaitingAssignment = true): array
    {
        $rows = [];

        if ($includeWaitingAssignment) {
            $rows[WorkflowStatus::WaitingAssignment->value] = ['label' => __('agencyos.dashboard_admin.column_waiting_assignment'), 'color' => '#94A3B8'];
        }

        $rows += [
            WorkflowStatus::InProgress->value => ['label' => __('agencyos.dashboard_admin.column_in_progress'), 'color' => 'var(--color-info)'],
            WorkflowStatus::UnderReview->value => ['label' => __('agencyos.dashboard_admin.column_under_review'), 'color' => 'var(--color-primary)'],
            WorkflowStatus::ChangesRequested->value => ['label' => __('agencyos.tasks.actions.request_changes'), 'color' => 'var(--color-warning)'],
        ];

        return collect($rows)
            ->map(fn ($row, $status) => ['label' => $row['label'], 'color' => $row['color'], 'count' => (int) ($counts[$status] ?? 0)])
            ->values()
            ->all();
    }

    /**
     * A generic this-week-vs-last-week comparison for any closure that counts rows in a
     * date range — used only where the underlying column is an immutable timestamp
     * (never a live/current-state field, which has no history to compare against).
     *
     * @param  \Closure(Carbon, Carbon): int  $countBetween
     * @return array{current: int, previous: int, direction: string, percent: ?int}
     */
    private function weekOverWeekCount(\Closure $countBetween): array
    {
        $current = $countBetween(now()->subDays(6)->startOfDay(), now()->endOfDay());
        $previous = $countBetween(now()->subDays(13)->startOfDay(), now()->subDays(7)->endOfDay());

        $percent = $previous > 0 ? (int) round((($current - $previous) / $previous) * 100) : null;
        $direction = match (true) {
            $percent === null => 'flat',
            $percent > 0 => 'up',
            $percent < 0 => 'down',
            default => 'flat',
        };

        return ['current' => $current, 'previous' => $previous, 'direction' => $direction, 'percent' => $percent];
    }

    private function currentMonthSnapshot(User $user, SnapshotType $type): ?MonthlyPerformanceSnapshot
    {
        return $user->performanceSnapshots()
            ->where('month_start', now()->startOfMonth()->toDateString())
            ->where('snapshot_type', $type->value)
            ->first();
    }
}
