<?php

namespace Tests\Feature;

use App\Enums\DeadlineStatus;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** PHASE 9 — each of the 4 roles gets its own dashboard. */
class DashboardTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private Department $design;

    private User $manager;

    private User $leader;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment('Marketing');
        $this->design = $this->makeDepartment('Design');
        $this->manager = $this->makeManager();
        $this->leader = $this->makeTeamLeader($this->marketing);
        $this->employee = $this->makeEmployee($this->marketing);
    }

    public function test_the_employee_sees_the_employee_dashboard_with_correct_current_count(): void
    {
        $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $response = $this->actingAs($this->employee)->get(route('dashboard'));

        $response->assertOk()->assertViewIs('dashboard.employee');
        $response->assertViewHas('current', 1);
    }

    public function test_the_tl_sees_the_tl_dashboard_with_department_scoped_counts(): void
    {
        $this->newTask($this->marketing, $this->manager);

        $response = $this->actingAs($this->leader)->get(route('dashboard'));

        $response->assertOk()->assertViewIs('dashboard.tl');
        $response->assertViewHas('waitingAssignment', 1);
    }

    public function test_the_manager_sees_the_manager_dashboard(): void
    {
        $this->newTask($this->marketing, $this->manager);

        $response = $this->actingAs($this->manager)->get(route('dashboard'));

        $response->assertOk()->assertViewIs('dashboard.manager');
        $response->assertViewHas('activeTasks', 1);
    }

    /** BRD §16.3 — a redirected step still waiting for the receiving TL to assign it. */
    public function test_the_manager_dashboard_lists_redirected_steps_awaiting_assignment(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $this->workflow()->redirect($task, $this->manager, $this->design, 'Wrong department, correcting');

        $response = $this->actingAs($this->manager)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('redirectedAwaitingAssignment', fn ($steps) => $steps->count() === 1
            && $steps->first()->department_id === $this->design->id);
    }

    /** BRD §16.2 — the TL sees whether the assignee has opened each of their tasks yet. */
    public function test_the_tl_dashboard_shows_whether_each_assignment_was_opened(): void
    {
        $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $before = $this->actingAs($this->leader)->get(route('dashboard'));
        $before->assertViewHas('tasksByEmployee', function ($byEmployee) {
            return $byEmployee->flatten()->every(fn ($a) => $a->first_seen_at === null);
        });

        // The real page an employee lands on via the "My tasks" sidebar link — NOT
        // tasks.mine, which is a JSON endpoint nothing in the UI actually calls.
        $this->actingAs($this->employee)->get('/tasks');

        $after = $this->actingAs($this->leader)->get(route('dashboard'));
        $after->assertViewHas('tasksByEmployee', function ($byEmployee) {
            return $byEmployee->flatten()->every(fn ($a) => $a->first_seen_at !== null);
        });
    }

    public function test_the_admin_keeps_the_generic_dashboard(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()->assertViewIs('dashboard');
    }

    /**
     * BRD §15 still stands: Admin gets no task/project CONTENT. The later product
     * decision only adds aggregate counts per department — this proves both halves at
     * once, real counts by department AND no titles/links leaking through.
     */
    public function test_the_admin_dashboard_shows_department_counts_but_no_task_content(): void
    {
        $admin = $this->makeAdmin();
        $this->newTask($this->marketing, $this->manager); // waiting assignment
        $this->taskInProgress($this->design, $this->makeTeamLeader($this->design), $this->makeEmployee($this->design));
        [$reviewTask] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()->assertViewIs('dashboard');
        $response->assertViewHas('departmentSummaries', function ($summaries) {
            $marketing = collect($summaries)->firstWhere('name', 'Marketing');
            $design = collect($summaries)->firstWhere('name', 'Design');

            return $marketing['waitingAssignment'] === 1
                && $marketing['underReview'] === 1
                && $marketing['headcount'] === 2 // the TL + employee made in setUp()
                && $design['inProgress'] === 1;
        });

        // No task title, brief, or link ever reaches this page.
        $response->assertDontSee($reviewTask->title);
        $response->assertDontSee(route('tasks.show', $reviewTask));

        // The headline row is just the same per-department data, summed.
        $response->assertViewHas('totals', fn ($totals) => $totals['waitingAssignment'] === 1
            && $totals['inProgress'] === 1
            && $totals['underReview'] === 1);
    }

    /**
     * A project can be linked to several departments at once — the org-wide headline
     * total must count it once, not once per department it happens to touch.
     */
    public function test_the_admin_dashboard_active_projects_total_does_not_double_count_a_multi_department_project(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create(['status' => 'active']);
        $this->addDepartmentToProject($project, $this->marketing);
        $this->addDepartmentToProject($project, $this->design);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('totals', fn ($totals) => $totals['activeProjects'] === 1);
        $response->assertViewHas('departmentSummaries', function ($summaries) {
            $marketing = collect($summaries)->firstWhere('name', 'Marketing');
            $design = collect($summaries)->firstWhere('name', 'Design');

            // The per-department breakdown legitimately counts it in both rows —
            // only the summed org-wide total must not add those two together.
            return $marketing['activeProjects'] === 1 && $design['activeProjects'] === 1;
        });
    }

    /** Open-but-not-overdue only → nothing overdue yet, score reads a clean 100 / healthy. */
    public function test_the_health_score_is_100_with_nothing_overdue(): void
    {
        $admin = $this->makeAdmin();
        $this->newTask($this->marketing, $this->manager);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertViewHas('healthScore', 100);
        $response->assertViewHas('healthBand', 'success');
    }

    /** Every open step overdue → the worst possible ratio, banded as at-risk. */
    public function test_the_health_score_drops_into_the_danger_band_when_everything_is_overdue(): void
    {
        $admin = $this->makeAdmin();
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $step->forceFill(['deadline_status' => DeadlineStatus::Overdue->value])->save();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertViewHas('healthScore', 0);
        $response->assertViewHas('healthBand', 'danger');
    }

    /** 14 days of [date, count] pairs, today's task included, older ones excluded. */
    public function test_the_admin_activity_series_covers_the_last_14_days_and_counts_todays_task(): void
    {
        $admin = $this->makeAdmin();
        $this->newTask($this->marketing, $this->manager);
        Task::factory()->create(['created_at' => now()->subDays(20)]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertViewHas('activitySeries', function ($series) {
            $today = collect($series)->last();

            return count($series) === 14
                && $today['date']->isToday()
                && $today['count'] === 1;
        });
    }

    /** A real week-over-week comparison on new-task creation, not a fabricated number. */
    public function test_the_manager_dashboard_shows_a_real_week_over_week_new_task_trend(): void
    {
        Task::factory()->count(2)->create(['created_at' => now()->subDays(10)]);
        $this->newTask($this->marketing, $this->manager);

        $response = $this->actingAs($this->manager)->get(route('dashboard'));

        $response->assertViewHas('newTasksTrend', fn ($trend) => $trend['current'] === 1
            && $trend['previous'] === 2
            && $trend['direction'] === 'down');
    }

    /** The Admin's "Recent Activity" widget is the real, existing audit log — not a stub. */
    public function test_the_admin_dashboard_recent_activity_is_the_real_audit_log(): void
    {
        $admin = $this->makeAdmin();
        $this->newTask($this->marketing, $this->manager);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertViewHas('recentActivity', fn ($logs) => $logs->isNotEmpty()
            && $logs->contains(fn ($log) => $log->action === 'task.created'));
        $response->assertViewHas('newestTeamMembers', fn ($members) => $members->contains($this->leader));
    }

    /** Manager's donut is a real grouped count, and the two new list widgets are real rows. */
    public function test_the_manager_dashboard_status_distribution_and_widgets_are_real(): void
    {
        $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $task = $this->newTask($this->marketing, $this->manager);
        $this->workflow()->redirect($task, $this->manager, $this->design, 'Wrong department, correcting');

        $response = $this->actingAs($this->manager)->get(route('dashboard'));

        $response->assertViewHas('statusSegments', function ($segments) {
            $inProgress = collect($segments)->firstWhere('label', __('agencyos.dashboard_admin.column_in_progress'));

            return $inProgress['count'] === 1;
        });
        $response->assertViewHas('recentTransfers', fn ($transfers) => $transfers->count() === 1
            && $transfers->first()->toDepartment->id === $this->design->id);
    }

    /** TL's chart/donut/trend are scoped to their OWN department, never the other one. */
    public function test_the_tl_dashboard_widgets_are_scoped_to_their_own_department(): void
    {
        $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $this->taskInProgress($this->design, $this->makeTeamLeader($this->design), $this->makeEmployee($this->design));

        $marketingEvents = TaskStatusHistory::where('department_id', $this->marketing->id)->count();
        $this->assertGreaterThan(0, $marketingEvents, 'the scenario must actually generate marketing-department events');

        $response = $this->actingAs($this->leader)->get(route('dashboard'));

        $response->assertViewHas('activitySeries', fn ($series) => collect($series)->sum('count') === $marketingEvents);
        $response->assertViewHas('statusSegments', function ($segments) {
            $inProgress = collect($segments)->firstWhere('label', __('agencyos.dashboard_admin.column_in_progress'));

            return $inProgress['count'] === 1;
        });
    }

    /** Employee's donut/deadlines are free re-shapes of $openAssignments — no new query. */
    public function test_the_employee_dashboard_status_and_deadlines_reflect_only_their_own_work(): void
    {
        [$task] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $task->currentStep->forceFill(['current_due_at' => now()->addDays(3)])->save();

        $response = $this->actingAs($this->employee)->get(route('dashboard'));

        $response->assertViewHas('statusSegments', function ($segments) {
            $inProgress = collect($segments)->firstWhere('label', __('agencyos.dashboard_admin.column_in_progress'));

            return $inProgress['count'] === 1;
        });
        $response->assertViewHas('upcomingDeadlines', fn ($deadlines) => $deadlines->count() === 1);
    }
}
