<?php

namespace Tests\Feature;

use App\Enums\AdjustmentType;
use App\Enums\SnapshotType;
use App\Models\Department;
use App\Models\MonthlyPerformanceSnapshot;
use App\Models\User;
use App\Services\PerformanceAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * PHASE 9 — the report page's role-scoped visibility (BRD §17), rebuilt in 2026-09 as
 * ONE table grouped by department: each group led by that department's Team Leader
 * (their own `tl_personal` delivery) followed by that department's employees.
 */
class ReportTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private Department $design;

    private User $manager;

    private User $marketingLeader;

    private User $marketingEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment('Marketing');
        $this->design = $this->makeDepartment('Design');
        $this->manager = $this->makeManager();
        $this->marketingLeader = $this->makeTeamLeader($this->marketing);
        $this->marketingEmployee = $this->makeEmployee($this->marketing);

        $this->snapshotFor($this->marketingLeader, SnapshotType::TlPersonal, $this->marketing);
        $this->snapshotFor($this->marketingEmployee, SnapshotType::Employee, $this->marketing);
        $this->snapshotFor($this->makeEmployee($this->design), SnapshotType::Employee, $this->design);
    }

    private function snapshotFor(User $user, SnapshotType $type, Department $department, ?string $monthStart = null): MonthlyPerformanceSnapshot
    {
        return MonthlyPerformanceSnapshot::factory()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'snapshot_type' => $type->value,
            'month_start' => $monthStart ?? now()->startOfMonth()->toDateString(),
        ]);
    }

    public function test_a_manager_sees_every_department_as_its_own_group(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.index'));

        $response->assertOk()->assertViewIs('reports.index');
        $response->assertViewHas('groups', fn ($groups) => $groups->count() === 2);
    }

    /** The Team Leader's own row leads their department's group, employees follow. */
    public function test_each_group_puts_the_team_leader_first(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.index'));

        $response->assertViewHas('groups', function ($groups) {
            $marketing = $groups->firstWhere('department', 'Marketing');

            return $marketing['rows']->first()->user_id === $this->marketingLeader->id
                && $marketing['rows']->last()->user_id === $this->marketingEmployee->id;
        });
    }

    public function test_a_tl_sees_only_their_own_department(): void
    {
        $response = $this->actingAs($this->marketingLeader)->get(route('reports.index'));

        $response->assertOk();
        $response->assertViewHas('groups', function ($groups) {
            return $groups->count() === 1 && $groups->first()['department'] === 'Marketing';
        });
    }

    public function test_an_employee_is_redirected_to_their_own_performance_page(): void
    {
        $employee = $this->makeEmployee($this->marketing);

        $this->actingAs($employee)->get(route('reports.index'))->assertRedirect(route('performance.show'));
    }

    public function test_the_month_filter_selects_a_different_months_snapshots(): void
    {
        $lastMonth = now()->subMonth()->startOfMonth();
        $this->snapshotFor($this->marketingEmployee, SnapshotType::Employee, $this->marketing, $lastMonth->toDateString());

        $response = $this->actingAs($this->manager)->get(route('reports.index', ['month' => $lastMonth->format('Y-m')]));

        $response->assertOk();
        $response->assertViewHas('groups', fn ($groups) => $groups->count() === 1
            && $groups->first()['rows']->count() === 1);
    }

    /** tl_team carries the DEPARTMENT's aggregate — showing it beside employees' own
     *  numbers would compare two different things, so this table never loads it. */
    public function test_the_table_never_includes_the_team_aggregate_row(): void
    {
        $this->snapshotFor($this->marketingLeader, SnapshotType::TlTeam, $this->marketing);

        $response = $this->actingAs($this->manager)->get(route('reports.index'));

        $response->assertViewHas('groups', function ($groups) {
            return $groups->flatMap(fn ($group) => $group['rows'])
                ->every(fn ($row) => $row->snapshot_type !== SnapshotType::TlTeam);
        });
    }

    /**
     * Found in production 2026-09: a person promoted mid-month keeps the snapshot
     * written under their OLD role forever (snapshotAll only ever writes the type
     * matching the CURRENT role), so both rows exist — the table must still show them
     * once, as who they are now.
     */
    public function test_a_person_with_a_stale_snapshot_from_a_role_change_appears_once(): void
    {
        // The leader also carries an old `employee` row from before the promotion.
        $this->snapshotFor($this->marketingLeader, SnapshotType::Employee, $this->marketing);

        $response = $this->actingAs($this->manager)->get(route('reports.index'));

        $response->assertViewHas('groups', function ($groups) {
            $rows = $groups->firstWhere('department', 'Marketing')['rows'];
            $leaderRows = $rows->where('user_id', $this->marketingLeader->id);

            return $leaderRows->count() === 1
                && $leaderRows->first()->snapshot_type === SnapshotType::TlPersonal;
        });
    }

    /**
     * Admins are never scored, so they have no snapshot and never showed up on this
     * page — the Manager still needs a row to attach their bonus or deduction to.
     */
    public function test_a_manager_sees_admins_as_their_own_group(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($this->manager)->get(route('reports.index'))
            ->assertViewHas('admins', fn ($admins) => $admins->pluck('id')->contains($admin->id))
            ->assertSee($admin->full_name);
    }

    /** A TL has no business seeing (or paying) the Admins. */
    public function test_a_tl_never_sees_the_admins_group(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($this->marketingLeader)->get(route('reports.index'))
            ->assertViewHas('admins', fn ($admins) => $admins->isEmpty())
            ->assertDontSee($admin->full_name);
    }

    /** The report prints on Agency OS letterhead, same as the daily department report. */
    public function test_the_report_page_offers_printing_with_the_letterhead(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.index'));

        $response->assertSee('onclick="window.print()"', false);
        $response->assertSee('print-letterhead', false);
        $response->assertSee(__('agencyos.reports.print_title'));
    }

    public function test_only_a_manager_can_adjust(): void
    {
        $this->actingAs($this->manager)->get(route('reports.index'))
            ->assertViewHas('canAdjust', true);

        $this->actingAs($this->marketingLeader)->get(route('reports.index'))
            ->assertViewHas('canAdjust', false);
    }

    /** Asked for 2026-09 — every amount says whether it is a bonus or a deduction, so a
     *  figure halfway down the table (or on a one-colour printout) still reads right. */
    public function test_every_amount_is_labelled_as_a_bonus_or_a_deduction(): void
    {
        $adjustments = app(PerformanceAdjustmentService::class);
        $adjustments->add($this->marketingEmployee, $this->manager, AdjustmentType::Bonus, '1500', 'Weekend shoot', now());
        $adjustments->add($this->marketingEmployee, $this->manager, AdjustmentType::Deduction, '250', 'Late delivery', now());

        $response = $this->actingAs($this->manager)->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('rep-adj-kind', false);
        // Once for the column header and once per figure — never only the header.
        $this->assertGreaterThan(1, substr_count($response->getContent(), __('agencyos.reports.bonus')));
        $this->assertGreaterThan(1, substr_count($response->getContent(), __('agencyos.reports.deduction')));
    }
}
