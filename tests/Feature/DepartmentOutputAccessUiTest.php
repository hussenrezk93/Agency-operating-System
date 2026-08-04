<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\DepartmentOutputAccess;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 3-closing real screen for cross-department output-access rules. */
class DepartmentOutputAccessUiTest extends TestCase
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

    public function test_the_list_renders_with_existing_rules(): void
    {
        DepartmentOutputAccess::factory()->create([
            'viewer_department_id' => $this->marketing->id,
            'source_department_id' => $this->design->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/department-output-access');

        $response->assertOk()->assertViewIs('department-output-access.index');
        $response->assertSee($this->marketing->name);
    }

    public function test_a_classic_form_post_creates_a_rule_and_redirects_back(): void
    {
        $response = $this->actingAs($this->admin)->post('/department-output-access', [
            'viewer_department_id' => $this->marketing->id,
            'source_department_id' => $this->design->id,
            'scope' => 'final_only',
            'is_allowed' => '1',
        ]);

        $response->assertRedirect(route('department-output-access.index'));
        $this->assertDatabaseHas('department_output_access', [
            'viewer_department_id' => $this->marketing->id,
            'source_department_id' => $this->design->id,
        ]);
    }

    public function test_a_manager_cannot_reach_output_access_administration(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();

        $this->actingAs($manager)->get('/department-output-access')->assertForbidden();
    }
}
