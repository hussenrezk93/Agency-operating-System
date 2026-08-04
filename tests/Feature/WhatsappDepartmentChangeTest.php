<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use App\Services\ProjectWhatsappService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * BRD §7.3 — a department's WhatsApp membership stays in sync with who actually belongs
 * to it: joining invites, leaving/disabling alerts the TL to remove them by hand (Agency OS
 * never touches the WhatsApp API itself).
 */
class WhatsappDepartmentChangeTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private Department $design;

    private User $manager;

    private User $marketingLeader;

    private User $designLeader;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment('Marketing');
        $this->design = $this->makeDepartment('Design');
        $this->manager = $this->makeManager();
        $this->marketingLeader = $this->makeTeamLeader($this->marketing);
        $this->designLeader = $this->makeTeamLeader($this->design);

        $this->project = Project::factory()->create(['created_by' => $this->manager->id]);
        $this->addDepartmentToProject($this->project, $this->marketing);

        app(ProjectWhatsappService::class)->setLink(
            $this->project, 'https://chat.whatsapp.com/ABC123', 'Group', $this->manager,
        );
    }

    private function users(): UserService
    {
        return app(UserService::class);
    }

    private function projects(): ProjectService
    {
        return app(ProjectService::class);
    }

    public function test_a_new_employee_joining_the_department_is_invited_to_its_active_projects(): void
    {
        ['user' => $employee] = $this->users()->create(
            RoleCode::Employee,
            ['full_name' => 'New Hire', 'username' => 'newhire', 'personal_email' => 'newhire@example.test'],
            $this->marketing,
            $this->manager,
        );

        $this->assertDatabaseHas('project_invite_deliveries', [
            'project_id' => $this->project->id,
            'user_id' => $employee->id,
            'channel' => 'in_app',
        ]);
    }

    public function test_an_employee_moving_departments_is_invited_to_the_new_ones_projects_and_alerts_the_old_leader(): void
    {
        $employee = $this->makeEmployee($this->marketing);

        $designProject = Project::factory()->create(['created_by' => $this->manager->id]);
        $this->addDepartmentToProject($designProject, $this->design);
        app(ProjectWhatsappService::class)->setLink(
            $designProject, 'https://chat.whatsapp.com/XYZ789', 'Design Group', $this->manager,
        );

        $this->users()->updateProfile($employee, ['department_id' => $this->design->id], $this->manager);

        $this->assertDatabaseHas('project_invite_deliveries', [
            'project_id' => $designProject->id,
            'user_id' => $employee->id,
            'channel' => 'in_app',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->marketingLeader->id,
            'type' => 'whatsapp.member_left',
        ]);
    }

    public function test_disabling_a_user_alerts_their_departments_leader(): void
    {
        $employee = $this->makeEmployee($this->marketing);

        $this->users()->disable($employee, $this->manager);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->marketingLeader->id,
            'type' => 'whatsapp.member_left',
        ]);
    }

    public function test_removing_a_department_from_a_project_alerts_its_leader(): void
    {
        $this->projects()->removeDepartment($this->project, $this->marketing, $this->manager);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->marketingLeader->id,
            'type' => 'whatsapp.department_removed',
        ]);
    }

    public function test_the_alert_never_targets_the_leader_being_removed_themselves(): void
    {
        $this->users()->disable($this->marketingLeader, $this->manager);

        $this->assertSame(0, Notification::where('type', 'whatsapp.member_left')
            ->where('user_id', $this->marketingLeader->id)
            ->count());
    }
}
