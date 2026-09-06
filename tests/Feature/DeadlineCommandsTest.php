<?php

namespace Tests\Feature;

use App\Enums\DeadlineStatus;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * Phase 0 Technical Plan §7 — the two scheduled commands, each driving
 * DeadlineService::sweepLiveSteps() end to end through `Artisan::call()`.
 */
class DeadlineCommandsTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private User $manager;

    private User $leader;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        // Frozen before makeTeamLeader() runs, not after: that helper backdates the
        // leadership assignment's start_date to `now()->subMonth()` — if "now" were
        // still the real clock at that point, the assignment could start AFTER the
        // 2026-08-01 the tests below freeze to (once real time passes 2026-09-01),
        // making canActAsLeaderOf() false and every assign() in this file 403.
        Carbon::setTestNow(Carbon::parse('2026-08-01 09:00:00', config('app.timezone')));

        $this->marketing = $this->makeDepartment();
        $this->manager = $this->makeManager();
        $this->leader = $this->makeTeamLeader($this->marketing);
        $this->employee = $this->makeEmployee($this->marketing);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_deadlines_due_soon_moves_a_step_that_just_entered_the_window(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        // due at 2026-08-04 23:59 — move "now" to inside the 24h window.
        Carbon::setTestNow($step->current_due_at->clone()->subHours(23));

        $this->artisan('agencyos:deadlines-due-soon')->assertExitCode(0);

        $this->assertSame(DeadlineStatus::DueSoon, $step->fresh()->deadline_status);
    }

    public function test_deadlines_overdue_moves_a_step_past_its_due_date(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        Carbon::setTestNow($step->current_due_at->clone()->addDay());

        $this->artisan('agencyos:deadlines-overdue')->assertExitCode(0);

        $this->assertSame(DeadlineStatus::Overdue, $step->fresh()->deadline_status);
    }

    public function test_the_sweep_never_touches_a_held_task(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $this->workflow()->hold($task, $this->manager, 'Pausing');

        Carbon::setTestNow($step->current_due_at->clone()->addDay());

        $this->artisan('agencyos:deadlines-overdue')->assertExitCode(0);

        $this->assertSame(DeadlineStatus::Paused, $step->fresh()->deadline_status);
    }
}
