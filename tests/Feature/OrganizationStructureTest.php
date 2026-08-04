<?php

namespace Tests\Feature;

use App\Enums\OutputAccessScope;
use App\Enums\ProjectStatus;
use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\DepartmentOutputAccess;
use App\Models\DepartmentRoute;
use App\Models\Project;
use App\Models\ProjectLink;
use App\Models\User;
use App\Services\DepartmentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizationStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_department_routes_link_two_departments(): void
    {
        $from = Department::factory()->create();
        $to = Department::factory()->create();

        $route = DepartmentRoute::factory()->create([
            'from_department_id' => $from->id,
            'to_department_id' => $to->id,
        ]);

        $this->assertTrue($route->fromDepartment->is($from));
        $this->assertTrue($route->toDepartment->is($to));
        $this->assertSame([$to->id], $from->fresh()->allowedNextDepartmentIds());
    }

    public function test_a_department_cannot_route_to_itself(): void
    {
        $d = Department::factory()->create();

        $this->expectException(QueryException::class);
        DepartmentRoute::factory()->create([
            'from_department_id' => $d->id,
            'to_department_id' => $d->id,
        ]);
    }

    public function test_route_pairs_are_unique(): void
    {
        $from = Department::factory()->create();
        $to = Department::factory()->create();
        $attrs = ['from_department_id' => $from->id, 'to_department_id' => $to->id];

        DepartmentRoute::factory()->create($attrs);

        $this->expectException(QueryException::class);
        DepartmentRoute::factory()->create($attrs);
    }

    public function test_output_access_rules_carry_a_scope(): void
    {
        $viewer = Department::factory()->create();
        $source = Department::factory()->create();

        $rule = DepartmentOutputAccess::factory()->create([
            'viewer_department_id' => $viewer->id,
            'source_department_id' => $source->id,
            'scope' => OutputAccessScope::FinalOnly->value,
        ]);

        $this->assertSame(OutputAccessScope::FinalOnly, $rule->scope);
        $this->assertTrue($rule->viewerDepartment->is($viewer));
        $this->assertTrue($viewer->outputAccessAsViewer->contains($rule));
    }

    /**
     * Two independent layers reject an invalid scope, so both are asserted:
     * the enum cast stops it in the application, and the CHECK constraint stops it in
     * PostgreSQL for any code path that bypasses Eloquent (raw SQL, imports, psql).
     */
    public function test_an_invalid_output_scope_is_rejected_by_the_application_layer(): void
    {
        $this->expectException(\ValueError::class);
        DepartmentOutputAccess::factory()->create(['scope' => 'everything']);
    }

    public function test_an_invalid_output_scope_is_rejected_by_the_database(): void
    {
        $admin = User::factory()->role(RoleCode::Admin)->create();
        $viewer = Department::factory()->create();
        $source = Department::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('department_output_access')->insert([
            'viewer_department_id' => $viewer->id,
            'source_department_id' => $source->id,
            'scope' => 'everything',   // bypasses the enum cast on purpose
            'is_allowed' => true,
            'updated_by' => $admin->id,
            'updated_at' => now(),
        ]);
    }

    public function test_projects_hold_reference_links(): void
    {
        $project = Project::factory()->create();
        ProjectLink::factory()->count(2)->create(['project_id' => $project->id]);

        $this->assertCount(2, $project->fresh()->links);
        $this->assertTrue($project->links->first()->project->is($project));
    }

    public function test_creating_a_department_with_its_primary_leader_is_transactional(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $tl = User::factory()->role(RoleCode::TeamLeader)->create();

        $department = app(DepartmentService::class)
            ->createWithPrimaryLeader('Motion Graphics', $tl, $manager);

        $this->assertTrue($department->primaryLeader()->is($tl));
        $this->assertTrue($department->effectiveLeader()->is($tl));
        $this->assertSame($department->id, $tl->refresh()->department_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'department.created']);
    }

    public function test_a_department_cannot_be_created_with_a_non_team_leader(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();
        $employee = User::factory()->role(RoleCode::Employee)->create();

        $this->expectException(ValidationException::class);
        app(DepartmentService::class)->createWithPrimaryLeader('Broken', $employee, $manager);

        $this->assertDatabaseMissing('departments', ['name' => 'Broken']);
    }

    public function test_project_status_supports_on_hold_as_an_approved_change_request(): void
    {
        $project = Project::factory()->create(['status' => ProjectStatus::OnHold->value]);

        $this->assertSame(ProjectStatus::OnHold, $project->refresh()->status);
        $this->assertFalse($project->isClosed());
        $this->assertFalse($project->acceptsNewTasks()); // no new tasks while paused (Q23)
    }

    public function test_an_invalid_project_status_is_rejected_by_the_application_layer(): void
    {
        $this->expectException(\ValueError::class);
        Project::factory()->create(['status' => 'archived']);
    }

    public function test_an_invalid_project_status_is_rejected_by_the_database(): void
    {
        $project = Project::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('projects')->where('id', $project->id)->update(['status' => 'archived']);
    }

    public function test_a_cancelled_project_must_carry_a_reason(): void
    {
        $this->expectException(QueryException::class);
        Project::factory()->create([
            'status' => ProjectStatus::Cancelled->value,
            'cancelled_reason' => null,
        ]);
    }

    public function test_an_invalid_user_status_is_rejected_by_the_application_layer(): void
    {
        $this->expectException(\ValueError::class);
        User::factory()->create(['status' => 'sabbatical']);
    }

    public function test_an_invalid_user_status_is_rejected_by_the_database(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('users')->where('id', $user->id)->update(['status' => 'sabbatical']);
    }
}
