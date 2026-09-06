<?php

namespace Tests\Feature;

use App\Enums\AdjustmentType;
use App\Enums\PayrollStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\PerformanceAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * Product decision 2026-09 — the Manager's payroll and spending book: salaries, the pay
 * window opened per person per month, take-home worked out from the bonuses and
 * deductions already recorded on the reports page, company spending, and the month's
 * bottom line.
 */
class PayrollTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private User $manager;

    private User $leader;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment('Marketing');
        $this->manager = $this->makeManager();
        $this->leader = $this->makeTeamLeader($this->marketing);
        $this->employee = $this->makeEmployee($this->marketing);
    }

    private function payroll(): PayrollService
    {
        return app(PayrollService::class);
    }

    private function month(): Carbon
    {
        return now()->startOfMonth();
    }

    /** Every date here is derived from "this month", never written out: a hard-coded
     *  window quietly stops being the current month the moment the calendar moves on. */
    private function start(): string
    {
        return $this->month()->toDateString();
    }

    private function end(): string
    {
        return $this->month()->endOfMonth()->toDateString();
    }

    private function dayOfMonth(int $day): string
    {
        return $this->month()->addDays($day - 1)->toDateString();
    }

    private function adjust(User $subject, AdjustmentType $type, string $amount): void
    {
        app(PerformanceAdjustmentService::class)->add(
            $subject, $this->manager, $type, $amount, 'Because of the September push', $this->month(),
        );
    }

    // ------------------------------------------------------------------ salary

    public function test_the_manager_sets_a_salary_and_changing_it_replaces_the_one_row(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');
        $this->payroll()->setSalary($this->employee, $this->manager, '9500.00');

        $this->assertSame(1, EmployeeSalary::where('user_id', $this->employee->id)->count());
        $this->assertSame('9500.00', (string) EmployeeSalary::where('user_id', $this->employee->id)->first()->monthly_amount);
    }

    /** The audit log IS the salary history — there is no per-change table, so a raise
     *  that left no trace would leave the old figure unrecoverable. */
    public function test_changing_a_salary_records_the_previous_figure_in_the_audit_log(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');
        $this->payroll()->setSalary($this->employee, $this->manager, '9500.00');

        $entry = AuditLog::where('action', 'salary.set')->latest('id')->first();

        $this->assertSame('8000.00', $entry->metadata['before']['monthly_amount']);
        $this->assertSame('9500.00', $entry->metadata['after']['monthly_amount']);
    }

    // ----------------------------------------------------------------- periods

    public function test_opening_a_month_copies_the_salary_in_force_onto_the_period(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');

        $period = $this->payroll()->openPeriod(
            $this->employee, $this->manager, $this->month(), $this->start(), $this->end(),
        );

        $this->assertSame('8000.00', (string) $period->base_amount);
        $this->assertSame(PayrollStatus::Open, $period->status);
        $this->assertSame($this->start(), $period->period_start->toDateString());
        $this->assertSame($this->end(), $period->period_end->toDateString());
    }

    /** A raise must not reach back into a month that was already opened at the old
     *  figure — the snapshot on the period is the whole point. */
    public function test_a_later_raise_does_not_change_an_already_opened_month(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');
        $period = $this->payroll()->openPeriod($this->employee, $this->manager, $this->month(), $this->start(), $this->end());

        $this->payroll()->setSalary($this->employee, $this->manager, '12000.00');

        $this->assertSame('8000.00', (string) $period->refresh()->base_amount);
        $this->assertSame('8000.00', $this->payroll()->settlement($period)['net']);
    }

    public function test_a_month_cannot_be_opened_twice_for_the_same_person(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');
        $this->payroll()->openPeriod($this->employee, $this->manager, $this->month(), $this->start(), $this->end());

        $this->expectException(ValidationException::class);
        $this->payroll()->openPeriod($this->employee, $this->manager, $this->month(), $this->start(), $this->end());
    }

    public function test_a_month_cannot_be_opened_for_someone_with_no_salary(): void
    {
        $this->expectException(ValidationException::class);
        $this->payroll()->openPeriod($this->employee, $this->manager, $this->month(), $this->start(), $this->end());
    }

    public function test_the_end_date_cannot_come_before_the_start_date(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');

        $this->expectException(ValidationException::class);
        $this->payroll()->openPeriod($this->employee, $this->manager, $this->month(), $this->end(), $this->start());
    }

    // -------------------------------------------------------------- settlement

    /** The sum the whole feature exists for: salary + bonuses − deductions. */
    public function test_take_home_is_the_salary_plus_bonuses_minus_deductions(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');
        $period = $this->payroll()->openPeriod($this->employee, $this->manager, $this->month(), $this->start(), $this->end());

        $this->adjust($this->employee, AdjustmentType::Bonus, '1500.00');
        $this->adjust($this->employee, AdjustmentType::Bonus, '500.00');
        $this->adjust($this->employee, AdjustmentType::Deduction, '250.00');

        $settlement = $this->payroll()->settlement($period->refresh());

        $this->assertSame('2000.00', $settlement['bonus']);
        $this->assertSame('250.00', $settlement['deduction']);
        $this->assertSame('9750.00', $settlement['net']);
    }

    /** Only the month's OWN adjustments count — last month's bonus belongs to last
     *  month's payslip. */
    public function test_another_months_adjustments_are_not_counted(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');
        $period = $this->payroll()->openPeriod($this->employee, $this->manager, $this->month(), $this->start(), $this->end());

        app(PerformanceAdjustmentService::class)->add(
            $this->employee, $this->manager, AdjustmentType::Bonus, '5000.00', 'Last month', $this->month()->subMonth(),
        );

        $this->assertSame('8000.00', $this->payroll()->settlement($period)['net']);
    }

    public function test_closing_a_month_freezes_the_amounts_against_anything_added_later(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');
        $period = $this->payroll()->openPeriod($this->employee, $this->manager, $this->month(), $this->start(), $this->end());
        $this->adjust($this->employee, AdjustmentType::Bonus, '1000.00');

        $closed = $this->payroll()->closePeriod($period->refresh(), $this->manager);

        $this->assertSame(PayrollStatus::Closed, $closed->status);
        $this->assertSame('9000.00', (string) $closed->net_amount);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame($this->manager->id, $closed->closed_by);

        // A bonus entered after payday does not rewrite the payslip.
        $this->adjust($this->employee, AdjustmentType::Bonus, '4000.00');

        $this->assertSame('9000.00', $this->payroll()->settlement($closed->refresh())['net']);
    }

    public function test_a_closed_month_cannot_be_closed_again_or_edited(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');
        $period = $this->payroll()->openPeriod($this->employee, $this->manager, $this->month(), $this->start(), $this->end());
        $closed = $this->payroll()->closePeriod($period, $this->manager);

        try {
            $this->payroll()->updatePeriod($closed, $this->manager, $this->start(), $this->end(), '1.00');
            $this->fail('a closed period must be read-only');
        } catch (ValidationException) {
            // expected
        }

        $this->expectException(ValidationException::class);
        $this->payroll()->closePeriod($closed->refresh(), $this->manager);
    }

    /** Reopening clears the frozen figures, so closing again produces today's numbers
     *  rather than the stale ones. */
    public function test_reopening_clears_the_snapshot_and_recloses_at_current_figures(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');
        $period = $this->payroll()->openPeriod($this->employee, $this->manager, $this->month(), $this->start(), $this->end());
        $closed = $this->payroll()->closePeriod($period, $this->manager);

        $reopened = $this->payroll()->reopenPeriod($closed, $this->manager);

        $this->assertSame(PayrollStatus::Open, $reopened->status);
        $this->assertNull($reopened->net_amount);
        $this->assertNull($reopened->closed_at);

        $this->adjust($this->employee, AdjustmentType::Deduction, '500.00');

        $this->assertSame('7500.00', (string) $this->payroll()->closePeriod($reopened, $this->manager)->net_amount);
    }

    // ---------------------------------------------------------------- expenses

    public function test_expenses_are_recorded_and_totalled_for_their_own_month(): void
    {
        $this->payroll()->addExpense($this->manager, $this->dayOfMonth(5), 'Transport to the shoot', '350.00');
        $this->payroll()->addExpense($this->manager, $this->dayOfMonth(11), 'Camera maintenance', '1200.50');
        $this->payroll()->addExpense($this->manager, $this->month()->subMonth()->startOfMonth()->addDays(10)->toDateString(), 'Last month', '9999.00');

        $totals = $this->payroll()->monthlyTotals($this->month());

        $this->assertSame('1550.50', $totals['expenses']);
        $this->assertSame(2, Expense::forMonth($this->month())->count());
    }

    /** The number the Manager actually asked for: wages plus everything else. */
    public function test_the_month_total_is_payroll_plus_spending(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');
        $this->payroll()->setSalary($this->leader, $this->manager, '12000.00');
        $this->payroll()->openPeriod($this->employee, $this->manager, $this->month(), $this->start(), $this->end());
        $this->payroll()->openPeriod($this->leader, $this->manager, $this->month(), $this->start(), $this->end());
        $this->adjust($this->employee, AdjustmentType::Deduction, '500.00');
        $this->payroll()->addExpense($this->manager, $this->dayOfMonth(5), 'Transport', '350.00');

        $totals = $this->payroll()->monthlyTotals($this->month());

        $this->assertSame('19500.00', $totals['payroll']);
        $this->assertSame('350.00', $totals['expenses']);
        $this->assertSame('19850.00', $totals['total']);
        $this->assertSame(2, $totals['people']);
    }

    /** Someone with no pay window opened contributes nothing — the total is what has
     *  actually been committed to, not a guess at the whole staff. */
    public function test_a_person_with_no_open_window_is_not_in_the_total(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');

        $this->assertSame('0.00', $this->payroll()->monthlyTotals($this->month())['payroll']);
    }

    // -------------------------------------------------------------- the screen

    /** Salaries and spending are two screens (product decision 2026-09), but the month's
     *  bottom line is the same on both — that total is the point of the feature. */
    public function test_the_two_screens_each_show_their_own_half_and_the_same_total(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');
        $this->payroll()->openPeriod($this->employee, $this->manager, $this->month(), $this->start(), $this->end());
        $this->payroll()->addExpense($this->manager, $this->dayOfMonth(5), 'Transport to the shoot', '350.00');

        $month = ['month' => $this->month()->format('Y-m')];

        $salaries = $this->actingAs($this->manager)->get(route('payroll.index', $month));
        $salaries->assertOk();
        $salaries->assertSee($this->employee->full_name, false);
        $salaries->assertDontSee('Transport to the shoot', false);
        $salaries->assertSee('8,350.00', false);   // the grand total

        $spending = $this->actingAs($this->manager)->get(route('payroll.expenses.index', $month));
        $spending->assertOk();
        $spending->assertSee('Transport to the shoot', false);
        $spending->assertDontSee($this->employee->full_name, false);
        $spending->assertSee('8,350.00', false);
    }

    public function test_the_manager_can_drive_the_whole_month_through_the_screen(): void
    {
        $month = $this->month()->format('Y-m');

        $this->actingAs($this->manager)
            ->post(route('payroll.salaries.store'), [
                'user_id' => $this->employee->id, 'monthly_amount' => '8000', 'month' => $month,
            ])->assertRedirect();

        $this->actingAs($this->manager)
            ->post(route('payroll.periods.open'), [
                'user_id' => $this->employee->id, 'month' => $month,
                'period_start' => $this->start(), 'period_end' => $this->end(),
            ])->assertRedirect();

        $period = PayrollPeriod::where('user_id', $this->employee->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('payroll.periods.close', $period))->assertRedirect();

        $this->assertSame(PayrollStatus::Closed, $period->refresh()->status);

        $this->actingAs($this->manager)
            ->post(route('payroll.expenses.store'), [
                'spent_on' => $this->dayOfMonth(7), 'description' => 'Studio rent', 'amount' => '2500',
            ])->assertRedirect();

        $this->assertSame(1, Expense::count());
    }

    /** An expense dated in another month redirects to THAT month, or it would look
     *  like the entry silently vanished. */
    public function test_adding_an_expense_lands_on_the_month_it_belongs_to(): void
    {
        $lastMonth = $this->month()->subMonth();

        $this->actingAs($this->manager)
            ->post(route('payroll.expenses.store'), [
                'spent_on' => $lastMonth->clone()->addDays(13)->toDateString(), 'description' => 'Late invoice', 'amount' => '100',
            ])
            ->assertRedirect(route('payroll.expenses.index', ['month' => $lastMonth->format('Y-m')]));
    }

    /** The bonus/deduction form on this page posts to the reports controller but must
     *  come back HERE — the Manager is working the payroll screen, not the report. */
    public function test_a_bonus_entered_from_the_payroll_page_returns_to_the_payroll_page(): void
    {
        $month = $this->month()->format('Y-m');

        $this->actingAs($this->manager)
            ->post(route('reports.adjustments.store'), [
                'user_id' => $this->employee->id, 'month' => $month, 'type' => 'bonus',
                'amount' => '750', 'reason' => 'Weekend shoot', 'return' => 'payroll',
            ])
            ->assertRedirect(route('payroll.index', ['month' => $month]));

        // Without the flag it still lands on the reports page, as it always did.
        $this->actingAs($this->manager)
            ->post(route('reports.adjustments.store'), [
                'user_id' => $this->employee->id, 'month' => $month, 'type' => 'bonus',
                'amount' => '250', 'reason' => 'Extra edit round',
            ])
            ->assertRedirect(route('reports.index', ['month' => $month]));
    }

    // ------------------------------------------------------------ who may look

    public function test_the_spending_screen_is_manager_only_too(): void
    {
        foreach ([$this->leader, $this->employee, $this->makeAdmin()] as $actor) {
            $this->actingAs($actor)->get(route('payroll.expenses.index'))->assertForbidden();
        }
    }

    /** Money is the Manager's alone — not a Team Leader's, not an Admin's, and not the
     *  employee whose salary it is. */
    public function test_nobody_but_the_manager_can_open_the_payroll_page(): void
    {
        foreach ([$this->leader, $this->employee, $this->makeAdmin()] as $actor) {
            $this->actingAs($actor)->get(route('payroll.index'))->assertForbidden();
        }
    }

    public function test_nobody_but_the_manager_can_set_a_salary_or_record_spending(): void
    {
        $this->actingAs($this->leader)
            ->post(route('payroll.salaries.store'), ['user_id' => $this->employee->id, 'monthly_amount' => '1'])
            ->assertForbidden();

        $this->actingAs($this->employee)
            ->post(route('payroll.expenses.store'), [
                'spent_on' => now()->toDateString(), 'description' => 'Anything', 'amount' => '1',
            ])
            ->assertForbidden();
    }

    public function test_a_team_leader_cannot_close_a_pay_period(): void
    {
        $this->payroll()->setSalary($this->employee, $this->manager, '8000.00');
        $period = $this->payroll()->openPeriod($this->employee, $this->manager, $this->month(), $this->start(), $this->end());

        $this->actingAs($this->leader)->post(route('payroll.periods.close', $period))->assertForbidden();

        $this->assertSame(PayrollStatus::Open, $period->refresh()->status);
    }
}
