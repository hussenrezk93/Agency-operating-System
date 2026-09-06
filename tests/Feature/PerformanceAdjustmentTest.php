<?php

namespace Tests\Feature;

use App\Enums\AdjustmentType;
use App\Enums\SnapshotType;
use App\Models\Department;
use App\Models\MonthlyPerformanceSnapshot;
use App\Models\PerformanceAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * Product decision 2026-09 — the Manager attaches a bonus or deduction (money, always
 * with a written reason) to one person for one month, shown on the reports page. The
 * performance score itself is never touched: it stays a pure deadline measurement.
 */
class PerformanceAdjustmentTest extends TestCase
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

    private function month(): string
    {
        return now()->startOfMonth()->format('Y-m');
    }

    public function test_a_manager_adds_a_bonus_with_a_reason(): void
    {
        $this->actingAs($this->manager)->post(route('reports.adjustments.store'), [
            'user_id' => $this->employee->id,
            'month' => $this->month(),
            'type' => 'bonus',
            'amount' => '500',
            'reason' => 'Delivered the campaign a day early.',
        ])->assertRedirect(route('reports.index', ['month' => $this->month()]));

        $adjustment = PerformanceAdjustment::sole();
        $this->assertSame($this->employee->id, $adjustment->user_id);
        $this->assertSame(AdjustmentType::Bonus, $adjustment->type);
        $this->assertSame('500.00', (string) $adjustment->amount);
        $this->assertSame($this->manager->id, $adjustment->created_by);
        $this->assertSame(now()->startOfMonth()->toDateString(), $adjustment->month_start->toDateString());
    }

    public function test_a_manager_adds_a_deduction(): void
    {
        $this->actingAs($this->manager)->post(route('reports.adjustments.store'), [
            'user_id' => $this->employee->id,
            'month' => $this->month(),
            'type' => 'deduction',
            'amount' => '200.50',
            'reason' => 'Missed two deadlines without notice.',
        ])->assertRedirect();

        $this->assertSame(AdjustmentType::Deduction, PerformanceAdjustment::sole()->type);
    }

    /** A Team Leader reads the reports page but never sets money on it. */
    public function test_a_team_leader_cannot_add_an_adjustment(): void
    {
        $this->actingAs($this->leader)->post(route('reports.adjustments.store'), [
            'user_id' => $this->employee->id,
            'month' => $this->month(),
            'type' => 'bonus',
            'amount' => '500',
            'reason' => 'Trying anyway.',
        ])->assertForbidden();

        $this->assertSame(0, PerformanceAdjustment::count());
    }

    public function test_an_employee_cannot_add_an_adjustment(): void
    {
        $this->actingAs($this->employee)->post(route('reports.adjustments.store'), [
            'user_id' => $this->employee->id,
            'month' => $this->month(),
            'type' => 'bonus',
            'amount' => '500',
            'reason' => 'Paying myself.',
        ])->assertForbidden();
    }

    public function test_the_reason_is_required(): void
    {
        $this->actingAs($this->manager)->post(route('reports.adjustments.store'), [
            'user_id' => $this->employee->id,
            'month' => $this->month(),
            'type' => 'bonus',
            'amount' => '500',
        ])->assertSessionHasErrors('reason');

        $this->assertSame(0, PerformanceAdjustment::count());
    }

    public function test_a_zero_or_negative_amount_is_rejected(): void
    {
        foreach (['0', '-50'] as $amount) {
            $this->actingAs($this->manager)->post(route('reports.adjustments.store'), [
                'user_id' => $this->employee->id,
                'month' => $this->month(),
                'type' => 'bonus',
                'amount' => $amount,
                'reason' => 'Should not save.',
            ])->assertSessionHasErrors('amount');
        }

        $this->assertSame(0, PerformanceAdjustment::count());
    }

    public function test_a_manager_removes_an_adjustment(): void
    {
        $adjustment = PerformanceAdjustment::create([
            'user_id' => $this->employee->id,
            'month_start' => now()->startOfMonth()->toDateString(),
            'type' => AdjustmentType::Bonus->value,
            'amount' => '500',
            'reason' => 'Entered by mistake.',
            'created_by' => $this->manager->id,
            'created_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->delete(route('reports.adjustments.destroy', $adjustment))
            ->assertRedirect(route('reports.index', ['month' => $this->month()]));

        $this->assertSame(0, PerformanceAdjustment::count());
    }

    /** Several entries in one month stack rather than overwrite — each keeps its reason. */
    public function test_several_adjustments_in_one_month_all_stick(): void
    {
        foreach ([['bonus', '300'], ['bonus', '200'], ['deduction', '100']] as [$type, $amount]) {
            $this->actingAs($this->manager)->post(route('reports.adjustments.store'), [
                'user_id' => $this->employee->id,
                'month' => $this->month(),
                'type' => $type,
                'amount' => $amount,
                'reason' => "Reason for {$type} {$amount}.",
            ])->assertRedirect();
        }

        $this->assertSame(3, PerformanceAdjustment::count());
        $this->assertSame('500.00', (string) PerformanceAdjustment::where('type', 'bonus')->sum('amount'));
    }

    public function test_the_report_page_shows_the_adjustment_against_its_person(): void
    {
        MonthlyPerformanceSnapshot::factory()->create([
            'user_id' => $this->employee->id,
            'department_id' => $this->marketing->id,
            'snapshot_type' => SnapshotType::Employee->value,
            'month_start' => now()->startOfMonth()->toDateString(),
        ]);

        $this->actingAs($this->manager)->post(route('reports.adjustments.store'), [
            'user_id' => $this->employee->id,
            'month' => $this->month(),
            'type' => 'bonus',
            'amount' => '500',
            'reason' => 'Delivered the campaign early.',
        ]);

        $this->actingAs($this->manager)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Delivered the campaign early.')
            ->assertSee('500.00');
    }

    /** Admins carry no performance snapshot, but they can still be paid a bonus. */
    public function test_a_manager_can_adjust_an_admin(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($this->manager)->post(route('reports.adjustments.store'), [
            'user_id' => $admin->id,
            'month' => $this->month(),
            'type' => 'bonus',
            'amount' => '750',
            'reason' => 'Kept the system running through the migration.',
        ])->assertRedirect();

        $this->assertSame($admin->id, PerformanceAdjustment::sole()->user_id);

        $this->actingAs($this->manager)->get(route('reports.index'))
            ->assertSee('Kept the system running through the migration.');
    }

    public function test_adding_and_removing_are_audit_logged(): void
    {
        $this->actingAs($this->manager)->post(route('reports.adjustments.store'), [
            'user_id' => $this->employee->id,
            'month' => $this->month(),
            'type' => 'bonus',
            'amount' => '500',
            'reason' => 'Great month.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $this->manager->id,
            'action' => 'performance_adjustment.added',
            'entity_type' => 'performance_adjustment',
        ]);

        $this->actingAs($this->manager)->delete(route('reports.adjustments.destroy', PerformanceAdjustment::sole()));

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $this->manager->id,
            'action' => 'performance_adjustment.removed',
        ]);
    }
}
