<?php

namespace Tests\Feature;

use App\Enums\ActivationState;
use App\Enums\LeadershipType;
use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Temporary-TL administration is MANAGER-ONLY (approved decisions Q2, Q8, Q16, Q18).
 * Endpoints are called directly here — hiding a button proves nothing.
 */
class TemporaryLeadershipAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-08-01 09:00:00', config('app.timezone')));

        $this->department = Department::factory()->create();
        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        $this->employee = User::factory()->role(RoleCode::Employee)
            ->inDepartment($this->department)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return [
            'department_id' => $this->department->id,
            'user_id' => $this->employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-10',
            'reason' => 'Primary Team Leader on annual leave',
        ];
    }

    private function existingAssignment(): DepartmentLeadershipAssignment
    {
        return DepartmentLeadershipAssignment::create([
            'department_id' => $this->department->id,
            'user_id' => $this->employee->id,
            'assignment_type' => LeadershipType::Temporary->value,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-10',
            'is_active' => true,
            'activation_state' => ActivationState::Active->value,
            'assigned_by' => $this->manager->id,
        ]);
    }

    public function test_a_manager_can_appoint_a_temporary_leader(): void
    {
        $this->actingAs($this->manager)
            ->postJson('/temporary-leadership', $this->payload())
            ->assertCreated();

        $this->assertDatabaseHas('department_leadership_assignments', [
            'department_id' => $this->department->id,
            'user_id' => $this->employee->id,
            'assignment_type' => 'temporary',
        ]);
    }

    public function test_a_manager_can_replace_a_temporary_leader(): void
    {
        $assignment = $this->existingAssignment();
        $replacement = User::factory()->role(RoleCode::Employee)
            ->inDepartment($this->department)->create();

        $this->actingAs($this->manager)
            ->postJson("/temporary-leadership/{$assignment->id}/replace", [
                'user_id' => $replacement->id,
                'end_date' => '2026-08-10',
                'reason' => 'Replacement during the leave period',
            ])->assertOk();

        $this->assertTrue($this->department->fresh()->effectiveLeader()->is($replacement));
    }

    public function test_a_manager_can_end_an_assignment_early(): void
    {
        $assignment = $this->existingAssignment();

        $this->actingAs($this->manager)
            ->postJson("/temporary-leadership/{$assignment->id}/end")
            ->assertOk();

        $this->assertFalse((bool) $assignment->fresh()->is_active);
    }

    /** @return array<string, array{RoleCode}> */
    public static function unauthorizedRoles(): array
    {
        return [
            'admin is configuration-only' => [RoleCode::Admin],
            'team leader may not appoint their own cover' => [RoleCode::TeamLeader],
            'employee may not administer leadership' => [RoleCode::Employee],
        ];
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_non_managers_cannot_appoint_a_temporary_leader(RoleCode $role): void
    {
        $actor = User::factory()->role($role)->inDepartment($this->department)->create();

        $this->actingAs($actor)
            ->postJson('/temporary-leadership', $this->payload())
            ->assertForbidden();

        $this->assertDatabaseMissing('department_leadership_assignments', [
            'assignment_type' => 'temporary',
        ]);
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_non_managers_cannot_replace_or_end_an_assignment(RoleCode $role): void
    {
        $assignment = $this->existingAssignment();
        $actor = User::factory()->role($role)->inDepartment($this->department)->create();

        $this->actingAs($actor)
            ->postJson("/temporary-leadership/{$assignment->id}/replace", [
                'user_id' => $actor->id, 'end_date' => '2026-08-09', 'reason' => 'attempted takeover',
            ])->assertForbidden();

        $this->actingAs($actor)
            ->postJson("/temporary-leadership/{$assignment->id}/end")
            ->assertForbidden();

        $this->assertTrue((bool) $assignment->fresh()->is_active);
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_non_managers_cannot_list_assignments(RoleCode $role): void
    {
        $actor = User::factory()->role($role)->create();

        $this->actingAs($actor)->getJson('/temporary-leadership')->assertForbidden();
    }

    public function test_a_guest_cannot_reach_any_temporary_leadership_endpoint(): void
    {
        $assignment = $this->existingAssignment();

        $this->post('/temporary-leadership', $this->payload())->assertRedirect(route('login'));
        $this->post("/temporary-leadership/{$assignment->id}/end")->assertRedirect(route('login'));
        $this->get('/temporary-leadership')->assertRedirect(route('login'));
    }

    public function test_the_policy_itself_denies_non_managers(): void
    {
        $assignment = $this->existingAssignment();

        foreach ([RoleCode::Admin, RoleCode::TeamLeader, RoleCode::Employee] as $role) {
            $actor = User::factory()->role($role)->create();
            $this->assertFalse($actor->can('appoint', DepartmentLeadershipAssignment::class));
            $this->assertFalse($actor->can('replace', $assignment));
            $this->assertFalse($actor->can('endEarly', $assignment));
            $this->assertFalse($actor->can('manageMembership', DepartmentLeadershipAssignment::class));
        }

        $this->assertTrue($this->manager->can('appoint', DepartmentLeadershipAssignment::class));
        $this->assertTrue($this->manager->can('replace', $assignment));
    }

    public function test_appointment_validation_requires_a_reason(): void
    {
        $payload = $this->payload();
        unset($payload['reason']);

        $this->actingAs($this->manager)
            ->postJson('/temporary-leadership', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }
}
