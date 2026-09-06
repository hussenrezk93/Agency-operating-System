<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * Locks in the N+1 fixes from the performance-optimization pass: the query count for
 * each of these pages must stay FLAT as the number of rows grows, never scale linearly
 * with it. A regression here means a `->with(...)` eager-load was dropped.
 *
 * Every test authenticates ONCE (`actingAs`) and fires one throwaway "warm up" request
 * before measuring either side — a session's very first request pays a one-off
 * session-bootstrap query that later requests on the same session don't, which is noise
 * unrelated to N+1 and would otherwise make these assertions flaky.
 */
class PerformanceRegressionTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function queryCountFor(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_task_list_query_count_does_not_grow_with_the_number_of_tasks(): void
    {
        $department = $this->makeDepartment('Marketing');
        $manager = $this->makeManager();
        $leader = $this->makeTeamLeader($department);
        $employee = $this->makeEmployee($department);
        $this->actingAs($manager);

        $this->taskInProgress($department, $leader, $employee);
        $this->taskInProgress($department, $leader, $employee);

        $this->get('/tasks')->assertOk();
        $small = $this->queryCountFor(fn () => $this->get('/tasks')->assertOk());

        for ($i = 0; $i < 5; $i++) {
            $this->taskInProgress($department, $leader, $employee);
        }

        $large = $this->queryCountFor(fn () => $this->get('/tasks')->assertOk());

        $this->assertSame($small, $large, 'the task list must not fire one extra query per task row');
    }

    public function test_the_task_detail_page_query_count_does_not_grow_with_history_entries(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $employee = $this->makeEmployee($department);
        $manager = $this->systemManager();
        $this->actingAs($manager);

        [$task, $step] = $this->taskUnderReview($department, $leader, $employee);
        $this->get("/tasks/{$task->id}")->assertOk();
        $small = $this->queryCountFor(fn () => $this->get("/tasks/{$task->id}")->assertOk());

        // Two more changes-requested/resubmit rounds — more task_status_history rows on
        // the SAME single step, isolating the history.changedBy fix from step count.
        for ($i = 0; $i < 2; $i++) {
            $this->workflow()->requestChanges($step->refresh(), $leader, 'Please fix this');
            $this->workflow()->addOutput($step->refresh(), $employee, "https://drive.example.com/out-{$i}");
            $this->workflow()->submit($step->refresh(), $employee);
        }

        $large = $this->queryCountFor(fn () => $this->get("/tasks/{$task->id}")->assertOk());

        $this->assertSame($small, $large, 'the task detail page must not fire one extra query per history row');
    }

    /**
     * Transferring to a second department doubles the route rail (`steps`) and
     * legitimately adds ONE bounded query for the §15 output-visibility lookup
     * (`Task::approvedOutputsBefore()` — it must know which earlier step(s) exist,
     * unrelated to this optimization pass). A real per-step N+1 regression in
     * `steps.department`/`steps.activeAssignment.assignee` would add ~3 queries for the
     * new step, well past this tolerance.
     */
    public function test_the_task_detail_page_does_not_fire_a_query_per_step_for_department_or_assignee(): void
    {
        $marketing = $this->makeDepartment('Marketing');
        $design = $this->makeDepartment('Design');
        $this->allowRoute($marketing, $design);
        $leader1 = $this->makeTeamLeader($marketing);
        $employee1 = $this->makeEmployee($marketing);
        $leader2 = $this->makeTeamLeader($design);
        $employee2 = $this->makeEmployee($design);
        $manager = $this->systemManager();
        $this->actingAs($manager);

        [$smallTask] = $this->taskApproved($marketing, $leader1, $employee1);
        $this->get("/tasks/{$smallTask->id}")->assertOk();
        $small = $this->queryCountFor(fn () => $this->get("/tasks/{$smallTask->id}")->assertOk());

        [$largeTask, $step] = $this->taskApproved($marketing, $leader1, $employee1);
        $next = $this->workflow()->sendToNextDepartment($step, $leader1, $design);
        $this->workflow()->assign($next, $leader2, $employee2, now()->toDateString(), now()->addDays(3)->toDateString());
        $this->workflow()->addOutput($next->refresh(), $employee2, 'https://drive.example.com/design-out');
        $this->workflow()->submit($next->refresh(), $employee2);
        $this->workflow()->approve($next->refresh(), $leader2);
        $this->workflow()->approve($next->refresh(), $manager);

        $large = $this->queryCountFor(fn () => $this->get("/tasks/{$largeTask->id}")->assertOk());

        $this->assertLessThanOrEqual($small + 1, $large, 'a second step must not add a whole extra round of department/assignee queries');
    }

    public function test_the_employee_dashboard_query_count_does_not_grow_with_open_assignments(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $employee = $this->makeEmployee($department);
        $this->actingAs($employee);

        $this->taskInProgress($department, $leader, $employee);
        $this->get('/dashboard')->assertOk();
        $small = $this->queryCountFor(fn () => $this->get('/dashboard')->assertOk());

        for ($i = 0; $i < 4; $i++) {
            $this->taskInProgress($department, $leader, $employee);
        }

        $large = $this->queryCountFor(fn () => $this->get('/dashboard')->assertOk());

        $this->assertSame($small, $large, 'the employee dashboard must not fire one extra query per open assignment');
    }

    public function test_the_department_list_query_count_does_not_grow_with_the_number_of_departments(): void
    {
        $manager = $this->makeManager();
        $this->makeTeamLeader($this->makeDepartment('Marketing'));
        $this->makeTeamLeader($this->makeDepartment('Design'));
        $this->actingAs($manager);

        $this->get('/departments')->assertOk();
        $small = $this->queryCountFor(fn () => $this->get('/departments')->assertOk());

        foreach (['Sales', 'Support', 'Engineering'] as $name) {
            $this->makeTeamLeader($this->makeDepartment($name));
        }

        $large = $this->queryCountFor(fn () => $this->get('/departments')->assertOk());

        $this->assertSame($small, $large, 'the department list must not fire one extra query per department row');
    }

    public function test_chat_all_tls_resolution_query_count_does_not_grow_with_department_count(): void
    {
        $manager = $this->makeManager();
        $this->makeTeamLeader($this->makeDepartment('Marketing'));
        $this->makeTeamLeader($this->makeDepartment('Design'));
        $this->actingAs($manager);

        // Warm up twice: once for the session-bootstrap query, once more because the
        // very first resolve of the ManagerTls singleton INSERTs its members, and that
        // write count depends on how many are new — not what this test is about. Measure
        // only once every current TL is already a synced member, so the read path (the
        // thing the eager-load fix touches) is what's being compared.
        $this->get('/chat')->assertOk();
        $this->get('/chat')->assertOk();
        $small = $this->queryCountFor(fn () => $this->get('/chat')->assertOk());

        foreach (['Sales', 'Support', 'Engineering'] as $name) {
            $this->makeTeamLeader($this->makeDepartment($name));
        }

        $this->get('/chat')->assertOk();
        $large = $this->queryCountFor(fn () => $this->get('/chat')->assertOk());

        $this->assertSame($small, $large, 'resolving the all-TLs/manager-TLs chat sidebar must not fire one extra query per department');
    }
}
