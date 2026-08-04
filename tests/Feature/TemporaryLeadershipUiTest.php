<?php

namespace Tests\Feature;

use App\Enums\ActivationState;
use App\Enums\LeadershipType;
use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Phase 3-closing real screens for Temporary Team Leader administration. */
class TemporaryLeadershipUiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Department $marketing;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-08-01 09:00:00', config('app.timezone')));

        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        $this->marketing = Department::factory()->create();
        $this->employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_list_renders_and_the_create_form_is_manager_only(): void
    {
        $this->actingAs($this->manager)->get('/temporary-leadership')->assertOk()->assertViewIs('temporary-leadership.index');
        $this->actingAs($this->manager)->get('/temporary-leadership/create')->assertOk()->assertViewIs('temporary-leadership.create');

        $tl = User::factory()->role(RoleCode::TeamLeader)->create();
        $this->actingAs($tl)->get('/temporary-leadership')->assertForbidden();
    }

    public function test_a_classic_form_post_appoints_a_temporary_leader_and_redirects(): void
    {
        $response = $this->actingAs($this->manager)->post('/temporary-leadership', [
            'department_id' => $this->marketing->id,
            'user_id' => $this->employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-10',
            'reason' => 'Primary Team Leader on annual leave',
        ]);

        $response->assertRedirect(route('temporary-leadership.index'));
        $this->assertDatabaseHas('department_leadership_assignments', [
            'department_id' => $this->marketing->id,
            'user_id' => $this->employee->id,
            'assignment_type' => 'temporary',
        ]);
    }

    public function test_a_classic_end_early_redirects_to_the_list(): void
    {
        $assignment = DepartmentLeadershipAssignment::create([
            'department_id' => $this->marketing->id,
            'user_id' => $this->employee->id,
            'assignment_type' => LeadershipType::Temporary->value,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-10',
            'is_active' => true,
            'activation_state' => ActivationState::Active->value,
            'assigned_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->manager)->post(route('temporary-leadership.end', $assignment));

        $response->assertRedirect(route('temporary-leadership.index'));
        $this->assertFalse((bool) $assignment->fresh()->is_active);
    }
}
