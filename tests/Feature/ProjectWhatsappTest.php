<?php

namespace Tests\Feature;

use App\Enums\ActivationState;
use App\Enums\LeadershipType;
use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\Project;
use App\Models\ProjectInviteDelivery;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BRD §7.3 — WhatsApp invite-link versioning and the invite-deliveries ledger.
 * PHASE 7 — fan-out now activates every newly-created row: InApp is marked sent
 * immediately, Email is sent (and marked sent) through the sync queue used in tests
 * whenever the recipient's factory-default `email_verified_at` makes them eligible.
 */
class ProjectWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $creator;

    private Department $marketing;

    private User $marketingEmployee;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        $this->marketing = Department::factory()->create();
        $this->creator = User::factory()->role(RoleCode::TeamLeader)->inDepartment($this->marketing)->create();
        $this->marketingEmployee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();

        $this->project = Project::factory()->create(['created_by' => $this->creator->id]);
        $this->project->departments()->attach($this->marketing->id, ['is_active' => true, 'added_at' => now()]);
    }

    public function test_setting_a_link_creates_version_one_and_fans_out_to_every_member(): void
    {
        $this->actingAs($this->manager)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://chat.whatsapp.com/ABC123',
            'label' => 'Campaign Group',
        ])->assertCreated();

        $this->assertDatabaseHas('project_whatsapp_link_versions', [
            'project_id' => $this->project->id, 'version_no' => 1, 'action_type' => 'created', 'is_current' => true,
        ]);
        $this->assertSame('https://chat.whatsapp.com/ABC123', $this->project->fresh()->whatsapp_group_url);

        // Members: marketing employee (department), the TL creator (creator), the Manager (manager).
        foreach ([$this->marketingEmployee->id, $this->creator->id, $this->manager->id] as $userId) {
            $this->assertSame(
                2,
                ProjectInviteDelivery::where('project_id', $this->project->id)->where('user_id', $userId)->count(),
                "expected exactly 2 channel rows for user {$userId}",
            );
        }

        // PHASE 7 — both channel rows are activated: InApp synchronously, Email through
        // the sync queue used in tests (the factory defaults every user to verified).
        $this->assertDatabaseHas('project_invite_deliveries', [
            'project_id' => $this->project->id,
            'user_id' => $this->marketingEmployee->id,
            'recipient_source' => 'department',
            'status' => 'sent',
        ]);
        $delivery = ProjectInviteDelivery::where('project_id', $this->project->id)
            ->where('user_id', $this->marketingEmployee->id)
            ->first();
        $this->assertNotNull($delivery->notification_id);
        $this->assertDatabaseHas('notifications', [
            'id' => $delivery->notification_id,
            'user_id' => $this->marketingEmployee->id,
            'type' => 'project.invite',
        ]);
    }

    public function test_changing_the_link_creates_a_new_version_and_flips_current(): void
    {
        $this->actingAs($this->manager)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://chat.whatsapp.com/ABC123',
        ])->assertCreated();

        $this->actingAs($this->manager)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://chat.whatsapp.com/NEW456',
        ])->assertCreated();

        $this->assertDatabaseHas('project_whatsapp_link_versions', [
            'project_id' => $this->project->id, 'version_no' => 1, 'is_current' => false, 'action_type' => 'created',
        ]);
        $this->assertDatabaseHas('project_whatsapp_link_versions', [
            'project_id' => $this->project->id, 'version_no' => 2, 'is_current' => true, 'action_type' => 'updated',
        ]);
        $this->assertSame('https://chat.whatsapp.com/NEW456', $this->project->fresh()->whatsapp_group_url);
    }

    public function test_removing_the_link_clears_the_url_and_creates_a_removed_version(): void
    {
        $this->actingAs($this->manager)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://chat.whatsapp.com/ABC123',
        ])->assertCreated();

        $this->actingAs($this->manager)->deleteJson("/projects/{$this->project->id}/whatsapp")->assertOk();

        $this->assertDatabaseHas('project_whatsapp_link_versions', [
            'project_id' => $this->project->id, 'action_type' => 'removed', 'group_url' => null, 'is_current' => true,
        ]);
        $this->assertNull($this->project->fresh()->whatsapp_group_url);
    }

    public function test_a_non_whatsapp_host_is_rejected(): void
    {
        $this->actingAs($this->manager)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://evil.example.com/invite',
        ])->assertStatus(422)->assertJsonValidationErrors('url');
    }

    public function test_a_project_on_hold_cannot_receive_a_new_link(): void
    {
        $this->project->update(['status' => 'on_hold']);

        $this->actingAs($this->manager)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://chat.whatsapp.com/ABC123',
        ])->assertStatus(422)->assertJsonValidationErrors('project');
    }

    public function test_a_closed_project_cannot_receive_a_new_link(): void
    {
        $this->project->update(['status' => 'completed', 'completed_at' => now()]);

        $this->actingAs($this->manager)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://chat.whatsapp.com/ABC123',
        ])->assertForbidden();
    }

    public function test_adding_a_department_invites_only_its_new_members(): void
    {
        $this->actingAs($this->manager)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://chat.whatsapp.com/ABC123',
        ])->assertCreated();

        $design = Department::factory()->create();
        $designEmployee = User::factory()->role(RoleCode::Employee)->inDepartment($design)->create();

        $this->actingAs($this->manager)
            ->postJson("/projects/{$this->project->id}/departments", ['department_id' => $design->id])
            ->assertCreated();

        $this->assertDatabaseHas('project_invite_deliveries', [
            'project_id' => $this->project->id,
            'user_id' => $designEmployee->id,
            'department_id' => $design->id,
            'recipient_source' => 'department',
        ]);
    }

    public function test_a_rejected_duplicate_department_add_fans_out_nothing_new(): void
    {
        $this->actingAs($this->manager)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://chat.whatsapp.com/ABC123',
        ])->assertCreated();

        $this->actingAs($this->manager)
            ->postJson("/projects/{$this->project->id}/departments", ['department_id' => $this->marketing->id])
            ->assertStatus(422); // already attached

        // The reject-before-fan-out guard held: still exactly 2 channel rows, not doubled.
        $this->assertSame(
            2,
            ProjectInviteDelivery::where('project_id', $this->project->id)
                ->where('user_id', $this->marketingEmployee->id)->count(),
        );
    }

    public function test_membership_includes_a_cross_department_temporary_leader(): void
    {
        $otherDepartmentTl = User::factory()->role(RoleCode::TeamLeader)->create();

        DepartmentLeadershipAssignment::create([
            'department_id' => $this->marketing->id,
            'user_id' => $otherDepartmentTl->id,
            'assignment_type' => LeadershipType::Temporary->value,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'is_active' => true,
            'activation_state' => ActivationState::Active->value,
            'assigned_by' => $this->manager->id,
        ]);

        $this->actingAs($this->manager)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://chat.whatsapp.com/ABC123',
        ])->assertCreated();

        $this->assertDatabaseHas('project_invite_deliveries', [
            'project_id' => $this->project->id,
            'user_id' => $otherDepartmentTl->id,
            'recipient_source' => 'temp_tl',
        ]);
    }

    public function test_an_employee_cannot_set_the_whatsapp_link(): void
    {
        $this->actingAs($this->marketingEmployee)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://chat.whatsapp.com/ABC123',
        ])->assertForbidden();
    }
}
