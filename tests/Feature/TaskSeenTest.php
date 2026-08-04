<?php

namespace Tests\Feature;

use App\Enums\TaskEvent;
use App\Models\Department;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * PHASE 1B SLICE 2 — automatic Seen (approved decision Q19, change request CR-006).
 *
 * Time is controlled with Carbon::setTestNow throughout, never the real clock, so
 * "the second open did not move the timestamp" is a real assertion rather than an
 * accident of two calls landing in the same second.
 */
class TaskSeenTest extends TestCase
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

        $this->marketing = $this->makeDepartment('Marketing');
        $this->manager = $this->makeManager();
        $this->leader = $this->makeTeamLeader($this->marketing);
        $this->employee = $this->makeEmployee($this->marketing);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_first_open_writes_the_timestamp(): void
    {
        [, $step, $assignment] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->assertNull($assignment->first_seen_at);

        Carbon::setTestNow('2026-08-03 09:15:00');
        $written = $this->workflow()->markSeen($step, $this->employee);

        $this->assertTrue($written);
        $this->assertNotNull($assignment->refresh()->first_seen_at);
        $this->assertSame('2026-08-03 09:15:00', $assignment->first_seen_at->format('Y-m-d H:i:s'));
    }

    /** Q19 — written once; reopening never overwrites it. */
    public function test_a_second_open_does_not_change_the_timestamp(): void
    {
        [, $step, $assignment] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        Carbon::setTestNow('2026-08-03 09:15:00');
        $this->workflow()->markSeen($step, $this->employee);
        $first = $assignment->refresh()->first_seen_at;

        Carbon::setTestNow('2026-08-04 17:40:00');
        $written = $this->workflow()->markSeen($step->refresh(), $this->employee);

        $this->assertFalse($written, 'the second call must not write');
        $this->assertTrue($first->equalTo($assignment->refresh()->first_seen_at));
    }

    public function test_the_event_is_recorded_exactly_once(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->workflow()->markSeen($step, $this->employee);
        $this->workflow()->markSeen($step->refresh(), $this->employee);
        $this->workflow()->markSeen($step->refresh(), $this->employee);

        $this->assertSame(1, TaskStatusHistory::query()
            ->where('task_id', $task->id)
            ->where('event_type', TaskEvent::FirstSeen->value)
            ->count());
    }

    public function test_somebody_who_is_not_the_assignee_cannot_mark_a_step_seen(): void
    {
        [, $step, $assignment] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $colleague = $this->makeEmployee($this->marketing);

        $this->assertFalse($colleague->can('markSeen', $step));
        $this->assertFalse($this->leader->can('markSeen', $step));

        try {
            $this->workflow()->markSeen($step, $colleague);
            $this->fail('a colleague must not be able to mark the step seen');
        } catch (AuthorizationException) {
            // expected
        }

        $this->assertNull($assignment->refresh()->first_seen_at);
    }

    // ------------------------------------------------------- CR-006 My Tasks sweep

    /** CR-006 — opening My Tasks is what marks the caller's own unseen steps. */
    public function test_opening_my_tasks_marks_every_eligible_unseen_step(): void
    {
        [, $stepA, $assignmentA] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        [, $stepB, $assignmentB] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $written = $this->workflow()->markMyTasksSeen($this->employee);

        $this->assertSame(2, $written);
        $this->assertNotNull($assignmentA->refresh()->first_seen_at);
        $this->assertNotNull($assignmentB->refresh()->first_seen_at);
        $this->assertNotNull($stepA->refresh()->activeAssignment->first_seen_at);
        $this->assertNotNull($stepB->refresh()->activeAssignment->first_seen_at);
    }

    public function test_the_sweep_is_idempotent(): void
    {
        $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->assertSame(1, $this->workflow()->markMyTasksSeen($this->employee));
        $this->assertSame(0, $this->workflow()->markMyTasksSeen($this->employee));
    }

    /** Q19 — another user's assignment is never touched. */
    public function test_the_sweep_never_touches_another_users_assignment(): void
    {
        [, , $mine] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $colleague = $this->makeEmployee($this->marketing);
        [, , $theirs] = $this->taskInProgress($this->marketing, $this->leader, $colleague);

        $this->workflow()->markMyTasksSeen($this->employee);

        $this->assertNotNull($mine->refresh()->first_seen_at);
        $this->assertNull($theirs->refresh()->first_seen_at, "the colleague's step must be untouched");
    }

    /** Q19 — closed tasks are ignored by the sweep. */
    public function test_the_sweep_ignores_steps_of_a_cancelled_task(): void
    {
        [$task, , $assignment] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $this->workflow()->cancelTask($task, $this->manager, 'Client withdrew the request.');

        $this->assertSame(0, $this->workflow()->markMyTasksSeen($this->employee));
        $this->assertNull($assignment->refresh()->first_seen_at);
    }

    public function test_the_sweep_ignores_a_step_that_has_moved_past_the_assignee(): void
    {
        [, $step, $assignment] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);
        $this->workflow()->approve($step, $this->leader);

        // Approval closed the assignment, so there is nothing live left to mark.
        $this->assertSame(0, $this->workflow()->markMyTasksSeen($this->employee));
        $this->assertNull($assignment->refresh()->first_seen_at);
    }

    /** The endpoint that carries the sweep, and Q20's absence from its payload. */
    public function test_the_my_tasks_endpoint_marks_seen_and_never_returns_the_timestamp(): void
    {
        [, , $assignment] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $response = $this->actingAs($this->employee)
            ->getJson(route('tasks.mine'))
            ->assertOk()
            ->assertJsonPath('newly_seen', 1)
            ->assertJsonCount(1, 'tasks');

        $this->assertNotNull($assignment->refresh()->first_seen_at);
        $this->assertStringNotContainsString(
            'first_seen',
            $response->getContent(),
            'Q20 — the employee must never be shown their own first_seen_at',
        );
    }

    /** Q20 — the Team Leader is the one who reads it. */
    public function test_the_team_leader_can_read_the_timestamp_through_its_own_endpoint(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        Carbon::setTestNow('2026-08-05 11:00:00');
        $this->workflow()->markSeen($step, $this->employee);

        $this->actingAs($this->leader)
            ->getJson(route('tasks.steps.first-seen', $step))
            ->assertOk()
            ->assertJsonPath('first_seen_at', fn ($value): bool => $value !== null);
    }
}
