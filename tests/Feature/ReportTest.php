<?php

namespace Tests\Feature;

use App\Enums\SnapshotType;
use App\Models\Department;
use App\Models\MonthlyPerformanceSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** PHASE 9 — the combined report page's role-scoped visibility (BRD §17). */
class ReportTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private Department $design;

    private User $manager;

    private User $marketingLeader;

    private User $designLeader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment('Marketing');
        $this->design = $this->makeDepartment('Design');
        $this->manager = $this->makeManager();
        $this->marketingLeader = $this->makeTeamLeader($this->marketing);
        $this->designLeader = $this->makeTeamLeader($this->design);

        $monthStart = now()->startOfMonth()->toDateString();

        MonthlyPerformanceSnapshot::factory()->forDepartment($this->marketing)->create(['month_start' => $monthStart]);
        MonthlyPerformanceSnapshot::factory()->forDepartment($this->design)->create(['month_start' => $monthStart]);
        MonthlyPerformanceSnapshot::factory()->ofType(SnapshotType::TlPersonal)->create(['user_id' => $this->marketingLeader->id, 'month_start' => $monthStart]);
        MonthlyPerformanceSnapshot::factory()->ofType(SnapshotType::TlTeam)->create(['user_id' => $this->marketingLeader->id, 'month_start' => $monthStart]);
        MonthlyPerformanceSnapshot::factory()->ofType(SnapshotType::TlPersonal)->create(['user_id' => $this->designLeader->id, 'month_start' => $monthStart]);
        MonthlyPerformanceSnapshot::factory()->ofType(SnapshotType::TlTeam)->create(['user_id' => $this->designLeader->id, 'month_start' => $monthStart]);
    }

    public function test_a_manager_sees_every_department_and_team_leader(): void
    {
        $response = $this->actingAs($this->manager)->get(route('reports.index'));

        $response->assertOk()->assertViewIs('reports.index');
        $response->assertViewHas('departments', fn ($departments) => $departments->count() === 2);
        $response->assertViewHas('teamLeaders', fn ($tls) => $tls->count() === 2);
    }

    public function test_a_tl_sees_only_their_own_department_and_own_row(): void
    {
        $response = $this->actingAs($this->marketingLeader)->get(route('reports.index'));

        $response->assertOk();
        $response->assertViewHas('departments', function ($departments) {
            return $departments->count() === 1 && $departments->first()->department_id === $this->marketing->id;
        });
        $response->assertViewHas('teamLeaders', function ($tls) {
            return $tls->count() === 1 && $tls->first()['personal']->user_id === $this->marketingLeader->id;
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
        MonthlyPerformanceSnapshot::factory()->forDepartment($this->marketing)->create(['month_start' => $lastMonth->toDateString()]);

        $response = $this->actingAs($this->manager)->get(route('reports.index', ['month' => $lastMonth->format('Y-m')]));

        $response->assertOk();
        $response->assertViewHas('departments', fn ($departments) => $departments->count() === 1);
    }
}
