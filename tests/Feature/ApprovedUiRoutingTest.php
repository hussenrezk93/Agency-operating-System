<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovedUiRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_the_approved_ui(): void
    {
        $this->get('/app/manager-dashboard.html')->assertRedirect(route('login'));
    }

    public function test_each_role_can_open_its_own_dashboard(): void
    {
        foreach ([
            RoleCode::Admin->value => 'admin-dashboard.html',
            RoleCode::Manager->value => 'manager-dashboard.html',
            RoleCode::TeamLeader->value => 'tl-dashboard.html',
            RoleCode::Employee->value => 'employee-dashboard.html',
        ] as $role => $screen) {
            $user = User::factory()->role(RoleCode::from($role))->create();

            $this->actingAs($user)
                ->get(route('approved-ui', ['screen' => $screen]))
                ->assertOk()
                ->assertSee('APP_LARAVEL_BRIDGE', false)
                ->assertSee('"role":"'.$role.'"', false);

            $this->post('/logout');
        }
    }

    public function test_employee_cannot_open_manager_dashboard(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->create();

        $this->actingAs($employee)
            ->get('/app/manager-dashboard.html')
            ->assertForbidden();
    }

    public function test_manager_cannot_open_the_team_leader_assignment_screen(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();

        $this->actingAs($manager)
            ->get('/app/task-assign.html')
            ->assertForbidden();
    }

    public function test_team_leader_can_open_the_assignment_screen(): void
    {
        $leader = User::factory()->role(RoleCode::TeamLeader)->create();

        $this->actingAs($leader)
            ->get('/app/task-assign.html')
            ->assertOk();
    }

    public function test_role_query_parameter_cannot_elevate_the_laravel_role(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create(['username' => 'manager']);

        $this->actingAs($manager)
            ->get('/app/manager-dashboard.html?role=admin')
            ->assertOk()
            ->assertSee('"role":"manager"', false)
            ->assertDontSee('"role":"admin"', false);
    }

    public function test_prototype_assets_are_served_through_the_authenticated_route(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->create();

        $this->actingAs($employee)
            ->get('/app/assets/agencyos.css')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8');
    }

    /**
     * A prototype-only screen (like System Settings) used to load its own frozen copy of
     * agencyos.css, which visibly drifted from the real one as migrated Blade pages picked
     * up shadow/radius/transition refinements — same class names, stale values. It must
     * serve the SAME file the real app uses, not resources/prototype/assets/agencyos.css.
     */
    public function test_the_prototype_stylesheet_is_the_same_file_the_real_app_uses(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->create();

        $response = $this->actingAs($employee)->get('/app/assets/agencyos.css');

        $response->assertOk();
        $this->assertSame(file_get_contents(public_path('assets/agencyos.css')), $response->getContent());
    }
}
