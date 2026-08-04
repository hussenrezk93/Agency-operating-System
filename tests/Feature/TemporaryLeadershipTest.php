<?php

namespace Tests\Feature;

use App\Enums\ActivationState;
use App\Enums\LeadershipType;
use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\Role;
use App\Models\User;
use App\Services\TemporaryLeadershipService;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Approved decisions Q2, Q3, Q6, Q7, Q8, Q9, Q13, Q14, Q16, Q17.
 * All time-dependent behavior uses Carbon time travel — never the real clock.
 */
class TemporaryLeadershipTest extends TestCase
{
    use RefreshDatabase;

    private TemporaryLeadershipService $service;

    private Department $department;

    private User $manager;

    private User $primary;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-08-01 09:00:00', config('app.timezone')));

        $this->service = app(TemporaryLeadershipService::class);
        $this->department = Department::factory()->create();
        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        $this->primary = User::factory()->role(RoleCode::TeamLeader)
            ->inDepartment($this->department)->create();
        $this->employee = User::factory()->role(RoleCode::Employee)
            ->inDepartment($this->department)->create();

        $this->department->leadershipAssignments()->create([
            'user_id' => $this->primary->id,
            'assignment_type' => LeadershipType::Primary->value,
            'start_date' => '2026-01-01',
            'is_active' => true,
            'activation_state' => ActivationState::Active->value,
            'assigned_by' => $this->manager->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function appointNow(?User $who = null, string $end = '2026-08-10'): DepartmentLeadershipAssignment
    {
        return $this->service->appoint(
            $this->department, $who ?? $this->employee,
            Carbon::now(), Carbon::parse($end), 'Primary TL on leave', $this->manager,
        );
    }

    /** 1 */
    public function test_primary_tl_is_not_view_only_before_the_temporary_period_starts(): void
    {
        $this->assertFalse($this->primary->fresh()->isViewOnlyLeader());
        $this->assertTrue($this->department->fresh()->effectiveLeader()->is($this->primary));
    }

    /** 2 + 13 (future start) */
    public function test_employee_remains_employee_before_a_future_assignment_starts(): void
    {
        $this->service->appoint($this->department, $this->employee,
            Carbon::parse('2026-08-20'), Carbon::parse('2026-08-30'),
            'Planned leave', $this->manager);

        $this->assertSame(RoleCode::Employee, $this->employee->fresh()->roleCode());
        $this->assertTrue($this->department->fresh()->effectiveLeader()->is($this->primary));
        $this->assertFalse($this->primary->fresh()->isViewOnlyLeader());
    }

    /** 3 */
    public function test_employee_becomes_team_leader_when_the_period_starts(): void
    {
        $this->service->appoint($this->department, $this->employee,
            Carbon::parse('2026-08-20'), Carbon::parse('2026-08-30'),
            'Planned leave', $this->manager);

        Carbon::setTestNow(Carbon::parse('2026-08-20 00:10:00', config('app.timezone')));
        $this->service->processScheduledTransitions();

        $this->assertSame(RoleCode::TeamLeader, $this->employee->fresh()->roleCode());
        $this->assertSame(RoleCode::Employee, $this->employee->fresh()->substantiveRoleCode());
    }

    /** 4 */
    public function test_the_temporary_leader_becomes_the_effective_leader(): void
    {
        $this->appointNow();

        $department = $this->department->fresh();

        $this->assertTrue($department->effectiveLeader()->is($this->employee));
        $this->assertTrue($department->temporaryLeader()->is($this->employee));
        $this->assertTrue($department->primaryLeader()->is($this->primary)); // primary preserved
    }

    /** 5 — the regression test for the fixed defect */
    public function test_primary_tl_becomes_view_only_while_a_temporary_leader_is_active(): void
    {
        $this->assertFalse($this->primary->fresh()->isViewOnlyLeader());

        $this->appointNow();

        $this->assertTrue($this->primary->fresh()->isViewOnlyLeader());
    }

    /** 6 */
    public function test_a_view_only_primary_tl_cannot_act_as_leader(): void
    {
        $this->appointNow();

        $primary = $this->primary->fresh();

        $this->assertFalse($primary->leadsDepartment($this->department->id));
        $this->assertFalse($primary->canActAsLeaderOf($this->department->id));
        $this->assertTrue($primary->hasLeadershipAssignment($this->department->id)); // still holds it
    }

    /** 7 */
    public function test_a_view_only_primary_tl_may_still_sign_in_and_view(): void
    {
        $this->appointNow();

        $this->post('/login', ['username' => $this->primary->username, 'password' => 'Demo123!'])
            ->assertRedirect(route('dashboard'));

        $this->get('/dashboard')->assertOk();
    }

    /** 8 */
    public function test_the_temporary_leader_may_act_as_leader(): void
    {
        $this->appointNow();

        $temp = $this->employee->fresh();

        $this->assertTrue($temp->leadsDepartment($this->department->id));
        $this->assertTrue($temp->canActAsLeaderOf($this->department->id));
        $this->assertFalse($temp->isViewOnlyLeader());
    }

    /** 9 + 10 */
    public function test_the_temporary_leader_must_come_from_the_same_department(): void
    {
        $outsider = User::factory()->role(RoleCode::Employee)
            ->inDepartment(Department::factory()->create())->create();

        $this->expectException(ValidationException::class);
        $this->appointNow($outsider);
    }

    /** 11 */
    public function test_overlapping_temporary_assignments_are_rejected(): void
    {
        $this->appointNow();
        $other = User::factory()->role(RoleCode::Employee)->inDepartment($this->department)->create();

        $this->expectException(QueryException::class); // EXCLUDE constraint
        DepartmentLeadershipAssignment::create([
            'department_id' => $this->department->id,
            'user_id' => $other->id,
            'assignment_type' => LeadershipType::Temporary->value,
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-15',
            'is_active' => true,
            'activation_state' => ActivationState::Active->value,
            'assigned_by' => $this->manager->id,
        ]);
    }

    /** 12 */
    public function test_one_user_cannot_cover_two_departments_at_once(): void
    {
        $this->appointNow();

        $other = Department::factory()->create();

        $this->expectException(ValidationException::class);
        $this->service->appoint($other, $this->employee, Carbon::now(),
            Carbon::parse('2026-08-12'), 'Primary TL on leave', $this->manager);
    }

    /** 13 + 15 */
    public function test_normal_expiry_restores_the_employee_and_clears_view_only(): void
    {
        $this->appointNow(end: '2026-08-05');

        Carbon::setTestNow(Carbon::parse('2026-08-06 00:10:00', config('app.timezone')));
        $result = $this->service->processScheduledTransitions();

        $this->assertSame(1, $result['ended']);
        $this->assertSame(RoleCode::Employee, $this->employee->fresh()->roleCode());
        $this->assertNull($this->employee->fresh()->base_role_id);
        $this->assertFalse($this->primary->fresh()->isViewOnlyLeader());
        $this->assertTrue($this->department->fresh()->effectiveLeader()->is($this->primary));
    }

    /** 14 + 16 */
    public function test_early_termination_restores_the_employee_and_clears_view_only(): void
    {
        $assignment = $this->appointNow();

        $this->service->endEarly($assignment, $this->manager);

        $this->assertSame(RoleCode::Employee, $this->employee->fresh()->roleCode());
        $this->assertFalse($this->primary->fresh()->isViewOnlyLeader());
        $this->assertTrue($this->department->fresh()->effectiveLeader()->is($this->primary));
    }

    /** 17 + 18 */
    public function test_a_replacement_becomes_effective_leader_and_the_previous_returns_to_employee(): void
    {
        $second = User::factory()->role(RoleCode::Employee)->inDepartment($this->department)->create();

        $first = $this->appointNow();
        $new = $this->service->replace($first, $second, Carbon::parse('2026-08-10'),
            'Replacement during leave', $this->manager);

        $this->assertSame(RoleCode::Employee, $this->employee->fresh()->roleCode());
        $this->assertSame(RoleCode::TeamLeader, $second->fresh()->roleCode());
        $this->assertTrue($this->department->fresh()->effectiveLeader()->is($second));
        $this->assertSame($new->id, $first->fresh()->replaced_by_assignment_id);
        $this->assertTrue($this->primary->fresh()->isViewOnlyLeader()); // still covered

        $active = DepartmentLeadershipAssignment::where('department_id', $this->department->id)
            ->where('assignment_type', LeadershipType::Temporary->value)
            ->where('is_active', true)->count();
        $this->assertSame(1, $active);
    }

    /** 19 — the elevated user keeps their own department membership and identity (Q4/Q5/Q17) */
    public function test_the_elevated_user_keeps_their_department_and_substantive_identity(): void
    {
        $this->appointNow();

        $temp = $this->employee->fresh();

        $this->assertSame($this->department->id, $temp->department_id);
        $this->assertSame(RoleCode::Employee, $temp->substantiveRoleCode());
        $this->assertTrue($temp->isTemporarilyElevated());
        // Personal task retention (Q4) is asserted with the task tables in Phase 1B.
    }

    /** 20 */
    public function test_role_and_leadership_history_remain_auditable(): void
    {
        $employeeRoleId = Role::where('code', RoleCode::Employee->value)->firstOrFail()->id;
        $assignment = $this->appointNow();
        $this->service->endEarly($assignment, $this->manager);

        $this->assertDatabaseHas('user_role_transitions', [
            'user_id' => $this->employee->id,
            'transition_type' => 'elevation',
            'reason' => 'temporary_tl_start',
            'leadership_assignment_id' => $assignment->id,
        ]);
        $this->assertDatabaseHas('user_role_transitions', [
            'user_id' => $this->employee->id,
            'transition_type' => 'restoration',
            'to_role_id' => $employeeRoleId,
        ]);
        foreach (['temporary_tl.appointed', 'temporary_tl.ended_early',
            'user.role_elevated', 'user.role_restored'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }
    }

    public function test_the_scheduler_is_idempotent(): void
    {
        $this->appointNow(end: '2026-08-03');

        Carbon::setTestNow(Carbon::parse('2026-08-04 00:10:00', config('app.timezone')));
        $first = $this->service->processScheduledTransitions();
        $second = $this->service->processScheduledTransitions();

        $this->assertSame(1, $first['ended']);
        $this->assertSame(0, $second['ended']);
    }

    public function test_a_team_leader_cannot_be_appointed_as_temporary_leader(): void
    {
        $tl = User::factory()->role(RoleCode::TeamLeader)->inDepartment($this->department)->create();

        $this->expectException(ValidationException::class);
        $this->appointNow($tl);
    }
}
