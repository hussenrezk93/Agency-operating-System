<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * BRD §6 — departments are Manager-managed; a department cannot exist without a
 * primary Team Leader (DepartmentService::createWithPrimaryLeader, already proven
 * at the service level by OrganizationStructureTest — this proves it over HTTP).
 */
class DepartmentManagementTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private User $manager;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        $this->admin = User::factory()->role(RoleCode::Admin)->create();
    }

    public function test_manager_creates_a_department_with_a_primary_leader(): void
    {
        $leader = User::factory()->role(RoleCode::TeamLeader)->create();

        $response = $this->actingAs($this->manager)->postJson('/departments', [
            'name' => 'Design',
            'primary_leader_id' => $leader->id,
        ])->assertCreated();

        $this->assertDatabaseHas('departments', ['name' => 'Design']);
        $this->assertTrue(
            Department::find($response->json('data.id'))->primaryLeader()->is($leader)
        );
    }

    public function test_a_non_team_leader_cannot_be_the_primary_leader(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->create();

        $this->actingAs($this->manager)->postJson('/departments', [
            'name' => 'Design',
            'primary_leader_id' => $employee->id,
        ])->assertStatus(422)->assertJsonValidationErrors('primary_leader_id');
    }

    public function test_duplicate_department_names_are_rejected_with_a_validation_error(): void
    {
        Department::factory()->create(['name' => 'Marketing']);
        $leader = User::factory()->role(RoleCode::TeamLeader)->create();

        $this->actingAs($this->manager)->postJson('/departments', [
            'name' => 'Marketing',
            'primary_leader_id' => $leader->id,
        ])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_manager_renames_a_department(): void
    {
        $department = Department::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->manager)
            ->patchJson("/departments/{$department->id}", ['name' => 'New Name'])
            ->assertOk();

        $this->assertSame('New Name', $department->fresh()->name);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'department.updated',
            'entity_type' => 'department',
            'entity_id' => $department->id,
            'actor_user_id' => $this->manager->id,
        ]);
    }

    public function test_manager_deactivates_and_reactivates_a_department(): void
    {
        $department = Department::factory()->create();

        $this->actingAs($this->manager)
            ->postJson("/departments/{$department->id}/deactivate")
            ->assertOk();
        $this->assertFalse((bool) $department->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'department.deactivated',
            'entity_type' => 'department',
            'entity_id' => $department->id,
            'actor_user_id' => $this->manager->id,
        ]);

        $this->actingAs($this->manager)
            ->postJson("/departments/{$department->id}/reactivate")
            ->assertOk();
        $this->assertTrue((bool) $department->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'department.reactivated',
            'entity_type' => 'department',
            'entity_id' => $department->id,
            'actor_user_id' => $this->manager->id,
        ]);
    }

    /** BRD §6 — deactivating a department that still has active tasks alerts every Manager. */
    public function test_deactivating_a_department_with_active_tasks_alerts_managers(): void
    {
        $department = $this->makeDepartment('Design');
        $this->newTask($department, $this->manager);
        $otherManager = User::factory()->role(RoleCode::Manager)->create();

        $this->actingAs($this->manager)
            ->postJson("/departments/{$department->id}/deactivate")
            ->assertOk();

        foreach ([$this->manager, $otherManager] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $recipient->id,
                'type' => 'department.deactivated_with_active_tasks',
            ]);
        }
    }

    public function test_deactivating_a_department_with_no_active_tasks_does_not_alert_managers(): void
    {
        $department = Department::factory()->create();

        $this->actingAs($this->manager)
            ->postJson("/departments/{$department->id}/deactivate")
            ->assertOk();

        $this->assertDatabaseMissing('notifications', [
            'type' => 'department.deactivated_with_active_tasks',
        ]);
    }

    /** @return array<string, array{RoleCode}> */
    public static function rolesThatCannotManageDepartments(): array
    {
        return [
            'admin' => [RoleCode::Admin],
            'team leader' => [RoleCode::TeamLeader],
            'employee' => [RoleCode::Employee],
        ];
    }

    #[DataProvider('rolesThatCannotManageDepartments')]
    public function test_non_managers_cannot_create_or_modify_departments(RoleCode $role): void
    {
        $actor = User::factory()->role($role)->create();
        $leader = User::factory()->role(RoleCode::TeamLeader)->create();
        $department = Department::factory()->create();

        $this->actingAs($actor)->postJson('/departments', [
            'name' => 'Blocked', 'primary_leader_id' => $leader->id,
        ])->assertForbidden();

        $this->actingAs($actor)
            ->patchJson("/departments/{$department->id}", ['name' => 'Blocked Rename'])
            ->assertForbidden();

        $this->actingAs($actor)
            ->postJson("/departments/{$department->id}/deactivate")
            ->assertForbidden();
    }

    public function test_admin_may_list_departments_but_not_create_them(): void
    {
        Department::factory()->count(2)->create();

        $this->actingAs($this->admin)->getJson('/departments')->assertOk();
    }

    public function test_a_guest_cannot_reach_department_administration(): void
    {
        $this->get('/departments')->assertRedirect(route('login'));
    }
}
