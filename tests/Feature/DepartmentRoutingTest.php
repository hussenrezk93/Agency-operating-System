<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BRD §15 — the department_routes matrix is Admin-owned configuration read by
 * TaskRoutingService. This proves the ADMIN SURFACE only; TaskRoutingTest already
 * proves the routing DECISION at "Send to Next Department".
 */
class DepartmentRoutingTest extends TestCase
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

    public function test_admin_creates_a_routing_rule(): void
    {
        $this->actingAs($this->admin)->postJson('/department-routes', [
            'from_department_id' => $this->marketing->id,
            'to_department_id' => $this->design->id,
            'is_allowed' => true,
        ])->assertOk();

        $this->assertDatabaseHas('department_routes', [
            'from_department_id' => $this->marketing->id,
            'to_department_id' => $this->design->id,
            'is_allowed' => true,
        ]);
    }

    public function test_upserting_the_same_pair_updates_rather_than_duplicates(): void
    {
        $this->actingAs($this->admin)->postJson('/department-routes', [
            'from_department_id' => $this->marketing->id,
            'to_department_id' => $this->design->id,
            'is_allowed' => true,
        ])->assertOk();

        $this->actingAs($this->admin)->postJson('/department-routes', [
            'from_department_id' => $this->marketing->id,
            'to_department_id' => $this->design->id,
            'is_allowed' => false,
        ])->assertOk();

        $this->assertDatabaseCount('department_routes', 1);
        $this->assertDatabaseHas('department_routes', ['is_allowed' => false]);
    }

    public function test_a_department_cannot_route_to_itself(): void
    {
        $this->actingAs($this->admin)->postJson('/department-routes', [
            'from_department_id' => $this->marketing->id,
            'to_department_id' => $this->marketing->id,
            'is_allowed' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('from_department_id');
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
    public function test_non_admins_cannot_manage_routing_rules(RoleCode $role): void
    {
        $actor = User::factory()->role($role)->create();

        $this->actingAs($actor)->getJson('/department-routes')->assertForbidden();
        $this->actingAs($actor)->postJson('/department-routes', [
            'from_department_id' => $this->marketing->id,
            'to_department_id' => $this->design->id,
            'is_allowed' => true,
        ])->assertForbidden();
    }

    public function test_a_guest_cannot_reach_routing_administration(): void
    {
        $this->get('/department-routes')->assertRedirect(route('login'));
    }
}
