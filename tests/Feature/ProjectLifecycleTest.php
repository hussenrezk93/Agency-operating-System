<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Client;
use App\Models\Department;
use App\Models\DepartmentRoute;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BRD §7.2 — project lifecycle. Re-proves PolicyFoundationTest's policy-level
 * complete/cancel/hold/resume assertions now hold over HTTP, plus the TL
 * department-scoping rule this phase adds.
 */
class ProjectLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Client $client;

    private Department $marketing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        $this->client = Client::factory()->create();
        $this->marketing = Department::factory()->create();
    }

    public function test_manager_creates_a_project_with_departments_and_links(): void
    {
        $design = Department::factory()->create();

        $response = $this->actingAs($this->manager)->postJson('/projects', [
            'client_id' => $this->client->id,
            'name' => 'Q3 Campaign',
            'description' => 'Launch campaign',
            'department_ids' => [$this->marketing->id, $design->id],
            'links' => [['url' => 'https://example.test/brief', 'label' => 'Brief']],
        ])->assertCreated();

        $this->assertMatchesRegularExpression('/^PRJ-\d{4}-\d{4}$/', $response->json('data.project_code'));
        $this->assertDatabaseHas('project_departments', [
            'department_id' => $this->marketing->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('project_links', ['url' => 'https://example.test/brief']);
    }

    /** BRD §7.2 — a new client may be created inline instead of picking an existing one. */
    public function test_manager_creates_a_project_with_a_brand_new_client(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/projects', [
            'client_source' => 'new',
            'new_client_name' => 'Horizon Retail',
            'new_client_phone' => '+20 100 000 0000',
            'name' => 'Horizon Launch',
            'department_ids' => [$this->marketing->id],
        ])->assertCreated();

        $this->assertDatabaseHas('clients', ['name' => 'Horizon Retail', 'phone' => '+20 100 000 0000']);

        $client = Client::where('name', 'Horizon Retail')->firstOrFail();
        $this->assertSame($client->id, $response->json('data.client_id'));
    }

    public function test_creating_a_project_with_a_new_client_but_no_name_is_rejected(): void
    {
        $this->actingAs($this->manager)->postJson('/projects', [
            'client_source' => 'new',
            'new_client_phone' => '+20 100 000 0000',
            'name' => 'Horizon Launch',
            'department_ids' => [$this->marketing->id],
        ])->assertStatus(422)->assertJsonValidationErrors('new_client_name');
    }

    public function test_a_team_leader_may_add_their_own_department_and_allowed_targets(): void
    {
        $allowed = Department::factory()->create();
        DepartmentRoute::factory()->create([
            'from_department_id' => $this->marketing->id,
            'to_department_id' => $allowed->id,
            'is_allowed' => true,
        ]);
        $tl = User::factory()->role(RoleCode::TeamLeader)->inDepartment($this->marketing)->create();

        $this->actingAs($tl)->postJson('/projects', [
            'client_id' => $this->client->id,
            'name' => 'TL Project',
            'department_ids' => [$this->marketing->id, $allowed->id],
        ])->assertCreated();
    }

    public function test_a_team_leader_cannot_add_a_department_outside_their_allowed_set(): void
    {
        $disallowed = Department::factory()->create();
        $tl = User::factory()->role(RoleCode::TeamLeader)->inDepartment($this->marketing)->create();

        $this->actingAs($tl)->postJson('/projects', [
            'client_id' => $this->client->id,
            'name' => 'Blocked Project',
            'department_ids' => [$this->marketing->id, $disallowed->id],
        ])->assertStatus(422)->assertJsonValidationErrors('department_ids');
    }

    public function test_employee_cannot_create_a_project(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();

        $this->actingAs($employee)->postJson('/projects', [
            'client_id' => $this->client->id,
            'name' => 'Nope',
            'department_ids' => [$this->marketing->id],
        ])->assertForbidden();
    }

    public function test_manager_or_creator_completes_a_project(): void
    {
        $creator = User::factory()->role(RoleCode::TeamLeader)->inDepartment($this->marketing)->create();
        $project = Project::factory()->create(['created_by' => $creator->id]);

        $this->actingAs($creator)->postJson("/projects/{$project->id}/complete")->assertOk();

        $this->assertSame('completed', $project->fresh()->status->value);
        $this->assertNotNull($project->fresh()->completed_at);
    }

    public function test_a_completed_project_is_read_only(): void
    {
        $project = Project::factory()->create(['status' => 'completed', 'completed_at' => now()]);

        $this->actingAs($this->manager)
            ->patchJson("/projects/{$project->id}", ['name' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($this->manager)->postJson("/projects/{$project->id}/cancel", [
            'reason' => 'too late',
        ])->assertForbidden();
    }

    public function test_cancelling_a_project_requires_a_reason(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->manager)->postJson("/projects/{$project->id}/cancel", [])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
    }

    public function test_manager_cancels_a_project_with_a_reason(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->manager)->postJson("/projects/{$project->id}/cancel", [
            'reason' => 'Client dropped the engagement',
        ])->assertOk();

        $fresh = $project->fresh();
        $this->assertSame('cancelled', $fresh->status->value);
        $this->assertSame('Client dropped the engagement', $fresh->cancelled_reason);
    }

    public function test_manager_holds_and_resumes_a_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->manager)->postJson("/projects/{$project->id}/hold", [
            'reason' => 'Awaiting client sign-off',
        ])->assertOk();
        $this->assertSame('on_hold', $project->fresh()->status->value);
        $this->assertDatabaseHas('project_holds', ['project_id' => $project->id, 'ended_at' => null]);

        $this->actingAs($this->manager)->postJson("/projects/{$project->id}/resume")->assertOk();
        $this->assertSame('active', $project->fresh()->status->value);
        $this->assertDatabaseMissing('project_holds', ['project_id' => $project->id, 'ended_at' => null]);
    }

    public function test_an_unrelated_team_leader_cannot_complete_or_cancel(): void
    {
        $creator = User::factory()->role(RoleCode::TeamLeader)->create();
        $unrelated = User::factory()->role(RoleCode::TeamLeader)->create();
        $project = Project::factory()->create(['created_by' => $creator->id]);

        $this->actingAs($unrelated)->postJson("/projects/{$project->id}/complete")->assertForbidden();
        $this->actingAs($unrelated)->postJson("/projects/{$project->id}/cancel", [
            'reason' => 'attempted takeover',
        ])->assertForbidden();
    }

    public function test_a_guest_cannot_reach_project_administration(): void
    {
        $this->get('/projects')->assertRedirect(route('login'));
    }
}
