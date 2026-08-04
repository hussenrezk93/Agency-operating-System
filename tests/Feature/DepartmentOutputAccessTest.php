<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** BRD §15 "قاعدة مشاهدة المخرجات" — cross-department output visibility is Admin-owned. */
class DepartmentOutputAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Department $marketing;

    private Department $design;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->role(RoleCode::Admin)->create();
        $this->marketing = Department::factory()->create();
        $this->design = Department::factory()->create();
    }

    public function test_admin_grants_output_access_between_departments(): void
    {
        $this->actingAs($this->admin)->postJson('/department-output-access', [
            'viewer_department_id' => $this->marketing->id,
            'source_department_id' => $this->design->id,
            'scope' => 'final_only',
            'is_allowed' => true,
        ])->assertOk();

        $this->assertDatabaseHas('department_output_access', [
            'viewer_department_id' => $this->marketing->id,
            'source_department_id' => $this->design->id,
            'scope' => 'final_only',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $this->admin->id,
            'action' => 'department_output_access.updated',
            'entity_type' => 'department_output_access',
        ]);
    }

    public function test_upserting_the_same_pair_updates_rather_than_duplicates(): void
    {
        $payload = [
            'viewer_department_id' => $this->marketing->id,
            'source_department_id' => $this->design->id,
            'scope' => 'all_outputs',
            'is_allowed' => true,
        ];

        $this->actingAs($this->admin)->postJson('/department-output-access', $payload)->assertOk();
        $this->actingAs($this->admin)->postJson(
            '/department-output-access',
            [...$payload, 'scope' => 'final_only']
        )->assertOk();

        $this->assertDatabaseCount('department_output_access', 1);
        $this->assertDatabaseHas('department_output_access', ['scope' => 'final_only']);
    }

    public function test_a_department_cannot_view_its_own_outputs_through_this_rule(): void
    {
        $this->actingAs($this->admin)->postJson('/department-output-access', [
            'viewer_department_id' => $this->marketing->id,
            'source_department_id' => $this->marketing->id,
            'scope' => 'all_outputs',
            'is_allowed' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('viewer_department_id');
    }

    public function test_an_invalid_scope_is_rejected(): void
    {
        $this->actingAs($this->admin)->postJson('/department-output-access', [
            'viewer_department_id' => $this->marketing->id,
            'source_department_id' => $this->design->id,
            'scope' => 'everything',
            'is_allowed' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('scope');
    }

    /** @return array<string, array{RoleCode}> */
    public static function nonAdminRoles(): array
    {
        return [
            'manager' => [RoleCode::Manager],
            'team leader' => [RoleCode::TeamLeader],
            'employee' => [RoleCode::Employee],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_cannot_manage_output_access_rules(RoleCode $role): void
    {
        $actor = User::factory()->role($role)->create();

        $this->actingAs($actor)->getJson('/department-output-access')->assertForbidden();
        $this->actingAs($actor)->postJson('/department-output-access', [
            'viewer_department_id' => $this->marketing->id,
            'source_department_id' => $this->design->id,
            'scope' => 'all_outputs',
            'is_allowed' => true,
        ])->assertForbidden();
    }

    public function test_a_guest_cannot_reach_output_access_administration(): void
    {
        $this->get('/department-output-access')->assertRedirect(route('login'));
    }
}
