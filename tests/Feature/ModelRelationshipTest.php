<?php

namespace Tests\Feature;

use App\Enums\LeadershipType;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Client;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_belongs_to_a_role_and_optionally_to_a_department(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->role(RoleCode::Employee)->inDepartment($department)->create();

        $this->assertSame(RoleCode::Employee, $user->roleCode());
        $this->assertTrue($user->department->is($department));
        $this->assertTrue($department->users->contains($user));
    }

    public function test_a_user_may_exist_without_a_department(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();

        $this->assertNull($manager->department_id);
        $this->assertNull($manager->department);
    }

    public function test_enum_casts_are_applied(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $project = Project::factory()->create();

        $this->assertInstanceOf(UserStatus::class, $user->status);
        $this->assertSame('active', $client->status->value);
        $this->assertSame('active', $project->status->value);
    }

    public function test_a_project_belongs_to_one_client_and_many_departments(): void
    {
        $client = Client::factory()->create();
        $project = Project::factory()->for($client)->create();
        $departments = Department::factory()->count(3)->create();

        $project->departments()->attach($departments->pluck('id'));

        $this->assertTrue($project->client->is($client));
        $this->assertCount(3, $project->fresh()->departments);
        $this->assertTrue($client->projects->contains($project));
    }

    public function test_a_leadership_assignment_links_a_department_and_a_user(): void
    {
        $department = Department::factory()->create();
        $tl = User::factory()->role(RoleCode::TeamLeader)->inDepartment($department)->create();
        $manager = User::factory()->role(RoleCode::Manager)->create();

        $assignment = DepartmentLeadershipAssignment::create([
            'department_id' => $department->id,
            'user_id' => $tl->id,
            'assignment_type' => LeadershipType::Primary->value,
            'start_date' => now()->toDateString(),
            'assigned_by' => $manager->id,
        ]);

        $this->assertTrue($assignment->department->is($department));
        $this->assertTrue($assignment->user->is($tl));
        $this->assertSame(LeadershipType::Primary, $assignment->assignment_type);
        $this->assertTrue($department->leadershipAssignments->contains($assignment));
    }

    public function test_the_application_runs_on_cairo_time(): void
    {
        $this->assertSame('Africa/Cairo', config('app.timezone'));
    }
}
