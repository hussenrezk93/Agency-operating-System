<?php

namespace Tests\Feature;

use App\Enums\LeadershipType;
use App\Enums\RoleCode;
use App\Models\Client;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirmed invariants are enforced by the DATABASE, not only by application code
 * (BRD non-functional requirements). These tests require PostgreSQL.
 */
class DatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_username_is_unique(): void
    {
        User::factory()->create(['username' => 'taken']);

        $this->expectException(QueryException::class);
        User::factory()->create(['username' => 'taken']);
    }

    public function test_personal_email_is_unique(): void
    {
        User::factory()->create(['personal_email' => 'same@dev.local']);

        $this->expectException(QueryException::class);
        User::factory()->create(['personal_email' => 'same@dev.local']);
    }

    public function test_department_name_is_unique(): void
    {
        Department::factory()->create(['name' => 'Marketing']);

        $this->expectException(QueryException::class);
        Department::factory()->create(['name' => 'Marketing']);
    }

    public function test_project_code_is_unique(): void
    {
        $client = Client::factory()->create();
        Project::factory()->for($client)->create(['project_code' => 'PRJ-2026-0001']);

        $this->expectException(QueryException::class);
        Project::factory()->for($client)->create(['project_code' => 'PRJ-2026-0001']);
    }

    public function test_a_department_may_not_have_two_active_primary_leaders(): void
    {
        $department = Department::factory()->create();
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $first = User::factory()->role(RoleCode::TeamLeader)->create();
        $second = User::factory()->role(RoleCode::TeamLeader)->create();

        $make = fn (User $u) => DepartmentLeadershipAssignment::create([
            'department_id' => $department->id,
            'user_id' => $u->id,
            'assignment_type' => LeadershipType::Primary->value,
            'start_date' => now()->toDateString(),
            'assigned_by' => $manager->id,
        ]);

        $make($first);

        $this->expectException(QueryException::class);
        $make($second);
    }

    public function test_temporary_leadership_periods_may_not_overlap_in_one_department(): void
    {
        $department = Department::factory()->create();
        $manager = User::factory()->role(RoleCode::Manager)->create();

        $make = fn (User $u, string $from, string $to) => DepartmentLeadershipAssignment::create([
            'department_id' => $department->id,
            'user_id' => $u->id,
            'assignment_type' => LeadershipType::Temporary->value,
            'start_date' => $from,
            'end_date' => $to,
            'assigned_by' => $manager->id,
        ]);

        $make(User::factory()->role(RoleCode::TeamLeader)->create(), '2026-08-01', '2026-08-10');

        $this->expectException(QueryException::class);
        $make(User::factory()->role(RoleCode::TeamLeader)->create(), '2026-08-05', '2026-08-15');
    }

    public function test_project_department_pairs_are_unique(): void
    {
        $project = Project::factory()->create();
        $department = Department::factory()->create();

        $project->departments()->attach($department->id);

        $this->expectException(QueryException::class);
        $project->departments()->attach($department->id);
    }
}
