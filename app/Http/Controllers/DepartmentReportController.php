<?php

namespace App\Http\Controllers;

use App\Enums\DepartmentReportType;
use App\Enums\DepartmentSpecialRole;
use App\Enums\RoleCode;
use App\Models\DepartmentDailyReport;
use App\Models\DepartmentReportContribution;
use App\Services\DepartmentReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DepartmentReportController extends Controller
{
    public function __construct(private readonly DepartmentReportService $reports) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        $month = $this->resolveMonth($request);

        $counts = DepartmentDailyReport::query()
            ->visibleTo($actor)
            ->whereBetween('report_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->selectRaw('report_date, count(*) as total, sum(case when submitted_at is not null then 1 else 0 end) as submitted')
            ->groupBy('report_date')
            ->get()
            ->keyBy(fn ($row) => $row->report_date->toDateString());

        return view('department-reports.index', [
            'month' => $month,
            'counts' => $counts,
            'today' => now()->toDateString(),
        ]);
    }

    public function show(Request $request, string $date): View
    {
        $actor = $request->user();

        $reports = DepartmentDailyReport::query()
            ->visibleTo($actor)
            ->where('report_date', $date)
            ->with(['department', 'submittedBy'])
            ->get();

        // A TL who owns an unsubmitted report for this date still needs to see it to
        // submit it, even though visibleTo() alone already covers "their own department"
        // regardless of submission state — this is just making that intent explicit for
        // the view, not an extra query.
        $ownUnsubmitted = $actor->hasRole(RoleCode::TeamLeader)
            ? $reports->filter(fn (DepartmentDailyReport $r) => ! $r->isSubmitted() && $actor->can('submit', $r))
            : collect();

        // Nothing to show yet isn't the same as nothing ever coming: for today, before
        // the daily generation run, an empty $reports means "not generated yet," not
        // "no reports exist" — the view renders that as a locked placeholder instead of
        // a plain empty state.
        $pendingGeneration = $reports->isEmpty()
            && $date === now()->toDateString()
            && now()->lt(Carbon::parse($date)->setTimeFromTimeString(DepartmentReportService::GENERATION_TIME));

        // The Moderator's TL now reads other departments' approved reports plus the
        // Content handoff (product decision 2026-09), so they get the same Print
        // button the Manager has — everyone else stays scoped to just their own.
        $canPrint = $actor->hasRole(RoleCode::Manager)
            || $actor->department?->special_role === DepartmentSpecialRole::Moderator;

        $collective = $reports->filter(
            fn (DepartmentDailyReport $r) => ! $r->isSubmitted() && $this->reports->collectsMemberReports($r),
        );

        return view('department-reports.show', [
            'date' => $date,
            'reports' => $reports,
            'ownUnsubmitted' => $ownUnsubmitted,
            'canPrint' => $canPrint,
            'pendingGeneration' => $pendingGeneration,
            'generationTime' => DepartmentReportService::GENERATION_TIME,
            'isTeamLeader' => $actor->hasRole(RoleCode::TeamLeader),
            // Product decision 2026-09 — a collectively-written report (Sales) shows
            // its members' parts and who is still missing instead of one free textarea.
            'contributions' => $collective->mapWithKeys(fn (DepartmentDailyReport $r) => [
                $r->id => $this->reports->contributionStatus($r),
            ]),
            // Exactly what submitting would store, shown to the Team Leader before they
            // do it — the same composer, so the preview cannot drift from the result.
            'composedPreview' => $collective->mapWithKeys(fn (DepartmentDailyReport $r) => [
                $r->id => $this->reports->composeDetails($r),
            ]),
        ]);
    }

    /**
     * The member-facing screen: today's report for MY department, my own part of it,
     * and who else has written. Deliberately a page of its own rather than a corner of
     * the Team Leader's screen — an Employee has no business on that one.
     */
    public function contribute(Request $request): View
    {
        $actor = $request->user();

        $report = DepartmentDailyReport::query()
            ->where('department_id', $actor->department_id)
            ->where('report_date', now()->toDateString())
            ->where('type', DepartmentReportType::Summary->value)
            ->with('department')
            ->first();

        // Two different "nothing to do here" answers, and they must not be conflated:
        // a department that does not write collectively never has anything on this
        // screen, whereas one that does simply has no report yet before the daily
        // generation run. Deciding on the DEPARTMENT first keeps the message correct
        // at any hour -- reading it off the report made the answer depend on the clock.
        $isCollectiveDepartment = $this->reports->departmentCollectsMemberReports($actor->department);
        $collects = $isCollectiveDepartment
            && $report !== null
            && $this->reports->collectsMemberReports($report);

        return view('department-reports.contribute', [
            'report' => $collects ? $report : null,
            'status' => $collects ? $this->reports->contributionStatus($report) : null,
            'mine' => $collects
                ? DepartmentReportContribution::where('report_id', $report->id)
                    ->where('user_id', $actor->id)
                    ->first()
                : null,
            'pendingGeneration' => $report === null
                && now()->lt(now()->setTimeFromTimeString(DepartmentReportService::GENERATION_TIME)),
            'generationTime' => DepartmentReportService::GENERATION_TIME,
            'collects' => $collects,
            'isCollectiveDepartment' => $isCollectiveDepartment,
        ]);
    }

    public function storeContribution(Request $request, DepartmentDailyReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $this->reports->contribute($report, $request->user(), $validated['body']);

        // A Team Leader writes their own part from their department's report page, so
        // they go back to it; everyone else has only the member screen to return to.
        return redirect()
            ->route(
                $request->user()->hasRole(RoleCode::TeamLeader)
                    ? 'department-reports.show'
                    : 'department-reports.contribute',
                $request->user()->hasRole(RoleCode::TeamLeader)
                    ? [$report->report_date->toDateString()]
                    : [],
            )
            ->with('status', __('agencyos.department_reports.flash.contribution_saved'));
    }

    public function submit(Request $request, DepartmentDailyReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'details' => ['required', 'string', 'max:5000'],
            'task_comments' => ['nullable', 'array'],
            'task_comments.*' => ['nullable', 'string', 'max:1000'],
            'external_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->reports->submit(
            $report,
            $request->user(),
            $validated['details'],
            $validated['task_comments'] ?? [],
            $validated['external_note'] ?? null,
        );

        return redirect()
            ->route('department-reports.show', $report->report_date->toDateString())
            ->with('status', __('agencyos.department_reports.flash.submitted'));
    }

    public function update(Request $request, DepartmentDailyReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'details' => ['required', 'string', 'max:5000'],
            'task_comments' => ['nullable', 'array'],
            'task_comments.*' => ['nullable', 'string', 'max:1000'],
            'external_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->reports->update(
            $report,
            $request->user(),
            $validated['details'],
            $validated['task_comments'] ?? [],
            $validated['external_note'] ?? null,
        );

        return redirect()
            ->route('department-reports.show', $report->report_date->toDateString())
            ->with('status', __('agencyos.department_reports.flash.updated'));
    }

    public function approve(Request $request, DepartmentDailyReport $report): RedirectResponse
    {
        $this->reports->approve($report, $request->user());

        return redirect()
            ->route('department-reports.show', $report->report_date->toDateString())
            ->with('status', __('agencyos.department_reports.flash.approved'));
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
