<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\DepartmentRoute;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** BRD §7.2 — adding/removing a project's participating departments. */
class ProjectDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Department $marketing;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        $this->marketing = Department::factory()->create();
        $this->project = Project::factory()->create(['created_by' => $this->manager->id]);
        $this->project->departments()->attach($this->marketing->id, ['is_active' => true, 'added_at' => now()]);
    }

    public function test_manager_adds_a_department_to_a_project(): void
    {
        $design = Department::factory()->create();

        $this->actingAs($this->manager)
            ->postJson("/projects/{$this->project->id}/departments", ['department_id' => $design->id])
            ->assertCreated();

        $this->assertDatabaseHas('project_departments', [
            'project_id' => $this->project->id,
            'department_id' => $design->id,
            'is_active' => true,
        ]);
    }

    public function test_a_department_cannot_be_added_twice(): void
    {
        $this->actingAs($this->manager)
            ->postJson("/projects/{$this->project->id}/departments", ['department_id' => $this->marketing->id])
            ->assertStatus(422);
    }

    public function test_manager_removes_a_department_without_deleting_the_history_row(): void
    {
        $this->actingAs($this->manager)
            ->deleteJson("/projects/{$this->project->id}/departments/{$this->marketing->id}")
            ->assertOk();

        $this->assertDatabaseHas('project_departments', [
            'project_id' => $this->project->id,
            'department_id' => $this->marketing->id,
            'is_active' => false,
        ]);
    }

    public function test_a_team_leader_may_only_add_their_own_or_allowed_departments(): void
    {
        $own = Department::factory()->create();
        $allowed = Department::factory()->create();
        $disallowed = Department::factory()->create();
        DepartmentRoute::factory()->create([
            'from_department_id' => $own->id, 'to_department_id' => $allowed->id, 'is_allowed' => true,
        ]);
        $tl = User::factory()->role(RoleCode::TeamLeader)->inDepartment($own)->create();
        $project = Project::factory()->create(['created_by' => $tl->id]);

        $this->actingAs($tl)
            ->postJson("/projects/{$project->id}/departments", ['department_id' => $allowed->id])
            ->assertCreated();

        $this->actingAs($tl)
            ->postJson("/projects/{$project->id}/departments", ['department_id' => $disallowed->id])
            ->assertStatus(422);
    }

    public function test_a_closed_project_cannot_have_departments_changed(): void
    {
        $closed = Project::factory()->create(['status' => 'completed', 'completed_at' => now()]);
        $design = Department::factory()->create();

        $this->actingAs($this->manager)
            ->postJson("/projects/{$closed->id}/departments", ['department_id' => $design->id])
            ->assertForbidden();
    }

    public function test_an_unrelated_team_leader_cannot_modify_departments(): void
    {
        $outsider = User::factory()->role(RoleCode::TeamLeader)->create();
        $design = Department::factory()->create();

        $this->actingAs($outsider)
            ->postJson("/projects/{$this->project->id}/departments", ['department_id' => $design->id])
            ->assertForbidden();
    }
}
