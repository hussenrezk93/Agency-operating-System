<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BRD §5/§18 — Admin creates Manager accounts only; Manager creates Employee/TL
 * accounts only; nobody self-registers. Every account starts with a generated
 * temporary password and must_change_password = true (BRD §18).
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->role(RoleCode::Admin)->create();
        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        $this->department = Department::factory()->create();
    }

    public function test_admin_creates_a_manager_account_with_a_temporary_password(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/users', [
            'full_name' => 'New Manager',
            'username' => 'new.manager',
            'personal_email' => 'new.manager@dev.local',
            'role' => RoleCode::Manager->value,
        ])->assertCreated();

        $response->assertJsonPath('data.username', 'new.manager');
        $this->assertNotEmpty($response->json('temporary_password'));

        $this->assertDatabaseHas('users', [
            'username' => 'new.manager',
            'must_change_password' => true,
        ]);
    }

    /** @return array<string, array{RoleCode}> */
    public static function rolesAdminCannotCreate(): array
    {
        return [
            'team leader' => [RoleCode::TeamLeader],
            'employee' => [RoleCode::Employee],
            'admin' => [RoleCode::Admin],
        ];
    }

    #[DataProvider('rolesAdminCannotCreate')]
    public function test_admin_cannot_create_non_manager_accounts(RoleCode $role): void
    {
        $this->actingAs($this->admin)->postJson('/users', [
            'full_name' => 'Someone',
            'username' => 'someone.'.$role->value,
            'personal_email' => $role->value.'@dev.local',
            'role' => $role->value,
            'department_id' => in_array($role, [RoleCode::TeamLeader, RoleCode::Employee], true)
                ? $this->department->id : null,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'someone.'.$role->value]);
    }

    public function test_manager_creates_a_team_leader_in_a_department(): void
    {
        $this->actingAs($this->manager)->postJson('/users', [
            'full_name' => 'New TL',
            'username' => 'new.tl',
            'personal_email' => 'new.tl@dev.local',
            'role' => RoleCode::TeamLeader->value,
            'department_id' => $this->department->id,
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['username' => 'new.tl', 'department_id' => $this->department->id]);
    }

    public function test_manager_creates_an_employee_in_a_department(): void
    {
        $this->actingAs($this->manager)->postJson('/users', [
            'full_name' => 'New Employee',
            'username' => 'new.employee',
            'personal_email' => 'new.employee@dev.local',
            'role' => RoleCode::Employee->value,
            'department_id' => $this->department->id,
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['username' => 'new.employee']);
    }

    public function test_a_team_leader_or_employee_requires_a_department(): void
    {
        $this->actingAs($this->manager)->postJson('/users', [
            'full_name' => 'No Department',
            'username' => 'no.department',
            'personal_email' => 'no.department@dev.local',
            'role' => RoleCode::Employee->value,
        ])->assertStatus(422)->assertJsonValidationErrors('department_id');
    }

    public function test_a_manager_account_may_not_carry_a_department(): void
    {
        $this->actingAs($this->admin)->postJson('/users', [
            'full_name' => 'Bad Manager',
            'username' => 'bad.manager',
            'personal_email' => 'bad.manager@dev.local',
            'role' => RoleCode::Manager->value,
            'department_id' => $this->department->id,
        ])->assertStatus(422)->assertJsonValidationErrors('department_id');
    }

    /** @return array<string, array{RoleCode}> */
    public static function rolesManagerCannotCreate(): array
    {
        return [
            'manager' => [RoleCode::Manager],
            'admin' => [RoleCode::Admin],
        ];
    }

    #[DataProvider('rolesManagerCannotCreate')]
    public function test_manager_cannot_create_manager_or_admin_accounts(RoleCode $role): void
    {
        $this->actingAs($this->manager)->postJson('/users', [
            'full_name' => 'Someone',
            'username' => 'blocked.'.$role->value,
            'personal_email' => 'blocked.'.$role->value.'@dev.local',
            'role' => $role->value,
        ])->assertForbidden();
    }

    public function test_manager_disables_and_reactivates_an_employee(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->department)->create();

        $this->actingAs($this->manager)->postJson("/users/{$employee->id}/disable")->assertOk();
        $this->assertSame('inactive', $employee->fresh()->status->value);

        $this->actingAs($this->manager)->postJson("/users/{$employee->id}/reactivate")->assertOk();
        $this->assertSame('active', $employee->fresh()->status->value);
    }

    public function test_manager_cannot_disable_another_manager(): void
    {
        $otherManager = User::factory()->role(RoleCode::Manager)->create();

        $this->actingAs($this->manager)->postJson("/users/{$otherManager->id}/disable")->assertForbidden();
    }

    public function test_admin_resets_a_managers_password_and_forces_change(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/users/{$this->manager->id}/reset-password")
            ->assertOk();

        $this->assertNotEmpty($response->json('temporary_password'));
        $this->assertTrue((bool) $this->manager->fresh()->must_change_password);
    }

    public function test_admin_cannot_reset_an_employees_password(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->department)->create();

        $this->actingAs($this->admin)->postJson("/users/{$employee->id}/reset-password")->assertForbidden();
    }

    /** @return array<string, array{RoleCode}> */
    public static function rolesWithNoUserAdministration(): array
    {
        return [
            'team leader' => [RoleCode::TeamLeader],
            'employee' => [RoleCode::Employee],
        ];
    }

    #[DataProvider('rolesWithNoUserAdministration')]
    public function test_team_leaders_and_employees_cannot_reach_user_administration(RoleCode $role): void
    {
        $actor = User::factory()->role($role)->inDepartment($this->department)->create();

        $this->actingAs($actor)->getJson('/users')->assertForbidden();
        $this->actingAs($actor)->postJson('/users', [
            'full_name' => 'X', 'username' => 'x', 'personal_email' => 'x@dev.local',
            'role' => RoleCode::Employee->value, 'department_id' => $this->department->id,
        ])->assertForbidden();
    }

    public function test_a_guest_cannot_reach_any_user_administration_endpoint(): void
    {
        $this->get('/users')->assertRedirect(route('login'));
        $this->post('/users', [])->assertRedirect(route('login'));
    }

    public function test_the_audit_log_never_stores_a_plaintext_password(): void
    {
        $this->actingAs($this->admin)->postJson('/users', [
            'full_name' => 'Audited Manager',
            'username' => 'audited.manager',
            'personal_email' => 'audited.manager@dev.local',
            'role' => RoleCode::Manager->value,
        ])->assertCreated();

        $entry = AuditLog::where('action', 'user.created')->latest('id')->firstOrFail();

        $serialized = json_encode($entry->metadata);
        $this->assertStringNotContainsString('password', strtolower($serialized));
    }
}
