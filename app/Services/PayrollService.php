<?php

namespace App\Services;

use App\Enums\AdjustmentType;
use App\Enums\PayrollStatus;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\PayrollPeriod;
use App\Models\PerformanceAdjustment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Product decision 2026-09 — the only writer of employee_salaries, payroll_periods and
 * expenses, and the single place a person's take-home for a month is worked out.
 *
 * The one rule the whole class is built around: WHILE A PERIOD IS OPEN its totals are
 * read live from performance_adjustments, and the moment it CLOSES they are frozen onto
 * the row. A bonus entered later, or a raise next month, must never quietly rewrite a
 * month that has already been paid — so a closed period never recomputes anything.
 *
 * Bonuses and deductions are not re-entered here: they are the same
 * performance_adjustments the Manager already sets on the reports page, matched by the
 * period's month_start. One number, one place it is decided.
 */
class PayrollService
{
    public function __construct(private readonly AuditService $audit) {}

    // ------------------------------------------------------------------ salary

    /** Set (or change) one person's standing monthly salary. */
    public function setSalary(User $subject, User $actor, string $amount, ?string $note = null): EmployeeSalary
    {
        Gate::forUser($actor)->authorize('create', EmployeeSalary::class);

        $existing = EmployeeSalary::where('user_id', $subject->id)->first();

        $salary = EmployeeSalary::updateOrCreate(
            ['user_id' => $subject->id],
            [
                'monthly_amount' => $amount,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'updated_by' => $actor->id,
                'updated_at' => now(),
            ],
        );

        // The audit log IS the salary history — there is no per-change table, so this
        // entry is the only record of what the previous figure was.
        $this->audit->log(
            action: 'salary.set',
            entityType: 'employee_salary',
            entityId: $salary->id,
            before: $existing !== null ? ['monthly_amount' => (string) $existing->monthly_amount] : [],
            after: ['user_id' => $subject->id, 'monthly_amount' => $amount],
            actorId: $actor->id,
        );

        return $salary;
    }

    // ------------------------------------------------------------------ periods

