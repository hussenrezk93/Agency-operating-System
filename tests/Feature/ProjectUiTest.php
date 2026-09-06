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

/** Phase 5-style real screens for projects, wired to the existing ProjectService/ProjectPolicy/ProjectWhatsappService. */
class ProjectUiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Department $marketing;

    private User $tl;

    private User $employee;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        $this->marketing = Department::factory()->create();
        $this->tl = User::factory()->role(RoleCode::TeamLeader)->inDepartment($this->marketing)->create();
        $this->employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();
        $this->client = Client::factory()->create();
    }

    private function activeProject(array $overrides = []): Project
    {
        $project = Project::factory()->create($overrides + ['client_id' => $this->client->id]);
        $project->departments()->attach($this->marketing->id, ['is_active' => true, 'added_at' => now()]);

        return $project->refresh();
    }

    public function test_the_project_list_renders_and_scopes_by_department_for_non_managers(): void
    {
        $mine = $this->activeProject();
        $elsewhere = $this->activeProject();
        $elsewhere->departments()->detach($this->marketing->id);
        $otherDept = Department::factory()->create();
        $elsewhere->departments()->attach($otherDept->id, ['is_active' => true, 'added_at' => now()]);

        $response = $this->actingAs($this->employee)->get('/projects');

        $response->assertOk()->assertViewIs('projects.index');
        $response->assertSee($mine->project_code);
        $response->assertDontSee($elsewhere->project_code);
    }

    public function test_the_create_form_renders_for_manager_and_tl_but_not_employee(): void
    {
        $this->actingAs($this->manager)->get('/projects/create')->assertOk()->assertViewIs('projects.create');
        $this->actingAs($this->tl)->get('/projects/create')->assertOk()->assertViewIs('projects.create');
        $this->actingAs($this->employee)->get('/projects/create')->assertForbidden();
    }

    public function test_a_classic_form_post_creates_a_project_and_redirects_to_its_page(): void
    {
        $response = $this->actingAs($this->manager)->post('/projects', [
            'client_id' => $this->client->id,
            'name' => 'Classic Form Project',
            'description' => 'Created through the real Blade form.',
            'department_ids' => [$this->marketing->id],
            'links' => [
                ['url' => '', 'label' => ''],
                ['url' => '', 'label' => ''],
                ['url' => '', 'label' => ''],
            ],
        ]);

        $project = Project::where('name', 'Classic Form Project')->firstOrFail();

        $response->assertRedirect(route('projects.show', $project));
        $this->assertTrue($project->departments()->where('departments.id', $this->marketing->id)->exists());
    }

    public function test_the_create_form_offers_two_reference_and_two_material_link_slots(): void
    {
        $response = $this->actingAs($this->manager)->get(route('projects.create-form'));

        $response->assertOk();
        $response->assertSee(__('agencyos.tasks.fields.reference_link_n', ['n' => 1]));
        $response->assertSee(__('agencyos.tasks.fields.reference_link_n', ['n' => 2]));
        $response->assertDontSee(__('agencyos.tasks.fields.reference_link_n', ['n' => 3]));
        $response->assertSee(__('agencyos.tasks.fields.material_link_n', ['n' => 1]));
        $response->assertSee(__('agencyos.tasks.fields.material_link_n', ['n' => 2]));
    }

    public function test_a_classic_form_post_saves_both_reference_and_material_links(): void
    {
        $response = $this->actingAs($this->manager)->post('/projects', [
            'client_id' => $this->client->id,
            'name' => 'Project With Material Links',
            'description' => 'Created through the real Blade form.',
            'department_ids' => [$this->marketing->id],
            'links' => [
                ['url' => 'https://example.test/reference-1', 'label' => 'Reference 1'],
                ['url' => 'https://example.test/reference-2', 'label' => 'Reference 2'],
                ['url' => 'https://example.test/material-1', 'label' => 'Material 1'],
                ['url' => 'https://example.test/material-2', 'label' => 'Material 2'],
            ],
        ]);

        $project = Project::where('name', 'Project With Material Links')->firstOrFail();

        $response->assertRedirect(route('projects.show', $project));
        $this->assertDatabaseHas('project_links', ['project_id' => $project->id, 'url' => 'https://example.test/material-1']);
        $this->assertDatabaseHas('project_links', ['project_id' => $project->id, 'url' => 'https://example.test/material-2']);
        $this->assertSame(4, $project->links()->count());
    }

    public function test_a_team_leader_cannot_create_a_project_outside_their_allowed_departments(): void
    {
        $disallowed = Department::factory()->create();

        $response = $this->actingAs($this->tl)->post('/projects', [
            'client_id' => $this->client->id,
            'name' => 'Blocked Project',
            'department_ids' => [$disallowed->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('department_ids');
        $this->assertDatabaseMissing('projects', ['name' => 'Blocked Project']);
    }

    public function test_the_show_page_renders_with_members_and_gated_actions(): void
    {
        $project = $this->activeProject(['created_by' => $this->tl->id]);

        $response = $this->actingAs($this->manager)->get(route('projects.show', $project));

        $response->assertOk()->assertViewIs('projects.show');
        $response->assertViewHas('canComplete', true);
        $response->assertSee($this->employee->full_name);
        $response->assertSee($this->manager->full_name);
    }

    public function test_classic_lifecycle_actions_redirect_to_the_show_page(): void
    {
        $project = $this->activeProject();

        $this->actingAs($this->manager)
            ->post(route('projects.hold', $project), ['reason' => 'Awaiting sign-off'])
            ->assertRedirect(route('projects.show', $project));
        $this->assertSame('on_hold', $project->fresh()->status->value);

        $this->actingAs($this->manager)
            ->post(route('projects.resume', $project))
            ->assertRedirect(route('projects.show', $project));
        $this->assertSame('active', $project->fresh()->status->value);

        $this->actingAs($this->manager)
            ->post(route('projects.complete', $project))
            ->assertRedirect(route('projects.show', $project));
        $this->assertSame('completed', $project->fresh()->status->value);
    }

    public function test_a_classic_cancel_with_a_reason_redirects_to_the_show_page(): void
    {
        $project = $this->activeProject();

        $this->actingAs($this->manager)
            ->post(route('projects.cancel', $project), ['reason' => 'Client dropped the engagement'])
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame('cancelled', $project->fresh()->status->value);
    }

    public function test_classic_add_department_and_add_link_redirect_to_the_show_page(): void
    {
        $project = $this->activeProject();
        $design = Department::factory()->create();
        DepartmentRoute::factory()->create([
            'from_department_id' => $this->marketing->id, 'to_department_id' => $design->id, 'is_allowed' => true,
        ]);

        $this->actingAs($this->manager)
            ->post(route('projects.departments.store', $project), ['department_id' => $design->id])
            ->assertRedirect(route('projects.show', $project));
        $this->assertTrue($project->departments()->where('departments.id', $design->id)->exists());

        $this->actingAs($this->manager)
            ->post(route('projects.links.store', $project), ['url' => 'https://example.test/brief'])
            ->assertRedirect(route('projects.show', $project));
        $this->assertDatabaseHas('project_links', ['project_id' => $project->id, 'url' => 'https://example.test/brief']);
    }

    /** The "Add department" dropdown must offer departments to ADD — not ones already on the project. */
    public function test_the_add_department_dropdown_excludes_departments_already_on_the_project(): void
    {
        $project = $this->activeProject();
        $design = Department::factory()->create();

        $response = $this->actingAs($this->manager)->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertSee('name="department_id"', false);
        $response->assertSee($design->name);
        // $this->marketing is already attached via activeProject() — its name must not
        // reach the dropdown a second time even though it still appears elsewhere on
        // the page (the "current departments" tag list right above the form).
        $optionsHtml = str($response->getContent())
            ->after('name="department_id"')
            ->before('</select>')
            ->toString();
        $this->assertStringNotContainsString($this->marketing->name, $optionsHtml);
        $this->assertStringContainsString($design->name, $optionsHtml);
    }

    /** No departments left to add → the form itself should not render at all. */
    public function test_the_add_department_form_is_hidden_once_every_active_department_is_already_attached(): void
    {
        $project = $this->activeProject();
        // marketing is the ONLY active department in this test's world (setUp creates
        // just one), and activeProject() already attaches it — nothing left to offer.
        $response = $this->actingAs($this->manager)->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertDontSee('name="department_id"', false);
    }

    public function test_classic_whatsapp_set_and_remove_redirect_to_the_show_page(): void
    {
        $project = $this->activeProject();

        $this->actingAs($this->manager)
            ->post(route('projects.whatsapp.store', $project), ['url' => 'https://chat.whatsapp.com/ABC123'])
            ->assertRedirect(route('projects.show', $project));
        $this->assertSame('https://chat.whatsapp.com/ABC123', $project->fresh()->whatsapp_group_url);

        $this->actingAs($this->manager)
            ->delete(route('projects.whatsapp.destroy', $project))
            ->assertRedirect(route('projects.show', $project));
        $this->assertNull($project->fresh()->whatsapp_group_url);
    }

    public function test_a_guest_cannot_reach_project_screens(): void
    {
        $this->get('/projects')->assertRedirect(route('login'));
    }
}
