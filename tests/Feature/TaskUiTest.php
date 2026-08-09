<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * Phase 5 — the real Blade screens. Every action here reuses the same
 * TaskWorkflowService/policies the JSON API (TaskWorkflowTest/TaskAuthorizationTest)
 * already proves; this file proves the CLASSIC-BROWSER path around that engine:
 * a plain `->get()`/`->post()` (no Accept header) renders a view or redirects back,
 * it never dumps raw JSON.
 */
class TaskUiTest extends TestCase
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

    public function test_the_task_list_renders_for_every_role(): void
    {
        $this->newTask($this->marketing, $this->manager);

        foreach ([$this->manager, $this->leader, $this->employee] as $actor) {
            $this->actingAs($actor)->get('/tasks')->assertOk()->assertViewIs('tasks.index');
        }
    }

    /** BRD §8/§16 — Urgent-priority tasks sort to the top of the main task list. */
    public function test_urgent_tasks_sort_to_the_top_of_the_task_list(): void
    {
        $normal = $this->newTask($this->marketing, $this->manager, ['title' => 'Normal task']);
        $urgent = $this->newTask($this->marketing, $this->manager, ['title' => 'Urgent task', 'priority' => 'urgent']);

        $response = $this->actingAs($this->manager)->get('/tasks');

        $response->assertOk();
        $tasks = $response->viewData('tasks');
        $this->assertSame($urgent->id, $tasks->first()->id);
        $this->assertTrue($tasks->search(fn ($t) => $t->id === $normal->id) > $tasks->search(fn ($t) => $t->id === $urgent->id));
    }

    public function test_an_employee_only_sees_tasks_they_are_assigned_to(): void
    {
        [$assignedTask] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $otherTask = $this->newTask($this->marketing, $this->manager);

        $response = $this->actingAs($this->employee)->get('/tasks');

        $response->assertSee($assignedTask->task_code);
        $response->assertDontSee($otherTask->task_code);
    }

    /**
     * The sidebar's "My tasks" nav item for TL links to /tasks?view=my — by default a
     * TL sees every task with a step in their department (like the plain "Tasks" item),
     * so this proves the `view=my` query actually narrows that down to only tasks the
     * TL has self-assigned. Merely having created a task (without self-assigning any
     * step) does NOT count as "mine" — creating on behalf of the department and
     * personally doing the work are different things.
     */
    public function test_a_team_leader_can_filter_the_task_list_to_only_their_own(): void
    {
        [$ownTask] = $this->taskInProgress($this->marketing, $this->leader, $this->leader);
        [$createdNotAssigned] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $otherTask = $this->newTask($this->marketing, $this->manager);

        $unfiltered = $this->actingAs($this->leader)->get('/tasks');
        $unfiltered->assertSee(__('agencyos.tasks.index.title'));
        $unfiltered->assertSee($ownTask->task_code);
        $unfiltered->assertSee($createdNotAssigned->task_code);
        $unfiltered->assertSee($otherTask->task_code);

        $filtered = $this->actingAs($this->leader)->get('/tasks?view=my');
        $filtered->assertSee(__('agencyos.tasks.index.title_my'));
        $filtered->assertSee($ownTask->task_code);
        $filtered->assertDontSee($createdNotAssigned->task_code);
        $filtered->assertDontSee($otherTask->task_code);
    }

    /**
     * The sidebar's "Review queue" item for TL used to open a disconnected prototype
     * screen (tl-review.html, fake data, fake user) instead of anything real. It now
     * links to the real Tasks list pre-filtered to Under Review — this proves both the
     * link itself and that the filter actually narrows to only what's awaiting review.
     */
    public function test_the_sidebar_review_queue_link_opens_the_real_under_review_tasks(): void
    {
        [$underReviewTask, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/out');
        $this->workflow()->submit($step, $this->employee);
        $inProgressTask = $this->newTask($this->marketing, $this->manager);

        $dashboard = $this->actingAs($this->leader)->get(route('dashboard'));
        $dashboard->assertSee(route('tasks.index', ['status' => 'under_review']), false);

        $reviewQueue = $this->actingAs($this->leader)->get('/tasks?status=under_review');
        $reviewQueue->assertOk();
        $reviewQueue->assertSee($underReviewTask->task_code);
        $reviewQueue->assertDontSee($inProgressTask->task_code);
    }

    public function test_the_create_form_renders_for_manager_and_team_leader_but_not_employee(): void
    {
        $this->actingAs($this->manager)->get('/tasks/create')->assertOk()->assertViewIs('tasks.create');
        $this->actingAs($this->leader)->get('/tasks/create')->assertOk()->assertViewIs('tasks.create');
        $this->actingAs($this->employee)->get('/tasks/create')->assertForbidden();
    }

    public function test_a_classic_form_post_creates_a_task_and_redirects_to_its_page(): void
    {
        $response = $this->actingAs($this->manager)->post('/tasks', [
            'title' => 'Classic form task',
            'brief' => 'Created through the real Blade form.',
            'first_department_id' => $this->marketing->id,
            // The create form always posts 3 rows; only the first is filled in here.
            'reference_links' => [
                ['url' => 'https://drive.example.com/brief', 'label' => 'Brief'],
                ['url' => '', 'label' => ''],
                ['url' => '', 'label' => ''],
            ],
        ]);

        $task = Task::where('title', 'Classic form task')->firstOrFail();

        $response->assertRedirect(route('tasks.show', $task));
        $this->assertSame($this->marketing->id, $task->currentStep->department_id);
    }

    /** Reference links are optional — a task may be created and published with none at all. */
    public function test_creating_a_task_with_no_reference_links_succeeds(): void
    {
        $response = $this->actingAs($this->manager)->post('/tasks', [
            'title' => 'Linkless task',
            'brief' => 'No reference links attached.',
            'first_department_id' => $this->marketing->id,
            'reference_links' => [
                ['url' => '', 'label' => ''],
                ['url' => '', 'label' => ''],
                ['url' => '', 'label' => ''],
            ],
        ]);

        $task = Task::where('title', 'Linkless task')->firstOrFail();
        $response->assertRedirect(route('tasks.show', $task));
        $this->assertSame(0, $task->referenceLinks()->count());
    }

    /** BRD §11 — editing the deadline alone (same assignee) needs no reason. */
    public function test_reassigning_the_same_employee_needs_no_reason(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $response = $this->actingAs($this->leader)->post(route('tasks.steps.reassign', $step), [
            'assignee_id' => $this->employee->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertSessionDoesntHaveErrors('reason');
        $this->assertDatabaseHas('task_status_history', [
            'task_step_id' => $step->id,
            'reason' => __('agencyos.tasks.actions.deadline_only_reason'),
        ]);
    }

    /** BRD §11 — replacing the employee still requires a reason. */
    public function test_reassigning_to_a_different_employee_requires_a_reason(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $otherEmployee = $this->makeEmployee($this->marketing);

        $this->actingAs($this->leader)->post(route('tasks.steps.reassign', $step), [
            'assignee_id' => $otherEmployee->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
        ])->assertSessionHasErrors('reason');
    }

    public function test_the_task_detail_page_renders_with_the_right_actions_per_role(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        // The assignee (Employee) may add outputs and submit, not review.
        $asEmployee = $this->actingAs($this->employee)->get(route('tasks.show', $task));
        $asEmployee->assertOk()->assertViewIs('tasks.show');
        $asEmployee->assertViewHas('canAddOutput', true);
        $asEmployee->assertViewHas('canSubmit', true);
        $asEmployee->assertViewHas('canReview', false);

        // The department's Team Leader may not add outputs, but reviews once submitted.
        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/out');
        $this->workflow()->submit($step, $this->employee);

        $asLeader = $this->actingAs($this->leader)->get(route('tasks.show', $task));
        $asLeader->assertOk();
        $asLeader->assertViewHas('canReview', true);
        $asLeader->assertViewHas('canAddOutput', false);
    }

    /**
     * TaskWorkflowService::sendToNextDepartment()/completeTask() both reject a step that
     * isn't already Approved (422) — the "Send to next department" / "Finish task"
     * controls must not render next to Approve/Request changes while a step still
     * awaits review, only once it has actually been approved.
     */
    public function test_transfer_and_finish_only_appear_once_the_step_is_approved(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/out');
        $this->workflow()->submit($step, $this->employee);

        $beforeApproval = $this->actingAs($this->leader)->get(route('tasks.show', $task));
        $beforeApproval->assertSee(__('agencyos.tasks.actions.approve'));
        $beforeApproval->assertDontSee(__('agencyos.tasks.actions.transfer'));
        $beforeApproval->assertDontSee(__('agencyos.tasks.actions.finish'));

        $this->workflow()->approve($step, $this->leader);

        $afterApproval = $this->actingAs($this->leader)->get(route('tasks.show', $task));
        $afterApproval->assertSee(__('agencyos.tasks.actions.transfer'));
        $afterApproval->assertSee(__('agencyos.tasks.actions.finish'));
    }

    /**
     * TaskStepPolicy::review() is deliberately state-agnostic (state belongs to the
     * service) — approve()/requestChanges() both require Under Review (422 otherwise),
     * so Approve/Request changes must not render before the assignee has submitted,
     * even though the TL is already authorized to review in principle.
     */
    public function test_approve_and_request_changes_do_not_appear_before_a_submission(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $step = $task->currentStep;

        $waitingAssignment = $this->actingAs($this->leader)->get(route('tasks.show', $task));
        $waitingAssignment->assertDontSee(__('agencyos.tasks.actions.approve'));
        $waitingAssignment->assertDontSee(__('agencyos.tasks.actions.request_changes'));

        $this->workflow()->assign($step, $this->leader, $this->employee, now()->toDateString(), now()->addDays(3)->toDateString());

        $inProgress = $this->actingAs($this->leader)->get(route('tasks.show', $task));
        $inProgress->assertDontSee(__('agencyos.tasks.actions.approve'));
        $inProgress->assertDontSee(__('agencyos.tasks.actions.request_changes'));

        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/out');
        $this->workflow()->submit($step, $this->employee);

        $underReview = $this->actingAs($this->leader)->get(route('tasks.show', $task));
        $underReview->assertSee(__('agencyos.tasks.actions.approve'));
        $underReview->assertSee(__('agencyos.tasks.actions.request_changes'));
    }

    /**
     * addOutput()/submit() both require In Progress or Changes Requested (422
     * otherwise). submit() does NOT end the assignment — only approve() does — so the
     * employee stays the step's "current assignee" straight through Under Review too.
     * "Add output"/"Submit" must not still render once they've already submitted.
     */
    public function test_add_output_and_submit_do_not_appear_once_already_submitted(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/out');

        $inProgress = $this->actingAs($this->employee)->get(route('tasks.show', $task));
        $inProgress->assertSee(__('agencyos.tasks.actions.add_output_button'));
        $inProgress->assertSee(__('agencyos.tasks.actions.submit_work'));

        $this->workflow()->submit($step, $this->employee);

        $underReview = $this->actingAs($this->employee)->get(route('tasks.show', $task));
        $underReview->assertDontSee(__('agencyos.tasks.actions.add_output_button'));
        $underReview->assertDontSee(__('agencyos.tasks.actions.submit_work'));
    }

    /**
     * redirect() runs through the WorkflowStatus transition map, where Approved allows
     * no further transitions — TaskHoldRedirectTest already proves the service rejects
     * this (422/IllegalTransitionException); this proves the button itself stops
     * rendering too, once the step reaches that state.
     */
    public function test_redirect_does_not_appear_once_the_step_is_approved(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $beforeApproval = $this->actingAs($this->manager)->get(route('tasks.show', $task));
        $beforeApproval->assertSee(__('agencyos.tasks.actions.redirect_task'));

        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/out');
        $this->workflow()->submit($step, $this->employee);
        $this->workflow()->approve($step, $this->leader);

        $afterApproval = $this->actingAs($this->manager)->get(route('tasks.show', $task));
        $afterApproval->assertDontSee(__('agencyos.tasks.actions.redirect_task'));
    }

    public function test_a_classic_workflow_violation_redirects_back_with_an_error_not_json(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        // Submitting with no output is a WorkflowException (MissingOutputException).
        $response = $this->actingAs($this->employee)->post(route('tasks.steps.submit', $step));

        $response->assertRedirect();
        $response->assertSessionHasErrors('workflow');
        $this->assertStringNotContainsString('workflow_violation', $response->getContent() ?: '');
    }

    public function test_the_full_classic_form_workflow_redirects_at_every_step(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);
        $step = $task->currentStep;

        $this->actingAs($this->leader)->post(route('tasks.steps.assign', $step), [
            'assignee_id' => $this->employee->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
        ])->assertRedirect(route('tasks.show', $task));

        $step->refresh();

        $this->actingAs($this->employee)->post(route('tasks.steps.outputs.store', $step), [
            'url' => 'https://drive.example.com/out',
        ])->assertRedirect(route('tasks.show', $task));

        $this->actingAs($this->employee)->post(route('tasks.steps.submit', $step))
            ->assertRedirect(route('tasks.show', $task));

        $this->actingAs($this->leader)->post(route('tasks.steps.review', $step), [
            'decision' => 'approved',
        ])->assertRedirect(route('tasks.show', $task));

        $step->refresh();

        $this->actingAs($this->leader)->post(route('tasks.steps.complete', $step))
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame('completed', $task->fresh()->lifecycle_status->value);
    }

    public function test_a_classic_cancel_redirects_to_the_task_page(): void
    {
        $task = $this->newTask($this->marketing, $this->manager);

        $this->actingAs($this->manager)
            ->post(route('tasks.cancel', $task), ['reason' => 'No longer needed'])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame('cancelled', $task->fresh()->lifecycle_status->value);
    }

    public function test_the_assignee_and_department_leader_can_add_and_see_comments(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->employee)
            ->post(route('tasks.steps.comments.store', $step), ['body' => 'Need the brand kit link'])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertDatabaseHas('task_step_comments', [
            'task_step_id' => $step->id,
            'body' => 'Need the brand kit link',
        ]);

        $this->actingAs($this->leader)->get(route('tasks.show', $task))
            ->assertSee('Need the brand kit link');
    }

    public function test_an_unrelated_employee_cannot_add_a_comment(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $outsider = $this->makeEmployee($this->makeDepartment('Design'));

        $this->actingAs($outsider)
            ->post(route('tasks.steps.comments.store', $step), ['body' => 'Not my business'])
            ->assertForbidden();
    }

    public function test_the_history_page_renders(): void
    {
        [$task] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->manager)->get(route('tasks.history', $task))
            ->assertOk()
            ->assertViewIs('tasks.history');
    }
}
