<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Only the CONFIRMED authorization rules are asserted here.
 * Task authorization is intentionally absent — TaskPolicy is a deny-by-default
 * placeholder until the workflow phase is approved.
 */
class PolicyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manages_managers_only(): void
    {
        $admin = User::factory()->role(RoleCode::Admin)->create();
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $tl = User::factory()->role(RoleCode::TeamLeader)->create();
        $employee = User::factory()->role(RoleCode::Employee)->create();

        $this->assertTrue($admin->can('manage', $manager));
        $this->assertFalse($admin->can('manage', $tl));
        $this->assertFalse($admin->can('manage', $employee));
    }

    public function test_manager_manages_team_leaders_and_employees_but_not_managers(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $otherManager = User::factory()->role(RoleCode::Manager)->create();
        $tl = User::factory()->role(RoleCode::TeamLeader)->create();
        $employee = User::factory()->role(RoleCode::Employee)->create();

        $this->assertTrue($manager->can('manage', $tl));
        $this->assertTrue($manager->can('manage', $employee));
        $this->assertFalse($manager->can('manage', $otherManager));
    }

    public function test_team_leaders_and_employees_manage_nobody(): void
    {
        $tl = User::factory()->role(RoleCode::TeamLeader)->create();
        $employee = User::factory()->role(RoleCode::Employee)->create();

        $this->assertFalse($tl->can('manage', $employee));
        $this->assertFalse($employee->can('manage', $tl));
        $this->assertFalse($employee->can('viewAny', User::class));
    }

    public function test_only_the_manager_creates_departments(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $admin = User::factory()->role(RoleCode::Admin)->create();
        $tl = User::factory()->role(RoleCode::TeamLeader)->create();

        $this->assertTrue($manager->can('create', Department::class));
        $this->assertFalse($admin->can('create', Department::class));
        $this->assertFalse($tl->can('create', Department::class));
    }

    public function test_managers_and_team_leaders_may_create_projects(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $tl = User::factory()->role(RoleCode::TeamLeader)->create();
        $employee = User::factory()->role(RoleCode::Employee)->create();

        $this->assertTrue($manager->can('create', Project::class));
        $this->assertTrue($tl->can('create', Project::class));
        $this->assertFalse($employee->can('create', Project::class));
    }

    /**
     * APPROVED DECISION Q22 — a project may be completed or cancelled by the Manager or
     * by the original creator, including a Team Leader creator. Creator authority is
     * personal: it is never inherited by other users holding the same role.
     */
    public function test_manager_and_project_creator_may_complete_or_cancel_an_active_project(): void
    {
        $creator = User::factory()->role(RoleCode::TeamLeader)->create();
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $unrelatedTeamLeader = User::factory()->role(RoleCode::TeamLeader)->create();
        $employee = User::factory()->role(RoleCode::Employee)->create();

        $project = Project::factory()->create(['created_by' => $creator->id]);

        // Manager has full project operational authority.
        $this->assertTrue($manager->can('complete', $project));
        $this->assertTrue($manager->can('cancel', $project));

        // The original project creator has the same authority.
        $this->assertTrue($creator->can('complete', $project));
        $this->assertTrue($creator->can('cancel', $project));

        // Other users do not inherit creator permissions.
        $this->assertFalse($unrelatedTeamLeader->can('complete', $project));
        $this->assertFalse($unrelatedTeamLeader->can('cancel', $project));
        $this->assertFalse($employee->can('complete', $project));
        $this->assertFalse($employee->can('cancel', $project));
    }

    /** BRD §7.2 + Q22 — a closed project is read-only forever, for everyone. */
    public function test_a_closed_project_can_never_be_completed_cancelled_or_edited_again(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $creator = User::factory()->role(RoleCode::TeamLeader)->create();

        $completed = Project::factory()->create([
            'created_by' => $creator->id,
            'status' => ProjectStatus::Completed->value,
        ]);
        $cancelled = Project::factory()->create([
            'created_by' => $creator->id,
            'status' => ProjectStatus::Cancelled->value,
            'cancelled_reason' => 'Client dropped the engagement',
        ]);

        foreach ([$completed, $cancelled] as $project) {
            foreach ([$manager, $creator] as $actor) {
                $this->assertFalse($actor->can('complete', $project));
                $this->assertFalse($actor->can('cancel', $project));
                $this->assertFalse($actor->can('update', $project));
                $this->assertFalse($actor->can('hold', $project));
            }
        }
    }

    /** APPROVED CHANGE REQUEST Q23 — Hold/Resume carry the same authority as Complete/Cancel. */
    public function test_project_hold_and_resume_authority_matches_complete_and_cancel(): void
    {
        $creator = User::factory()->role(RoleCode::TeamLeader)->create();
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $employee = User::factory()->role(RoleCode::Employee)->create();

        $active = Project::factory()->create(['created_by' => $creator->id]);
        $onHold = Project::factory()->create([
            'created_by' => $creator->id,
            'status' => ProjectStatus::OnHold->value,
        ]);

        $this->assertTrue($manager->can('hold', $active));
        $this->assertTrue($creator->can('hold', $active));
        $this->assertFalse($employee->can('hold', $active));

        $this->assertFalse($manager->can('hold', $onHold));  // already paused
        $this->assertTrue($manager->can('resume', $onHold));
        $this->assertTrue($creator->can('resume', $onHold));
        $this->assertFalse($employee->can('resume', $onHold));
    }
}
