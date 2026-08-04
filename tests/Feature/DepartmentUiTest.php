<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 3-closing real screens for department administration. */
class DepartmentUiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->manager = User::factory()->role(RoleCode::Manager)->create();
    }

    public function test_the_department_list_renders_with_the_primary_leader(): void
    {
        $department = Department::factory()->create();

        $response = $this->actingAs($this->manager)->get('/departments');

        $response->assertOk()->assertViewIs('departments.index');
        $response->assertSee($department->name);
    }

    public function test_the_create_form_only_offers_team_leaders_without_a_department_already(): void
    {
        $freeLeader = User::factory()->role(RoleCode::TeamLeader)->create();
        $busyDepartment = Department::factory()->create();
        $busyLeader = User::factory()->role(RoleCode::TeamLeader)->inDepartment($busyDepartment)->create();
        DepartmentLeadershipAssignment::create([
            'department_id' => $busyDepartment->id,
            'user_id' => $busyLeader->id,
            'assignment_type' => 'primary',
            'start_date' => now()->subMonth()->toDateString(),
            'is_active' => true,
            'activation_state' => 'active',
            'assigned_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->manager)->get('/departments/create');

        $response->assertOk()->assertViewIs('departments.create');
        $response->assertSee($freeLeader->full_name);
        $response->assertDontSee($busyLeader->full_name);
    }

    public function test_a_classic_form_post_creates_a_department_and_redirects_to_the_list(): void
    {
        $leader = User::factory()->role(RoleCode::TeamLeader)->create();

        $response = $this->actingAs($this->manager)->post('/departments', [
            'name' => 'Classic Form Department',
            'primary_leader_id' => $leader->id,
        ]);

        $response->assertRedirect(route('departments.index'));
        $this->assertDatabaseHas('departments', ['name' => 'Classic Form Department']);
    }

    public function test_the_edit_form_renders_and_a_classic_rename_redirects(): void
    {
        $department = Department::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->manager)->get(route('departments.edit-form', $department))
            ->assertOk()->assertViewIs('departments.edit');

        $response = $this->actingAs($this->manager)->patch(route('departments.update', $department), [
            'name' => 'New Name',
        ]);

        $response->assertRedirect(route('departments.index'));
        $this->assertSame('New Name', $department->fresh()->name);
    }

    public function test_classic_deactivate_and_reactivate_redirect_to_the_list(): void
    {
        $department = Department::factory()->create();

        $this->actingAs($this->manager)->post(route('departments.deactivate', $department))
            ->assertRedirect(route('departments.index'));
        $this->assertFalse((bool) $department->fresh()->is_active);

        $this->actingAs($this->manager)->post(route('departments.reactivate', $department))
            ->assertRedirect(route('departments.index'));
        $this->assertTrue((bool) $department->fresh()->is_active);
    }

    public function test_an_admin_cannot_open_the_create_form(): void
    {
        $admin = User::factory()->role(RoleCode::Admin)->create();

        $this->actingAs($admin)->get('/departments/create')->assertForbidden();
    }
}
