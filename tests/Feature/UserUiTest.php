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

    /** Product decision 2026-08 — a leaderless department is still a valid staffing target. */
    public function test_the_create_user_form_offers_a_leaderless_department(): void
    {
        $leaderless = Department::factory()->create(['name' => 'Newly Formed', 'is_active' => false]);

        $response = $this->actingAs($this->manager)->get('/users/create');

        $response->assertOk()->assertSee('Newly Formed');
    }

    public function test_the_user_list_is_scoped_by_the_viewing_actor(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();
        // A second Admin, distinct from the acting one.
        $otherAdmin = User::factory()->role(RoleCode::Admin)->create();
        // A second Manager (2026-09 widening — see below), distinct from the acting one.
        $otherManager = User::factory()->role(RoleCode::Manager)->create();

        // Admin sees every account, Admins included (product decision 2026-08) —
        // Manager, TL, Employee, and other Admins alike.
        $asAdmin = $this->actingAs($this->admin)->get('/users');
        $asAdmin->assertOk()->assertViewIs('users.index');
        $asAdmin->assertSee($this->manager->full_name);
        $asAdmin->assertSee($employee->full_name);
        $asAdmin->assertSee($otherAdmin->full_name);

        // A Manager's range (2026-09, widened to match Admin's): Manager/TL/Employee, never Admins.
        $asManager = $this->actingAs($this->manager)->get('/users');
        $asManager->assertSee($employee->full_name);
        $asManager->assertSee($otherManager->full_name);
        $asManager->assertDontSee($otherAdmin->full_name);
    }

    /** Admin sees another Admin's row, but it carries no manage actions (view-only). */
    /** Product decision (2026-08): another admin's row IS manageable, but the actor's own row isn't. */
    public function test_another_admins_row_has_manage_actions_but_the_actors_own_row_does_not(): void
    {
        $otherAdmin = User::factory()->role(RoleCode::Admin)->create();

        $response = $this->actingAs($this->admin)->get('/users');

        $response->assertSee($otherAdmin->full_name);
        $response->assertSee(route('users.edit-form', $otherAdmin), false);
        $response->assertDontSee(route('users.edit-form', $this->admin), false);
    }

    /** The Activity link is the discoverable entry point onto ProfileController::edit()'s
     *  Activity tab — gated by the same UserPolicy::viewPerformance() boundary the page
     *  itself enforces (see ProfileActivityTest), so it must track that policy exactly. */
    public function test_the_activity_link_follows_viewperformance_visibility(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();
        $activityUrl = route('profile.edit', $employee);

        // Manager can view anyone's performance -> link appears.
        $asManager = $this->actingAs($this->manager)->get('/users');
        $asManager->assertSee($activityUrl, false);

        // Admin is excluded from viewPerformance entirely -> link never appears.
        $asAdmin = $this->actingAs($this->admin)->get('/users');
        $asAdmin->assertDontSee($activityUrl, false);
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

    public function test_the_admin_create_form_offers_every_manageable_role(): void
    {
        $response = $this->actingAs($this->admin)->get('/users/create');

        $response->assertOk();
        $response->assertSee('value="admin"', false);
        $response->assertSee('value="manager"', false);
        $response->assertSee('value="tl"', false);
        $response->assertSee('value="employee"', false);
    }

    /** 2026-09 widening — a Manager's create form now offers Manager too, never Admin. */
    public function test_the_manager_create_form_offers_every_manageable_role(): void
    {
        $response = $this->actingAs($this->manager)->get('/users/create');

        $response->assertOk();
        $response->assertDontSee('value="admin"', false);
        $response->assertSee('value="manager"', false);
        $response->assertSee('value="tl"', false);
        $response->assertSee('value="employee"', false);
    }

    public function test_admin_creates_a_team_leader_via_the_classic_form(): void
    {
        $response = $this->actingAs($this->admin)->post('/users', [
            'full_name' => 'Admin Classic TL',
            'username' => 'admin.classic.tl',
            'personal_email' => 'admin.classic.tl@dev.local',
            'role' => 'tl',
            'department_id' => $this->marketing->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['username' => 'admin.classic.tl', 'department_id' => $this->marketing->id]);
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

    /** 2026-09 widening — a Manager can now create a peer Manager account (no department). */
    public function test_a_manager_creates_another_manager_via_the_classic_form(): void
    {
        $response = $this->actingAs($this->manager)->post('/users', [
            'full_name' => 'Classic Manager',
            'username' => 'classic.manager.by.manager',
            'personal_email' => 'classic.manager.by.manager@dev.local',
            'role' => 'manager',
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['username' => 'classic.manager.by.manager', 'department_id' => null]);
    }

    public function test_the_edit_form_renders_and_a_classic_update_redirects(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();

        $this->actingAs($this->manager)->get(route('users.edit-form', $employee))
            ->assertOk()->assertViewIs('users.edit')
            // Regression guard: the header used to print the literal string
            // "{{ $user->username }}" instead of the actual username, because it
            // was escaped with Blade's `@{{ }}` (meant for JS template literals).
            ->assertSee($employee->username)
            ->assertDontSee('{{ $user->username }}', false);

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
