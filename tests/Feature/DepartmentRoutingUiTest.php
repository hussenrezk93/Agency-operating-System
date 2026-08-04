<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\DepartmentRoute;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 3-closing real screen for the department-routing matrix. */
class DepartmentRoutingUiTest extends TestCase
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

    public function test_the_matrix_renders_with_every_active_department(): void
    {
        $response = $this->actingAs($this->admin)->get('/department-routes');

        $response->assertOk()->assertViewIs('department-routes.index');
        $response->assertSee($this->marketing->name);
        $response->assertSee($this->design->name);
    }

    public function test_clicking_a_cell_toggles_the_route_and_redirects_back(): void
    {
        $response = $this->actingAs($this->admin)->post('/department-routes', [
            'from_department_id' => $this->marketing->id,
            'to_department_id' => $this->design->id,
            'is_allowed' => '1',
        ]);

        $response->assertRedirect(route('department-routes.index'));
        $this->assertDatabaseHas('department_routes', [
            'from_department_id' => $this->marketing->id,
            'to_department_id' => $this->design->id,
            'is_allowed' => true,
        ]);
    }

    public function test_a_manager_cannot_reach_the_routing_matrix(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();

        $this->actingAs($manager)->get('/department-routes')->assertForbidden();
    }

    public function test_the_matrix_reflects_an_existing_route(): void
    {
        DepartmentRoute::factory()->create([
            'from_department_id' => $this->marketing->id,
            'to_department_id' => $this->design->id,
            'is_allowed' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/department-routes');

        $response->assertOk();
        $response->assertSee('value="0"', false); // the toggle-off form value is present for the allowed cell
    }
}
