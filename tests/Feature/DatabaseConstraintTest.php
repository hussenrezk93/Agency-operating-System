<?php

namespace Tests\Feature;

use App\Enums\LeadershipType;
use App\Enums\RoleCode;
use App\Models\Client;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\MonthlyPerformanceSnapshot;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirmed invariants are enforced by the DATABASE, not only by application code
 * (BRD non-functional requirements). On MySQL, several of these are generated-column +
 * unique-index pairs standing in for what used to be PostgreSQL partial unique indexes —
 * see the doc comments on the relevant migrations for why.
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

    public function test_a_user_may_not_actively_lead_two_departments_at_once(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $leader = User::factory()->role(RoleCode::TeamLeader)->create();
        $first = Department::factory()->create();
        $second = Department::factory()->create();

        $make = fn (Department $d) => DepartmentLeadershipAssignment::create([
            'department_id' => $d->id,
            'user_id' => $leader->id,
            'assignment_type' => LeadershipType::Primary->value,
            'start_date' => now()->toDateString(),
            'assigned_by' => $manager->id,
        ]);

        $make($first);

        $this->expectException(QueryException::class);
        $make($second);
    }

    /** A row that is NOT active must coexist fine with an active one for the same user. */
    public function test_an_ended_leadership_assignment_does_not_block_a_new_active_one(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $leader = User::factory()->role(RoleCode::TeamLeader)->create();
        $first = Department::factory()->create();
        $second = Department::factory()->create();

        DepartmentLeadershipAssignment::create([
            'department_id' => $first->id,
            'user_id' => $leader->id,
            'assignment_type' => LeadershipType::Primary->value,
            'start_date' => now()->subMonth()->toDateString(),
            'is_active' => false,
            'assigned_by' => $manager->id,
        ]);

        $second_assignment = DepartmentLeadershipAssignment::create([
            'department_id' => $second->id,
            'user_id' => $leader->id,
            'assignment_type' => LeadershipType::Primary->value,
            'start_date' => now()->toDateString(),
            'assigned_by' => $manager->id,
        ]);

        $this->assertNotNull($second_assignment->id);
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

    public function test_a_user_may_not_have_two_snapshots_of_the_same_type_in_one_month(): void
    {
        $user = User::factory()->create();
        $month = now()->startOfMonth()->toDateString();

        $make = fn () => MonthlyPerformanceSnapshot::create([
            'user_id' => $user->id,
            'month_start' => $month,
            'due_steps' => 1,
            'on_time_steps' => 1,
            'overdue_steps' => 0,
            'score' => 100,
            'snapshot_type' => 'employee',
        ]);

        $make();

        $this->expectException(QueryException::class);
        $make();
    }

    public function test_a_department_may_not_have_two_snapshots_in_one_month(): void
    {
        $department = Department::factory()->create();
        $month = now()->startOfMonth()->toDateString();

        $make = fn () => MonthlyPerformanceSnapshot::create([
            'department_id' => $department->id,
            'month_start' => $month,
            'due_steps' => 1,
            'on_time_steps' => 1,
            'overdue_steps' => 0,
            'score' => 100,
            'snapshot_type' => 'department',
        ]);

        $make();

        $this->expectException(QueryException::class);
        $make();
    }

    /** Different snapshot_type in the same month is a different row, not a collision. */
    public function test_the_same_user_may_have_snapshots_of_different_types_in_one_month(): void
    {
        $user = User::factory()->create();
        $month = now()->startOfMonth()->toDateString();

        $make = fn (string $type) => MonthlyPerformanceSnapshot::create([
            'user_id' => $user->id,
            'month_start' => $month,
            'due_steps' => 1,
            'on_time_steps' => 1,
            'overdue_steps' => 0,
            'score' => 100,
            'snapshot_type' => $type,
        ]);

        $make('tl_personal');
        $second = $make('tl_team');

        $this->assertNotNull($second->id);
    }
}
