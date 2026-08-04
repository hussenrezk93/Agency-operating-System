<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 3-closing real screens for user administration. */
class UserUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private Department $marketing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->role(RoleCode::Admin)->create();
        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        $this->marketing = Department::factory()->create();
    }

    public function test_the_user_list_is_scoped_by_the_viewing_actor(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();

        $asAdmin = $this->actingAs($this->admin)->get('/users');
        $asAdmin->assertOk()->assertViewIs('users.index');
        $asAdmin->assertSee($this->manager->full_name);
        $asAdmin->assertDontSee($employee->full_name);

        $asManager = $this->actingAs($this->manager)->get('/users');
        $asManager->assertSee($employee->full_name);
        $asManager->assertDontSee($this->admin->full_name);
    }

    public function test_the_create_form_adapts_to_the_actor_and_a_classic_submit_shows_the_temporary_password(): void
    {
        $this->actingAs($this->admin)->get('/users/create')->assertOk()->assertViewIs('users.create')
            ->assertViewHas('isAdmin', true);
        $this->actingAs($this->manager)->get('/users/create')->assertOk()->assertViewHas('isAdmin', false);

        $response = $this->actingAs($this->admin)->post('/users', [
            'full_name' => 'Classic Manager',
            'username' => 'classic.manager',
            'personal_email' => 'classic.manager@dev.local',
            'role' => 'manager',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('temporary_password');
        $this->assertDatabaseHas('users', ['username' => 'classic.manager', 'must_change_password' => true]);
    }

    public function test_a_manager_creates_a_team_leader_via_the_classic_form(): void
    {
        $response = $this->actingAs($this->manager)->post('/users', [
            'full_name' => 'Classic TL',
            'username' => 'classic.tl',
            'personal_email' => 'classic.tl@dev.local',
            'role' => 'tl',
            'department_id' => $this->marketing->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['username' => 'classic.tl', 'department_id' => $this->marketing->id]);
    }

    public function test_the_edit_form_renders_and_a_classic_update_redirects(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();

        $this->actingAs($this->manager)->get(route('users.edit-form', $employee))
            ->assertOk()->assertViewIs('users.edit');

        $response = $this->actingAs($this->manager)->patch(route('users.update', $employee), [
            'full_name' => 'Renamed Employee',
            'personal_email' => $employee->personal_email,
            'department_id' => $this->marketing->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertSame('Renamed Employee', $employee->fresh()->full_name);
    }

    public function test_classic_disable_reactivate_and_reset_password_redirect_to_the_list(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();

        $this->actingAs($this->manager)->post(route('users.disable', $employee))
            ->assertRedirect(route('users.index'));
        $this->assertSame('inactive', $employee->fresh()->status->value);

        $this->actingAs($this->manager)->post(route('users.reactivate', $employee))
            ->assertRedirect(route('users.index'));
        $this->assertSame('active', $employee->fresh()->status->value);

        $response = $this->actingAs($this->manager)->post(route('users.reset-password', $employee));
        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('temporary_password');
    }

    public function test_an_employee_cannot_reach_user_administration(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();

        $this->actingAs($employee)->get('/users')->assertForbidden();
    }
}
