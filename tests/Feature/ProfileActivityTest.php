<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * The profile page's optional {user} parameter — same access boundary as
 * UserPolicy::viewPerformance() (see PerformanceUiTest), reused here so a Team Leader
 * or Manager can browse a colleague's task activity from their profile page.
 */
class ProfileActivityTest extends TestCase
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

    public function test_a_user_can_always_view_their_own_activity_tab(): void
    {
        $this->actingAs($this->employee)->get(route('profile.edit', ['tab' => 'activity']))
            ->assertOk()->assertViewIs('profile.edit');
    }

    public function test_a_manager_can_view_anyones_activity(): void
    {
        $this->actingAs($this->manager)->get(route('profile.edit', $this->employee))
            ->assertOk();
    }

    public function test_a_tl_can_view_a_same_department_employee(): void
    {
        $this->actingAs($this->leader)->get(route('profile.edit', $this->employee))
            ->assertOk();
    }

    public function test_a_tl_cannot_view_a_different_departments_employee(): void
    {
        $outsider = $this->makeEmployee($this->design);

        $this->actingAs($this->leader)->get(route('profile.edit', $outsider))
            ->assertForbidden();
    }

    public function test_an_employee_cannot_view_someone_elses_activity(): void
    {
        $other = $this->makeEmployee($this->marketing);

        $this->actingAs($this->employee)->get(route('profile.edit', $other))
            ->assertForbidden();
    }

    /** Editing someone else's account is UserController's job, never this page. */
    public function test_viewing_someone_elses_profile_never_offers_the_account_tab(): void
    {
        $response = $this->actingAs($this->manager)->get(route('profile.edit', $this->employee));

        $response->assertOk();
        $response->assertDontSee('name="personal_email"', false);
        $response->assertDontSee('id="avatarForm"', false);
    }

    public function test_a_created_task_and_an_assigned_task_both_appear_in_the_activity_tab(): void
    {
        $createdTask = $this->newTask($this->marketing, $this->manager);
        [$assignedTask] = $this->taskInProgress($this->marketing, $this->leader, $this->employee, creator: $this->manager);

        $response = $this->actingAs($this->manager)->get(route('profile.edit', ['tab' => 'activity']));

        $response->assertOk();
        $response->assertSee($createdTask->title);
        $response->assertSee($assignedTask->title);
    }

    /** The branded letterhead is the whole point of the print button — it must render,
     *  screen-hidden, so it is there for @media print to reveal. */
    public function test_the_activity_tab_offers_a_print_button_with_a_branded_letterhead(): void
    {
        $response = $this->actingAs($this->employee)->get(route('profile.edit', ['tab' => 'activity']));

        $response->assertOk();
        $response->assertSee('onclick="window.print()"', false);
        $response->assertSee('print-letterhead', false);
        $response->assertSee(__('agencyos.profile.activity.print_title'));
    }

    /** "Activity on :date" means task_status_history activity that day, not merely that
     *  the task exists — matches DepartmentReportService::buildAutoSummary()'s convention. */
    public function test_the_date_filter_only_shows_tasks_with_activity_on_that_day(): void
    {
        $recentTask = $this->newTask($this->marketing, $this->manager);
        $oldTask = $this->newTask($this->marketing, $this->manager, ['title' => 'Old campaign asset']);
        $oldTask->history()->update(['created_at' => now()->subDays(5)]);

        $filtered = $this->actingAs($this->manager)->get(
            route('profile.edit', ['tab' => 'activity', 'date' => now()->subDays(5)->toDateString()])
        );
        $filtered->assertOk();
        $filtered->assertSee($oldTask->title);
        $filtered->assertDontSee($recentTask->title);

        $unfiltered = $this->actingAs($this->manager)->get(route('profile.edit', ['tab' => 'activity']));
        $unfiltered->assertOk();
        $unfiltered->assertSee($oldTask->title);
        $unfiltered->assertSee($recentTask->title);
    }

    public function test_an_invalid_date_filter_is_silently_ignored(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $response = $this->actingAs($this->manager)->get(
            route('profile.edit', ['tab' => 'activity', 'date' => 'not-a-date'])
        );

        $response->assertOk();
        $response->assertSee($task->title);
    }
}
