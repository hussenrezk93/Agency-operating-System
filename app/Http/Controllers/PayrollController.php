<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\OpenPayrollPeriodRequest;
use App\Http\Requests\StoreEmployeeSalaryRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdatePayrollPeriodRequest;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\PayrollPeriod;
use App\Models\PerformanceAdjustment;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Product decision 2026-09 — the Manager's payroll and spending book, one month at a
 * time: every person's salary, the pay window opened for them, the bonuses and
 * deductions already recorded on the reports page, what they take home, everything the
 * company spent that month, and the two totals added together.
 *
 * Manager-only, twice over: `role:manager` on the routes and PayrollPolicy on every
 * action. Nobody else — Team Leader, Admin, or the employee themself — sees any of it.
 */
class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        $monthStart = $this->resolveMonth($request);

        // Everyone currently on staff, whatever their role — a Team Leader draws a
        // salary exactly like an employee, and an Admin the Manager pays belongs here
        // too. Inactive accounts drop out: there is nothing to pay a closed account,
        // and a month already paid keeps its own frozen row regardless.
        $people = User::query()
            ->where('status', UserStatus::Active->value)
            ->with(['department:id,name', 'role:id,code'])
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'department_id', 'role_id']);

        $salaries = EmployeeSalary::whereIn('user_id', $people->pluck('id'))->get()->keyBy('user_id');
        $periods = PayrollPeriod::forMonth($monthStart)->get()->keyBy('user_id');

        $adjustments = PerformanceAdjustment::query()
            ->forMonth($monthStart)
            ->whereIn('user_id', $people->pluck('id'))
            ->get()
            ->groupBy('user_id');

        // One settlement per person, worked out here so the Blade only prints numbers.
        $settlements = $periods->map(
            fn (PayrollPeriod $period) => $this->payroll->settlement(
                $period,
                $adjustments[$period->user_id] ?? collect(),
            ),
        );

        return view('payroll.index', [
            'monthStart' => $monthStart,
            'monthKey' => $monthStart->format('Y-m'),
            'availableMonths' => $this->availableMonths($monthStart),
            'groups' => $this->groupByDepartment($people),
            'salaries' => $salaries,
            'periods' => $periods,
            'settlements' => $settlements,
            'adjustments' => $adjustments,
            'totals' => $this->payroll->monthlyTotals($monthStart),
        ]);
    }

    /**
     * Spending gets its own screen (product decision 2026-09) — it is a different job
     * from paying people, and mixing both into one page made each of them harder to
     * work through. The month picker and the month's totals are shared, so whichever
     * screen the Manager is on, the bottom line is the same one.
     */
    public function expenses(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $monthStart = $this->resolveMonth($request);

        return view('payroll.expenses', [
            'monthStart' => $monthStart,
            'monthKey' => $monthStart->format('Y-m'),
            'availableMonths' => $this->availableMonths($monthStart),
            'expenses' => Expense::forMonth($monthStart)
                ->with('createdBy:id,full_name')
                ->orderByDesc('spent_on')->orderByDesc('id')->get(),
            'totals' => $this->payroll->monthlyTotals($monthStart),
        ]);
    }

    public function storeSalary(StoreEmployeeSalaryRequest $request): RedirectResponse
    {
        $this->payroll->setSalary(
            User::findOrFail($request->integer('user_id')),
            $request->user(),
            $request->string('monthly_amount')->toString(),
            $request->filled('note') ? $request->string('note')->toString() : null,
        );

        return $this->backToMonth($request, __('agencyos.payroll.flash.salary_saved'));
    }

    public function openPeriod(OpenPayrollPeriodRequest $request): RedirectResponse
    {
        $this->payroll->openPeriod(
            User::findOrFail($request->integer('user_id')),
            $request->user(),
            Carbon::createFromFormat('Y-m-d', $request->string('month').'-01')->startOfMonth(),
            $request->string('period_start')->toString(),
            $request->string('period_end')->toString(),
            $request->filled('note') ? $request->string('note')->toString() : null,
        );

        return $this->backToMonth($request, __('agencyos.payroll.flash.period_opened'));
    }

    public function updatePeriod(UpdatePayrollPeriodRequest $request, PayrollPeriod $period): RedirectResponse
    {
        $this->payroll->updatePeriod(
            $period,
            $request->user(),
            $request->string('period_start')->toString(),
            $request->string('period_end')->toString(),
            $request->string('base_amount')->toString(),
            $request->filled('note') ? $request->string('note')->toString() : null,
        );

        return $this->backToPeriodMonth($period, __('agencyos.payroll.flash.period_updated'));
    }

    public function closePeriod(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $this->payroll->closePeriod($period, $request->user());

        return $this->backToPeriodMonth($period, __('agencyos.payroll.flash.period_closed'));
    }

    public function reopenPeriod(Request $request, PayrollPeriod $period): RedirectResponse
    {
        $this->payroll->reopenPeriod($period, $request->user());

        return $this->backToPeriodMonth($period, __('agencyos.payroll.flash.period_reopened'));
    }

    public function storeExpense(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = $this->payroll->addExpense(
            $request->user(),
            $request->string('spent_on')->toString(),
            $request->string('description')->toString(),
            $request->string('amount')->toString(),
        );

        // The month the expense LANDS IN, not the one the form was submitted from — an
        // expense dated last month would otherwise vanish the moment it was saved.
        return redirect()
            ->route('payroll.expenses.index', ['month' => $expense->spent_on->format('Y-m')])
            ->with('status', __('agencyos.payroll.flash.expense_added'));
    }

    public function destroyExpense(Request $request, Expense $expense): RedirectResponse
    {
        $month = $expense->spent_on->format('Y-m');

        $this->payroll->removeExpense($expense, $request->user());

        return redirect()
            ->route('payroll.expenses.index', ['month' => $month])
            ->with('status', __('agencyos.payroll.flash.expense_removed'));
    }

    /**
     * People grouped by department, departments in name order, anyone without one last
     * — the same shape the reports table uses, so the two pages read alike.
     *
     * @param  Collection<int, User>  $people
     * @return Collection<int, array{department: ?string, rows: Collection<int, User>}>
     */
    private function groupByDepartment(Collection $people): Collection
    {
        return $people
            ->groupBy(fn (User $user) => $user->department_id ?? 0)
            ->map(fn (Collection $rows) => [
                'department' => $rows->first()->department?->name,
                'rows' => $rows->values(),
            ])
            ->sortBy(fn (array $group) => $group['department'] ?? "\u{FFFF}")
            ->values();
    }

    /**
     * Months worth offering in the picker: every month that already has payroll or
     * spending in it, plus the one being viewed, newest first.
     *
     * @return Collection<string, string>
     */
    private function availableMonths(Carbon $monthStart): Collection
    {
        return PayrollPeriod::query()->distinct()->pluck('month_start')
            ->map(fn ($m) => Carbon::parse($m)->format('Y-m'))
            ->merge(Expense::query()->distinct()->pluck('spent_on')->map(fn ($d) => Carbon::parse($d)->format('Y-m')))
            ->push($monthStart->format('Y-m'))
            ->unique()
            ->sortDesc()
            ->values()
            ->mapWithKeys(fn (string $key) => [
                $key => Carbon::createFromFormat('Y-m-d', $key.'-01')->translatedFormat('F Y'),
            ]);
    }

    private function backToMonth(Request $request, string $status): RedirectResponse
    {
        $month = $request->string('month')->toString();

        return redirect()
            ->route('payroll.index', preg_match('/^\d{4}-\d{2}$/', $month) ? ['month' => $month] : [])
            ->with('status', $status);
    }

    private function backToPeriodMonth(PayrollPeriod $period, string $status): RedirectResponse
    {
        return redirect()
            ->route('payroll.index', ['month' => $period->month_start->format('Y-m')])
            ->with('status', $status);
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