    /**
     * Open a person's month. The Manager picks both dates by hand (product decision
     * 2026-09 — cycles differ per person), and the salary in force right now is copied
     * onto the row so a later raise cannot reach back into this month.
     */
    public function openPeriod(
        User $subject,
        User $actor,
        Carbon $monthStart,
        string $periodStart,
        string $periodEnd,
        ?string $note = null,
    ): PayrollPeriod {
        Gate::forUser($actor)->authorize('create', PayrollPeriod::class);

        $month = $monthStart->clone()->startOfMonth();
        $salary = EmployeeSalary::where('user_id', $subject->id)->first();

        if ($salary === null) {
            throw ValidationException::withMessages([
                'user_id' => __('agencyos.payroll.errors.no_salary'),
            ]);
        }

        if (Carbon::parse($periodEnd)->lt(Carbon::parse($periodStart))) {
            throw ValidationException::withMessages([
                'period_end' => __('agencyos.payroll.errors.end_before_start'),
            ]);
        }

        if (PayrollPeriod::where('user_id', $subject->id)->forMonth($month)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => __('agencyos.payroll.errors.already_open'),
            ]);
        }

        $period = PayrollPeriod::create([
            'user_id' => $subject->id,
            'month_start' => $month->toDateString(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'base_amount' => (string) $salary->monthly_amount,
            'status' => PayrollStatus::Open->value,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'opened_by' => $actor->id,
            'opened_at' => now(),
        ]);

        $this->audit->log(
            action: 'payroll_period.opened',
            entityType: 'payroll_period',
            entityId: $period->id,
            after: [
                'user_id' => $subject->id,
                'month_start' => $month->toDateString(),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'base_amount' => (string) $salary->monthly_amount,
            ],
            actorId: $actor->id,
        );

        return $period;
    }

    /** Correct the dates (or the base amount) of a period that has not been paid yet. */
    public function updatePeriod(
        PayrollPeriod $period,
        User $actor,
        string $periodStart,
        string $periodEnd,
        string $baseAmount,
        ?string $note = null,
    ): PayrollPeriod {
        Gate::forUser($actor)->authorize('update', $period);

        if ($period->isClosed()) {
            throw ValidationException::withMessages([
                'period_start' => __('agencyos.payroll.errors.closed_is_read_only'),
            ]);
        }

        if (Carbon::parse($periodEnd)->lt(Carbon::parse($periodStart))) {
            throw ValidationException::withMessages([
                'period_end' => __('agencyos.payroll.errors.end_before_start'),
            ]);
        }

        $before = [
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
            'base_amount' => (string) $period->base_amount,
        ];

        $period->forceFill([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'base_amount' => $baseAmount,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ])->save();

        $this->audit->log(
            action: 'payroll_period.updated',
            entityType: 'payroll_period',
            entityId: $period->id,
            before: $before,
            after: ['period_start' => $periodStart, 'period_end' => $periodEnd, 'base_amount' => $baseAmount],
            actorId: $actor->id,
        );

        return $period->refresh();
    }

    /**
     * Close the month for one person: work the totals out one last time and freeze them.
     * From here the row answers with its own numbers and stops listening to anything
     * that happens to the salary or to that month's adjustments.
     */
    public function closePeriod(PayrollPeriod $period, User $actor): PayrollPeriod
    {
        Gate::forUser($actor)->authorize('close', $period);

        return DB::transaction(function () use ($period, $actor): PayrollPeriod {
            $period = PayrollPeriod::lockForUpdate()->findOrFail($period->id);

            if ($period->isClosed()) {
                throw ValidationException::withMessages([
                    'status' => __('agencyos.payroll.errors.already_closed'),
                ]);
            }

            $settlement = $this->settlement($period);

            $period->forceFill([
                'status' => PayrollStatus::Closed->value,
                'bonus_total' => $settlement['bonus'],
                'deduction_total' => $settlement['deduction'],
                'net_amount' => $settlement['net'],
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ])->save();

            $this->audit->log(
                action: 'payroll_period.closed',
                entityType: 'payroll_period',
                entityId: $period->id,
                after: [
                    'user_id' => $period->user_id,
                    'month_start' => $period->month_start->toDateString(),
                    'base_amount' => (string) $period->base_amount,
                    'bonus_total' => $settlement['bonus'],
                    'deduction_total' => $settlement['deduction'],
                    'net_amount' => $settlement['net'],
                ],
                actorId: $actor->id,
            );

            return $period->refresh();
        });
    }

    /**
     * Undo a close — a paid month that turns out to be wrong. The frozen totals are
     * cleared, so the period goes back to reading live figures and closing it again
     * produces today's numbers rather than the stale ones.
     */
    public function reopenPeriod(PayrollPeriod $period, User $actor): PayrollPeriod
    {
        Gate::forUser($actor)->authorize('close', $period);

        if (! $period->isClosed()) {
            return $period;
        }

        $before = ['net_amount' => (string) $period->net_amount];

        $period->forceFill([
            'status' => PayrollStatus::Open->value,
            'bonus_total' => null,
            'deduction_total' => null,
            'net_amount' => null,
            'closed_by' => null,
            'closed_at' => null,
        ])->save();

        $this->audit->log(
            action: 'payroll_period.reopened',
            entityType: 'payroll_period',
            entityId: $period->id,
            before: $before,
            after: ['user_id' => $period->user_id, 'month_start' => $period->month_start->toDateString()],
            actorId: $actor->id,
        );

        return $period->refresh();
    }

    // -------------------------------------------------------------- settlement

    /**
     * What this period is worth: base salary, plus that month's bonuses, minus its
     * deductions. A CLOSED period answers with the numbers frozen at close; an open one
     * is worked out live.
     *
     * $adjustments lets a page that already loaded the whole month's adjustments pass
     * this person's rows in, so a table of thirty people stays one query, not thirty.
     *
     * @param  ?Collection<int, PerformanceAdjustment>  $adjustments
     * @return array{base: string, bonus: string, deduction: string, net: string}
     */
    public function settlement(PayrollPeriod $period, ?Collection $adjustments = null): array
    {
        if ($period->isClosed()) {
            return [
                'base' => (string) $period->base_amount,
                'bonus' => (string) $period->bonus_total,
                'deduction' => (string) $period->deduction_total,
                'net' => (string) $period->net_amount,
            ];
        }

        $rows = $adjustments ?? PerformanceAdjustment::query()
            ->where('user_id', $period->user_id)
            ->forMonth($period->month_start)
            ->get();

        $bonus = $this->sumOf($rows, AdjustmentType::Bonus);
        $deduction = $this->sumOf($rows, AdjustmentType::Deduction);

        return [
            'base' => (string) $period->base_amount,
            'bonus' => $bonus,
            'deduction' => $deduction,
            'net' => number_format(
                (float) $period->base_amount + (float) $bonus - (float) $deduction,
                2, '.', '',
            ),
        ];
    }

    /**
     * The month's bottom line: what the company owes in wages, what it spent on
     * everything else, and the two added together — the number the Manager actually
     * asked for.
     *
     * @return array{payroll: string, expenses: string, total: string, people: int, closed: int}
     */
    public function monthlyTotals(Carbon $monthStart): array
    {
        $month = $monthStart->clone()->startOfMonth();

        $periods = PayrollPeriod::forMonth($month)->get();
        $adjustments = PerformanceAdjustment::query()
            ->forMonth($month)
            ->whereIn('user_id', $periods->pluck('user_id'))
            ->get()
            ->groupBy('user_id');

        $payroll = $periods->reduce(
            fn (float $carry, PayrollPeriod $period) => $carry + (float) $this->settlement(
                $period,
                $adjustments[$period->user_id] ?? collect(),
            )['net'],
            0.0,
        );

        $expenses = (float) Expense::forMonth($month)->sum('amount');

        return [
            'payroll' => number_format($payroll, 2, '.', ''),
            'expenses' => number_format($expenses, 2, '.', ''),
            'total' => number_format($payroll + $expenses, 2, '.', ''),
            'people' => $periods->count(),
            'closed' => $periods->where('status', PayrollStatus::Closed)->count(),
        ];
    }

    // ----------------------------------------------------------------- expenses

    public function addExpense(User $actor, string $spentOn, string $description, string $amount): Expense
    {
        Gate::forUser($actor)->authorize('create', Expense::class);

        $expense = Expense::create([
            'spent_on' => $spentOn,
            'description' => trim($description),
            'amount' => $amount,
            'created_by' => $actor->id,
            'created_at' => now(),
        ]);

        $this->audit->log(
            action: 'expense.added',
            entityType: 'expense',
            entityId: $expense->id,
            after: ['spent_on' => $spentOn, 'description' => trim($description), 'amount' => $amount],
            actorId: $actor->id,
        );

        return $expense;
    }

    /** Correcting an expense means deleting it and entering it again — same convention
     *  as a bonus or deduction, so an amount can never drift from its description. */
    public function removeExpense(Expense $expense, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $expense);

        $before = [
            'spent_on' => $expense->spent_on->toDateString(),
            'description' => $expense->description,
            'amount' => (string) $expense->amount,
        ];

        $expense->delete();

        $this->audit->log(
            action: 'expense.removed',
            entityType: 'expense',
            entityId: $expense->id,
            before: $before,
            actorId: $actor->id,
        );
    }

    /** @param  Collection<int, PerformanceAdjustment>  $rows */
    private function sumOf(Collection $rows, AdjustmentType $type): string
    {
        return number_format(
            (float) $rows->where('type', $type)->sum(fn (PerformanceAdjustment $a) => (float) $a->amount),
            2, '.', '',
        );
    }
}
