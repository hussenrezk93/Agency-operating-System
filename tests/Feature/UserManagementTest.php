<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Enums\WorkflowStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * BRD §5/§18, widened by explicit product decision: Admin creates and manages
 * Manager, Team Leader, and Employee accounts (originally Manager-only); Manager still
 * creates TL/Employee accounts only. Nobody self-registers, nobody manages Admin
 * accounts through the app. Every account starts with a generated temporary password
 * and must_change_password = true (BRD §18).
 */
class UserManagementTest extends TestCase
{
    use BuildsWorkflowScenarios;
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

    public function test_admin_cannot_create_another_admin_account(): void
    {
        $this->actingAs($this->admin)->postJson('/users', [
            'full_name' => 'Someone',
            'username' => 'someone.admin',
            'personal_email' => 'admin@dev.local',
            'role' => RoleCode::Admin->value,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'someone.admin']);
    }

    public function test_admin_creates_a_team_leader_in_a_department(): void
    {
        $this->actingAs($this->admin)->postJson('/users', [
            'full_name' => 'Admin-made TL',
            'username' => 'admin.made.tl',
            'personal_email' => 'admin.made.tl@dev.local',
            'role' => RoleCode::TeamLeader->value,
            'department_id' => $this->department->id,
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['username' => 'admin.made.tl', 'department_id' => $this->department->id]);
    }

    public function test_admin_creates_an_employee_in_a_department(): void
    {
        $this->actingAs($this->admin)->postJson('/users', [
            'full_name' => 'Admin-made Employee',
            'username' => 'admin.made.employee',
            'personal_email' => 'admin.made.employee@dev.local',
            'role' => RoleCode::Employee->value,
            'department_id' => $this->department->id,
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['username' => 'admin.made.employee']);
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

    public function test_admin_disables_and_reactivates_a_team_leader(): void
    {
        $leader = User::factory()->role(RoleCode::TeamLeader)->inDepartment($this->department)->create();

        $this->actingAs($this->admin)->postJson("/users/{$leader->id}/disable")->assertOk();
        $this->assertSame('inactive', $leader->fresh()->status->value);

        $this->actingAs($this->admin)->postJson("/users/{$leader->id}/reactivate")->assertOk();
        $this->assertSame('active', $leader->fresh()->status->value);
    }

    public function test_admin_cannot_disable_another_admin(): void
    {
        $otherAdmin = User::factory()->role(RoleCode::Admin)->create();

        $this->actingAs($this->admin)->postJson("/users/{$otherAdmin->id}/disable")->assertForbidden();
    }

    public function test_manager_cannot_disable_another_manager(): void
    {
        $otherManager = User::factory()->role(RoleCode::Manager)->create();

        $this->actingAs($this->manager)->postJson("/users/{$otherManager->id}/disable")->assertForbidden();
    }

    /** BRD §6 — a disabled employee's in-progress step is released back to the department. */
    public function test_disabling_an_employee_releases_their_in_progress_step_to_waiting_assignment(): void
    {
        $leader = $this->makeTeamLeader($this->department);
        $employee = $this->makeEmployee($this->department);
        [, $step] = $this->taskInProgress($this->department, $leader, $employee);

        $this->actingAs($this->manager)->postJson("/users/{$employee->id}/disable")->assertOk();

        $step->refresh();
        $this->assertSame(WorkflowStatus::WaitingAssignment, $step->workflow_status);
        $this->assertNull($step->activeAssignment);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'task_step.released_for_disabled_user',
            'entity_type' => 'task_step',
            'entity_id' => $step->id,
        ]);
    }

    /** A step already under review is the reviewer's responsibility, so disabling leaves it alone. */
    public function test_disabling_an_employee_does_not_release_a_step_under_review(): void
    {
        $leader = $this->makeTeamLeader($this->department);
        $employee = $this->makeEmployee($this->department);
        [, $step] = $this->taskUnderReview($this->department, $leader, $employee);

        $this->actingAs($this->manager)->postJson("/users/{$employee->id}/disable")->assertOk();

        $this->assertSame(WorkflowStatus::UnderReview, $step->fresh()->workflow_status);
    }

    public function test_admin_resets_a_managers_password_and_forces_change(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/users/{$this->manager->id}/reset-password")
            ->assertOk();

        $this->assertNotEmpty($response->json('temporary_password'));
        $this->assertTrue((bool) $this->manager->fresh()->must_change_password);
    }

    public function test_admin_resets_an_employees_password(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->department)->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/users/{$employee->id}/reset-password")
            ->assertOk();

        $this->assertNotEmpty($response->json('temporary_password'));
        $this->assertTrue((bool) $employee->fresh()->must_change_password);
    }

    public function test_admin_cannot_reset_another_admins_password(): void
    {
        $otherAdmin = User::factory()->role(RoleCode::Admin)->create();

        $this->actingAs($this->admin)->postJson("/users/{$otherAdmin->id}/reset-password")->assertForbidden();
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
