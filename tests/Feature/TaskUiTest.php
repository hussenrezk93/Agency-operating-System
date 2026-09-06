<?php

namespace Tests\Feature;

use App\Http\Controllers\TaskController;
use App\Models\Department;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Q12 — the TL self-assigned, so the Manager (not the TL) does the first review;
     * the badge must name the Manager as reviewer, not the generic "awaiting TL" label.
     */
    public function test_a_self_assigned_step_shows_the_manager_as_reviewer_on_the_task_page(): void
    {
        [$task] = $this->taskUnderReview($this->marketing, $this->leader, $this->leader);

        $this->actingAs($this->manager)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee(__('agencyos.tasks.workflow_status.pending_manager_review'))
            ->assertDontSee(__('agencyos.tasks.workflow_status.under_review'));
    }

    /** The ordinary (not self-assigned) case still names the Team Leader. */
    public function test_a_normally_assigned_step_shows_the_team_leader_as_reviewer_on_the_task_page(): void
    {
        [$task] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->manager)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee(__('agencyos.tasks.workflow_status.under_review'));
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
    /**
     * The badge and the filter have to agree: a self-assigned step at Under Review is
     * labelled "awaiting Manager review" (Q12), so it must NOT come back under the
     * "awaiting Team Leader review" filter — which is what the Manager reported seeing.
     */
    public function test_the_team_leader_review_filter_excludes_self_assigned_steps(): void
    {
        // Normal: employee's work, genuinely waiting on the TL.
        $this->taskUnderReview($this->marketing, $this->leader, $this->employee, null, ['title' => 'Employee work awaiting the TL']);
        // Self-assigned: the TL did it themselves, so the Manager reviews it instead.
        $this->taskUnderReview($this->marketing, $this->leader, $this->leader, null, ['title' => 'Self assigned work of the TL']);

        $response = $this->actingAs($this->manager)->get(route('tasks.index', ['status' => 'under_review']));

        $response->assertOk();
        $response->assertSee('Employee work awaiting the TL');
        $response->assertDontSee('Self assigned work of the TL');
    }

    /** The mirror of the above: the Manager's filter picks up both real
     *  PendingManagerReview rows and self-assigned steps still at Under Review. */
    public function test_the_manager_review_filter_includes_self_assigned_under_review_steps(): void
    {
        [$selfAssigned, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->leader);

        // A genuine second-stage row: employee work the TL has already approved.
        [$secondStage, $otherStep] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);
        $this->workflow()->approve($otherStep, $this->leader);

        $response = $this->actingAs($this->manager)->get(route('tasks.index', ['status' => 'pending_manager_review']));

        $response->assertOk();
        $response->assertSee($selfAssigned->title);
        $response->assertSee($secondStage->title);
    }

    /**
     * Reported from production: the Content leader saw the "Awaiting my review" button
     * on their dashboard, opened it, and found nothing — the link filtered by the
     * stored status `under_review`, while their queue actually sits at
     * `pending_content_review` in ANOTHER department. The filter is now about the
     * viewer's turn, not about one stored status.
     */
    public function test_the_awaiting_my_review_filter_shows_a_content_leaders_graphic_queue(): void
    {
        $graphic = $this->makeDepartment('Graphic');
        $graphic->forceFill(['special_role' => 'graphic'])->save();
        $content = $this->makeDepartment('Content');
        $content->forceFill(['special_role' => 'content'])->save();

        $graphicLeader = $this->makeTeamLeader($graphic);
        $contentLeader = $this->makeTeamLeader($content);
        $designer = $this->makeEmployee($graphic);

        [$task, $step] = $this->taskUnderReview($graphic, $graphicLeader, $designer, null, ['title' => 'Key visual for September']);

        $url = route('tasks.index', ['status' => TaskController::AWAITING_MY_REVIEW]);

        $this->actingAs($contentLeader)->get($url)->assertOk()
            ->assertDontSee('Key visual for September', false);

        $this->workflow()->approve($step, $graphicLeader);

        $this->actingAs($contentLeader)->get($url)->assertOk()
            ->assertSee('Key visual for September', false);

        // Graphic's own leader had their turn already — it is no longer awaiting them.
        $this->actingAs($graphicLeader)->get($url)->assertOk()
            ->assertDontSee('Key visual for September', false);
    }

    /** The same filter for a Manager: their second-stage queue plus the self-assigned
     *  steps Q12 hands them, and nothing that is still someone else's turn. */
    public function test_the_awaiting_my_review_filter_is_the_managers_own_queue(): void
    {
        [$selfAssigned] = $this->taskUnderReview($this->marketing, $this->leader, $this->leader, null, ['title' => 'Self assigned by the leader']);
        [$secondStage, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee, null, ['title' => 'Already approved by the leader']);
        $this->workflow()->approve($step, $this->leader);
        $this->taskUnderReview($this->marketing, $this->leader, $this->employee, null, ['title' => 'Still with the team leader']);

        $response = $this->actingAs($this->manager)
            ->get(route('tasks.index', ['status' => TaskController::AWAITING_MY_REVIEW]));

        $response->assertOk();
        $response->assertSee('Self assigned by the leader', false);
        $response->assertSee('Already approved by the leader', false);
        $response->assertDontSee('Still with the team leader', false);
    }

    /** A team leader's own queue never includes work they did themselves (Q12). */
    public function test_the_awaiting_my_review_filter_excludes_a_leaders_own_work(): void
    {
        $this->taskUnderReview($this->marketing, $this->leader, $this->employee, null, ['title' => 'Employee work for the leader']);
        $this->taskUnderReview($this->marketing, $this->leader, $this->leader, null, ['title' => 'Leader own work']);

        $response = $this->actingAs($this->leader)
            ->get(route('tasks.index', ['status' => TaskController::AWAITING_MY_REVIEW]));

        $response->assertOk();
        $response->assertSee('Employee work for the leader', false);
        $response->assertDontSee('Leader own work', false);
    }

    /**
     * Reported from production: the Manager approved a self-assigned step twice (the
     * two stages), clicked once more, and got a bare "403 THIS ACTION IS UNAUTHORIZED"
     * — which reads as a broken permission rather than "there is nothing left to
     * review". The extra click now lands back on the task with a plain explanation.
     */
    public function test_reviewing_an_already_approved_step_explains_itself_instead_of_a_bare_403(): void
    {
        [$task, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);
        $this->workflow()->approve($step, $this->leader);
        $this->workflow()->approve($step->refresh(), $this->manager);

        $this->actingAs($this->manager)
            ->post(route('tasks.steps.review', $step), ['decision' => 'approved'])
            ->assertRedirect(route('tasks.show', $task->id))
            ->assertSessionHas('status', __('agencyos.tasks.flash.already_decided'));
    }

    /** Somebody with no claim on the step still gets a plain 403, not the friendly note. */
    public function test_an_unrelated_employee_still_gets_a_403_on_review(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);
        $outsider = $this->makeEmployee($this->makeDepartment('Design'));

        $this->actingAs($outsider)
            ->post(route('tasks.steps.review', $step), ['decision' => 'approved'])
            ->assertForbidden();
    }

    /** Each approval stage says which one it was — both used to flash the same text. */
    public function test_the_two_approval_stages_flash_different_messages(): void
    {
        [, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->leader)
            ->post(route('tasks.steps.review', $step), ['decision' => 'approved'])
            ->assertSessionHas('status', __('agencyos.tasks.flash.approved_pending_manager'));

        $this->actingAs($this->manager)
            ->post(route('tasks.steps.review', $step->refresh()), ['decision' => 'approved'])
            ->assertSessionHas('status', __('agencyos.tasks.flash.approved_final'));
    }

    public function test_the_sidebar_review_queue_link_opens_the_real_under_review_tasks(): void
    {
        [$underReviewTask, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/out');
        $this->workflow()->submit($step, $this->employee);
        $inProgressTask = $this->newTask($this->marketing, $this->manager);

        // The link moved to the viewer-based "awaiting my review" filter in 2026-09 —
        // a stored status could not express the Content leader's cross-department queue.
        $url = route('tasks.index', ['status' => TaskController::AWAITING_MY_REVIEW]);

        $dashboard = $this->actingAs($this->leader)->get(route('dashboard'));
        $dashboard->assertSee($url, false);

        $reviewQueue = $this->actingAs($this->leader)->get($url);
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

    public function test_the_create_form_offers_two_reference_and_two_material_link_slots(): void
    {
        $response = $this->actingAs($this->manager)->get('/tasks/create');

        $response->assertOk();
        $response->assertSee(__('agencyos.tasks.fields.reference_link_n', ['n' => 1]));
        $response->assertSee(__('agencyos.tasks.fields.reference_link_n', ['n' => 2]));
        $response->assertDontSee(__('agencyos.tasks.fields.reference_link_n', ['n' => 3]));
        $response->assertSee(__('agencyos.tasks.fields.material_link_n', ['n' => 1]));
        $response->assertSee(__('agencyos.tasks.fields.material_link_n', ['n' => 2]));
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

    public function test_uploading_an_image_for_a_reference_link_slot_over_http(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->create('brief.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->manager)->post('/tasks', [
            'title' => 'Task with an image reference',
            'brief' => 'Created through the real Blade form.',
            'first_department_id' => $this->marketing->id,
            'reference_links' => [
                ['url' => '', 'label' => 'Moodboard', 'media' => $image],
                ['url' => '', 'label' => ''],
                ['url' => '', 'label' => ''],
            ],
        ]);

        $task = Task::where('title', 'Task with an image reference')->firstOrFail();
        $response->assertRedirect(route('tasks.show', $task));

        $link = $task->referenceLinks()->sole();
        $this->assertTrue($link->is_upload);
        Storage::disk('public')->assertExists($link->url);
    }

    /** Product decision 2026-09 — a reference slot can be a video, not just an image. */
    public function test_uploading_a_video_for_a_reference_link_slot_over_http(): void
    {
        Storage::fake('public');
        $video = UploadedFile::fake()->create('promo.mp4', 500, 'video/mp4');

        $response = $this->actingAs($this->manager)->post('/tasks', [
            'title' => 'Task with a video reference',
            'brief' => 'Created through the real Blade form.',
            'first_department_id' => $this->marketing->id,
            'reference_links' => [
                ['url' => '', 'label' => 'Promo cut', 'media' => $video],
            ],
        ]);

        $task = Task::where('title', 'Task with a video reference')->firstOrFail();
        $response->assertRedirect(route('tasks.show', $task));

        $link = $task->referenceLinks()->sole();
        $this->assertTrue($link->is_upload);
        $this->assertTrue($link->isVideo());
        Storage::disk('public')->assertExists($link->url);
    }

    public function test_providing_both_a_url_and_an_image_on_the_same_reference_slot_is_rejected(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->create('brief.jpg', 100, 'image/jpeg');

        $this->actingAs($this->manager)->post('/tasks', [
            'title' => 'Conflicting reference slot',
            'brief' => 'Should be rejected.',
            'first_department_id' => $this->marketing->id,
            'reference_links' => [
                ['url' => 'https://drive.example.com/brief', 'label' => '', 'media' => $image],
            ],
        ])->assertSessionHasErrors('reference_links.0.url');
    }

    /** The show page renders an uploaded reference link as an image thumbnail, not a link button. */
    public function test_an_uploaded_reference_link_renders_as_an_image_on_the_show_page(): void
    {
        $task = $this->newTask($this->marketing, $this->manager, [
            'reference_links' => [
                ['url' => 'task-references/moodboard.jpg', 'is_upload' => true],
            ],
        ]);
        $link = $task->referenceLinks->sole();

        $response = $this->actingAs($this->manager)->get(route('tasks.show', $task));

        $response->assertOk();
        $response->assertSee($link->displayUrl(), false);
        $response->assertSee('<img', false);
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
        $this->workflow()->approve($step->refresh(), $this->manager);

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
     * Regression — the two-stage review (CR-003) widened approve()/requestChanges() to
     * also accept PendingManagerReview, but the Blade condition gating this form was
     * left checking only UnderReview, so the Manager's own review step never rendered
     * an Approve button at all once the TL had already signed off.
     */
    public function test_approve_and_request_changes_appear_for_the_manager_at_the_second_stage(): void
    {
        [$task, $step] = $this->taskUnderReview($this->marketing, $this->leader, $this->employee);
        $this->workflow()->approve($step, $this->leader);

        $pendingManagerReview = $this->actingAs($this->manager)->get(route('tasks.show', $task));

        $pendingManagerReview->assertSee(__('agencyos.tasks.actions.approve'));
        $pendingManagerReview->assertSee(__('agencyos.tasks.actions.request_changes'));
    }

    /**
     * submit() requires In Progress or Changes Requested (422 otherwise), so "Submit"
     * must not still render once they've already submitted. addOutput()/removeOutput()
     * stay open through Under Review too (WorkflowStatus::canManageOutputs()) — submit()
     * does NOT end the assignment, only approve() does, so the employee stays the
     * step's "current assignee" straight through Under Review and may keep managing
     * their output links until the reviewer actually decides.
     */
    public function test_submit_disappears_but_add_output_stays_available_once_already_submitted(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/out');

        $inProgress = $this->actingAs($this->employee)->get(route('tasks.show', $task));
        $inProgress->assertSee(__('agencyos.tasks.actions.add_output_button'));
        $inProgress->assertSee(__('agencyos.tasks.actions.submit_work'));

        $this->workflow()->submit($step, $this->employee);

        $underReview = $this->actingAs($this->employee)->get(route('tasks.show', $task));
        $underReview->assertSee(__('agencyos.tasks.actions.add_output_button'));
        $underReview->assertDontSee(__('agencyos.tasks.actions.submit_work'));
    }

    public function test_the_remove_output_button_renders_on_the_show_page_while_under_review(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $output = $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/out');
        $this->workflow()->submit($step, $this->employee);

        $response = $this->actingAs($this->employee)->get(route('tasks.show', $task));

        $response->assertOk();
        $response->assertSee(route('tasks.steps.outputs.destroy', [$step, $output]), false);
    }

    public function test_uploading_an_image_output_over_http_stores_it_and_flags_the_row(): void
    {
        Storage::fake('public');
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $image = UploadedFile::fake()->create('cut.jpg', 100, 'image/jpeg');

        $this->actingAs($this->employee)
            ->post(route('tasks.steps.outputs.store', $step), ['media' => $image])
            ->assertRedirect(route('tasks.show', $task));

        $output = $step->outputs()->latest('id')->firstOrFail();
        $this->assertTrue($output->is_upload);
        Storage::disk('public')->assertExists($output->url);
    }

    /** Product decision 2026-09 — an output can be an uploaded video, not just an image. */
    public function test_uploading_a_video_output_over_http_stores_it_and_flags_the_row(): void
    {
        Storage::fake('public');
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $video = UploadedFile::fake()->create('cut.mp4', 500, 'video/mp4');

        $this->actingAs($this->employee)
            ->post(route('tasks.steps.outputs.store', $step), ['media' => $video])
            ->assertRedirect(route('tasks.show', $task));

        $output = $step->outputs()->latest('id')->firstOrFail();
        $this->assertTrue($output->is_upload);
        $this->assertTrue($output->isVideo());
        Storage::disk('public')->assertExists($output->url);
    }

    public function test_providing_both_a_url_and_an_image_is_rejected(): void
    {
        Storage::fake('public');
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $image = UploadedFile::fake()->create('cut.jpg', 100, 'image/jpeg');

        $this->actingAs($this->employee)
            ->post(route('tasks.steps.outputs.store', $step), ['url' => 'https://drive.example.com/cut', 'media' => $image])
            ->assertSessionHasErrors('url');
    }

    public function test_providing_neither_a_url_nor_an_image_is_rejected(): void
    {
        [, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->employee)
            ->post(route('tasks.steps.outputs.store', $step), [])
            ->assertSessionHasErrors('url');
    }

    /** The show page renders an uploaded output as an image thumbnail, not a link button. */
    public function test_an_uploaded_output_renders_as_an_image_on_the_show_page(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $output = $this->workflow()->addOutput($step, $this->employee, 'task-outputs/cut.jpg', isUpload: true);

        $response = $this->actingAs($this->employee)->get(route('tasks.show', $task));

        $response->assertOk();
        $response->assertSee($output->displayUrl(), false);
        $response->assertSee('<img', false);
    }

    public function test_removing_an_output_over_http_redirects_back_to_the_task(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);
        $output = $this->workflow()->addOutput($step, $this->employee, 'https://drive.example.com/wrong');

        $this->actingAs($this->employee)
            ->delete(route('tasks.steps.outputs.destroy', [$step, $output]))
            ->assertRedirect(route('tasks.show', $task));

        $this->assertNotNull($output->fresh()->removed_at);

        $afterRemoval = $this->actingAs($this->employee)->get(route('tasks.show', $task));
        $afterRemoval->assertDontSee('https://drive.example.com/wrong', false);
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
        $this->workflow()->approve($step->refresh(), $this->manager);

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

        // Product decision 2026-09 — the TL's approval alone only reaches
        // PendingManagerReview; the Manager still has to sign off before Finish works.
        $this->actingAs($this->manager)->post(route('tasks.steps.review', $step), [
            'decision' => 'approved',
        ])->assertRedirect(route('tasks.show', $task));

        $step->refresh();

        $this->actingAs($this->leader)->post(route('tasks.steps.complete', $step))
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame('completed', $task->fresh()->lifecycle_status->value);
    }

    /** Product decision 2026-09 — a Manager reopens a Completed task via the classic
     *  form and it's back on the assignee's queue. */
    public function test_a_classic_reopen_redirects_to_the_task_page_and_reactivates_it(): void
    {
        [$task, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);
        $this->workflow()->completeTask($step, $this->leader);

        $this->actingAs($this->manager)
            ->post(route('tasks.reopen', $task), [
                'reason' => 'Client wants a colour fix.',
                'due_date' => now()->addDays(2)->toDateString(),
            ])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertSame('active', $task->fresh()->lifecycle_status->value);
        $this->assertSame('changes_requested', $task->fresh()->currentStep->workflow_status->value);
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

    /** 2026-08 decision — the Manager may read and post too, on any task, without it
     *  touching the workflow (TaskWorkflowService::addComment() only writes the row). */
    public function test_the_manager_can_add_and_see_a_comment_on_any_task(): void
    {
        [$task, $step] = $this->taskInProgress($this->marketing, $this->leader, $this->employee);

        $this->actingAs($this->manager)
            ->post(route('tasks.steps.comments.store', $step), ['body' => 'Keep me posted here'])
            ->assertRedirect(route('tasks.show', $task));

        $this->assertDatabaseHas('task_step_comments', [
            'task_step_id' => $step->id,
            'body' => 'Keep me posted here',
        ]);
        $this->assertSame('in_progress', $step->fresh()->workflow_status->value);

        $this->actingAs($this->manager)->get(route('tasks.show', $task))
            ->assertSee('Keep me posted here');
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

    /**
     * Regression — CR-003 deleted the "creator review" TaskEvent cases entirely, but
     * production still has real task_status_history rows with the old event_type
     * values (e.g. creator_review_rejected). The enum cast throws a ValueError on any
     * value it can't map, so removing the cases 500'd every task whose timeline
     * included one. The cases were restored read-only; this proves an old row no
     * longer crashes the page.
     */
    public function test_a_task_history_row_with_a_retired_creator_review_event_still_renders(): void
    {
        [$task, $step] = $this->taskApproved($this->marketing, $this->leader, $this->employee);

        TaskStatusHistory::create([
            'task_id' => $task->id,
            'task_step_id' => $step->id,
            'event_type' => 'creator_review_rejected',
            'changed_by' => $this->manager->id,
            'department_id' => $step->department_id,
            'reason' => 'Legacy row from the removed creator-review feature.',
            'created_at' => now(),
        ]);

        $this->actingAs($this->manager)->get(route('tasks.history', $task))
            ->assertOk()
            ->assertSee(__('agencyos.tasks.event.creator_review_rejected'));
    }
}
