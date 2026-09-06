<?php

namespace App\Http\Controllers;

use App\Enums\DepartmentSpecialRole;
use App\Enums\RoleCode;
use App\Enums\TaskLifecycle;
use App\Enums\UserStatus;
use App\Enums\WorkflowStatus;
use App\Http\Requests\CancelTaskRequest;
use App\Http\Requests\HoldTaskRequest;
use App\Http\Requests\PublishDraftTaskRequest;
use App\Http\Requests\RedirectTaskRequest;
use App\Http\Requests\ReopenTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use App\Services\TaskRoutingService;
use App\Services\TaskWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    /** The synthetic `status` filter value behind every "Awaiting my review" link. */
    public const AWAITING_MY_REVIEW = 'awaiting_me';

    public function __construct(
        private readonly TaskWorkflowService $workflow,
        private readonly TaskRoutingService $routing,
    ) {}

    /** Blade-only — the real task list (see `MyTasksController` for the JSON "mine" API). */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Task::class);
        $actor = $request->user();

        // BRD §8/§16 — Urgent-priority tasks sort to the top everywhere task lists appear.
        $query = Task::query()->with([
            'currentStep.department:id,name',
            'currentStep.activeAssignment.assignee:id,full_name',
            'project:id,name',
        ])
            ->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END")
            ->latest('id');

        $isOwnList = false;

        if ($actor->hasRole(RoleCode::Employee)) {
            $isOwnList = true;
            $query->whereHas('steps.assignments', fn (Builder $q) => $q->where('assignee_id', $actor->id));
        } elseif ($actor->hasRole(RoleCode::TeamLeader)) {
            if ($request->query('view') === 'my') {
                $isOwnList = true;
                $query->whereHas('steps.assignments', fn (Builder $q) => $q->where('assignee_id', $actor->id));
            } else {
                // Content also reviews Graphic's steps (product decision 2026-09), which
                // belong to another department — same widening TaskPolicy::view() makes,
                // so a task the leader may open is also a task they can find.
                $content = Department::withSpecialRole(DepartmentSpecialRole::Content);
                $reviewsForContent = $content !== null && $actor->canActAsLeaderOf($content->id);

                $query->where(function (Builder $q) use ($actor, $reviewsForContent): void {
                    $q->whereHas('steps', fn (Builder $s) => $s->where('department_id', $actor->department_id));

                    if ($reviewsForContent) {
                        $q->orWhereHas('currentStep', fn (Builder $s) => $s->where(
                            'workflow_status', WorkflowStatus::PendingContentReview->value,
                        ));
                    }
                });
            }
        }

        // Approved decision Q19/CR-006 — there is no "Seen" button; opening the list of
        // one's own tasks IS the seen signal. This is the page real users actually land
        // on (via the "My tasks" sidebar link); MyTasksController's identical sweep
        // guards the JSON endpoint but nothing in the UI calls it.
        if ($isOwnList) {
            $this->workflow->markMyTasksSeen($actor);
        }

        if ($status = $request->query('status')) {
            $query->whereHas('currentStep', fn (Builder $q) => $this->scopeToReviewStage($q, $status, $actor));
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }
        if ($departmentId = $request->query('department_id')) {
            $query->whereHas('currentStep', fn (Builder $q) => $q->where('department_id', $departmentId));
        }

        return view('tasks.index', [
            'tasks' => $query->paginate(50)->withQueryString(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'canCreate' => $actor->can('create', Task::class),
        ]);
    }

    /** Blade-only — the create-task form. */
    public function create(Request $request): View
    {
        $this->authorize('create', Task::class);
        $actor = $request->user();

        $departments = Department::where('is_active', true)->orderBy('name')->get();

        if ($actor->hasRole(RoleCode::TeamLeader)) {
            $allowed = array_merge(
                [$actor->department_id],
                $actor->department?->allowedNextDepartmentIds() ?? [],
            );
            $departments = $departments->whereIn('id', $allowed)->values();
        }

        return view('tasks.create', [
            'departments' => $departments,
            'projects' => Project::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreTaskRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        // The service never touches an UploadedFile directly (same convention as
        // avatar uploads) — each slot's media is stored here first, and the resulting
        // path takes the URL's place with is_upload set, exactly like TaskStepOutput.
        foreach ($data['reference_links'] ?? [] as $i => $link) {
            if (isset($link['media'])) {
                $data['reference_links'][$i]['url'] = $link['media']->store('task-references', 'public');
                $data['reference_links'][$i]['is_upload'] = true;
                unset($data['reference_links'][$i]['media']);
            }
        }

        $task = $this->workflow->createTask($request->user(), $data)
            ->load(['currentStep.department', 'referenceLinks']);

        if (! $request->expectsJson()) {
            $flash = $request->isDraft() ? 'agencyos.tasks.flash.draft_saved' : 'agencyos.tasks.flash.created';

            return redirect()->route('tasks.show', $task)->with('status', __($flash));
        }

        return response()->json(['data' => $task], 201);
    }

    public function publish(PublishDraftTaskRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $department = Department::findOrFail($request->integer('first_department_id'));

        $published = $this->workflow->publishDraft($task, $request->user(), $department);

        if (! $request->expectsJson()) {
            return redirect()->route('tasks.show', $published)->with('status', __('agencyos.tasks.flash.published'));
        }

        return response()->json(['data' => $published]);
    }

    public function destroy(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $this->authorize('deleteDraft', $task);

        $this->workflow->deleteDraft($task, $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('tasks.index')->with('status', __('agencyos.tasks.flash.draft_deleted'));
        }

        return response()->json(null, 204);
    }

    /** Blade-only — the creator's edit form, only reachable before the task is assigned. */
    public function edit(Request $request, Task $task): View
    {
        $this->authorize('update', $task);

        return view('tasks.edit', ['task' => $task]);
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $updated = $this->workflow->updateTask($task, $request->validated(), $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('tasks.show', $updated)->with('status', __('agencyos.tasks.flash.updated'));
        }

        return response()->json(['data' => $updated]);
    }

    public function show(Request $request, Task $task): JsonResponse|View
    {
        $this->authorize('view', $task);

        $task->load([
            'project:id,name',
            'creator:id,full_name',
            'currentStep.department:id,name',
            'currentStep.activeAssignment.assignee:id,full_name',
            'referenceLinks',
            'steps.department:id,name',
            'steps.activeAssignment.assignee:id,full_name',
            'history.changedBy:id,full_name',
        ]);

        if (! $request->expectsJson()) {
            if ($task->lifecycle_status === TaskLifecycle::Draft) {
                return view('tasks.draft', [
                    'task' => $task,
                    'departments' => Department::where('is_active', true)->orderBy('name')->get(),
                    'canPublish' => $request->user()->can('publish', $task),
                    'canDelete' => $request->user()->can('deleteDraft', $task),
                ]);
            }

            return $this->showView($request->user(), $task);
        }

        return response()->json(['data' => $task]);
    }

    /**
     * The status filter has to match the badge the row actually shows, not just the
     * stored column. A self-assigned step sitting at Under Review is labelled "awaiting
     * MANAGER review" (Q12 — a Team Leader never reviews work they did themselves), so
     * filtering by "awaiting Team Leader review" must leave those out, and filtering by
     * "awaiting Manager review" must include them alongside the real
     * PendingManagerReview rows. Anything else is an exact match on the column.
     */
    private function scopeToReviewStage(Builder $query, string $status, User $actor): Builder
    {
        if ($status === self::AWAITING_MY_REVIEW) {
            return $this->scopeToMyReviewQueue($query, $actor);
        }

        $selfAssigned = fn (Builder $q) => $q->whereHas(
            'activeAssignment',
            fn (Builder $assignment) => $assignment->where('is_self_assigned', true),
        );

        if ($status === WorkflowStatus::UnderReview->value) {
            return $query->where('workflow_status', $status)
                ->whereDoesntHave('activeAssignment', fn (Builder $a) => $a->where('is_self_assigned', true));
        }

        if ($status === WorkflowStatus::PendingManagerReview->value) {
            return $query->where(fn (Builder $q) => $q
                ->where('workflow_status', $status)
                ->orWhere(fn (Builder $q2) => $selfAssigned(
                    $q2->where('workflow_status', WorkflowStatus::UnderReview->value)
                )));
        }

        return $query->where('workflow_status', $status);
    }

    /**
     * "Awaiting my review" — the one list every reviewer actually wants, and the only
     * honest destination for the dashboards' own review links: a Content leader's queue
     * lives at pending_content_review and a Manager's at pending_manager_review, so no
     * single stored status could stand in for it. Deliberately NOT a WorkflowStatus
     * case: it's a question about the VIEWER, not about the step.
     */
    private function scopeToMyReviewQueue(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole(RoleCode::Manager)) {
            // Q12 — a self-assigned step still at Under Review is the Manager's too.
            return $query->where(fn (Builder $q) => $q
                ->where('workflow_status', WorkflowStatus::PendingManagerReview->value)
                ->orWhere(fn (Builder $q2) => $q2
                    ->where('workflow_status', WorkflowStatus::UnderReview->value)
                    ->whereHas('activeAssignment', fn (Builder $a) => $a->where('is_self_assigned', true))));
        }

        if (! $actor->hasRole(RoleCode::TeamLeader)) {
            return $query->whereRaw('1 = 0');
        }

        $content = Department::withSpecialRole(DepartmentSpecialRole::Content);
        $reviewsForContent = $content !== null && $actor->canActAsLeaderOf($content->id);

        return $query->where(function (Builder $q) use ($actor, $reviewsForContent): void {
            // Their own department's submissions — minus the ones they did themselves,
            // which Q12 hands to the Manager instead.
            $q->where(fn (Builder $own) => $own
                ->where('department_id', $actor->department_id)
                ->where('workflow_status', WorkflowStatus::UnderReview->value)
                ->whereDoesntHave('activeAssignment', fn (Builder $a) => $a->where('is_self_assigned', true)));

            if ($reviewsForContent) {
                $q->orWhere('workflow_status', WorkflowStatus::PendingContentReview->value);
            }
        });
    }

    /**
     * BRD §15 — a Team Leader only sees an earlier step's output if their department has
     * Admin-granted access to that step's department (their own department always does).
     * Manager keeps the unrestricted visibility they have everywhere else in the app.
     *
     * The assignee is the exception, and it is Q26's whole point: whoever is holding the
     * step right now reads the final approved work of the steps before it, because they
     * cannot continue from material they are not allowed to open. That right follows the
     * SEAT, not the role — BRD §15's matrix governs a Team Leader BROWSING another
     * department's outputs, not the person actually doing the work.
     *
     * Reported from production 2026-09-05 (TSK-2026-00026): a task was reopened and
     * redirected to the Moderator, whose Team Leader took the step herself. Her own
     * department has no granted access to Graphic, so the filter below stripped the two
     * Drive links the step exists to act on — and it read as though the outputs had been
     * deleted. An Employee in the same seat would have seen them the whole time.
     */
    private function visiblePreviousOutputs(User $actor, Task $task, TaskStep $step)
    {
        $outputs = $task->approvedOutputsBefore($step->sequence_no);

        if ($step->activeAssignment?->assignee_id === $actor->id) {
            return $outputs;
        }

        if (! $actor->hasRole(RoleCode::TeamLeader) || $actor->department === null) {
            return $outputs;
        }

        return $outputs->filter(
            fn ($output) => $output->step !== null && $actor->department->hasOutputAccessTo($output->step->department_id),
        )->values();
    }

    private function showView(User $actor, Task $task): View
    {
        $step = $task->currentStep;

        return view('tasks.show', [
            'task' => $task,
            'step' => $step,
            'outputs' => $step?->outputsForCurrentSubmission() ?? collect(),
            'previousOutputs' => $step !== null
                ? $this->visiblePreviousOutputs($actor, $task, $step)
                : collect(),
            'comments' => ($step !== null && $actor->can('viewComments', $step))
                ? $step->comments()->with('author:id,full_name')->get()
                : collect(),
            'canAddComment' => $step !== null && $actor->can('addComment', $step),
            'canAssign' => $step !== null && $actor->can('assign', $step),
            'canAddOutput' => $step !== null && $actor->can('addOutput', $step),
            'canSubmit' => $step !== null && $actor->can('submit', $step),
            'canReview' => $step !== null && $actor->can('review', $step),
            'canTransfer' => $step !== null && $actor->can('transfer', $step),
            'canEdit' => $actor->can('update', $task),
            'canComplete' => $actor->can('complete', $task),
            'canReopen' => $actor->can('reopen', $task),
            'canCancel' => $actor->can('cancel', $task),
            'canHold' => $actor->can('hold', $task),
            'canResume' => $actor->can('resume', $task),
            'canRedirect' => $actor->can('redirect', $task),
            'allowedDepartments' => ($step !== null && $actor->can('transfer', $step))
                ? $this->routing->allowedNextDepartments($step)
                : collect(),
            'redirectDepartments' => ($step !== null && $actor->can('redirect', $task))
                ? Department::where('is_active', true)->whereKeyNot($step->department_id)->orderBy('name')->get()
                : collect(),
            'assignableUsers' => ($step !== null && $actor->can('assign', $step))
                ? User::where('department_id', $step->department_id)
                    ->where('status', UserStatus::Active->value)
                    ->orderBy('full_name')
                    ->get()
                : collect(),
        ]);
    }

    public function history(Request $request, Task $task): JsonResponse|View
    {
        $this->authorize('viewHistory', $task);

        $history = $task->history()->with('changedBy:id,full_name', 'department:id,name')->get();

        if (! $request->expectsJson()) {
            return view('tasks.history', ['task' => $task, 'history' => $history]);
        }

        return response()->json(['data' => $history]);
    }

    public function cancel(CancelTaskRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $cancelled = $this->workflow->cancelTask(
            $task,
            $request->user(),
            $request->string('reason')->toString(),
        );

        if (! $request->expectsJson()) {
            return redirect()->route('tasks.show', $cancelled)->with('status', __('agencyos.tasks.flash.cancelled'));
        }

        return response()->json(['data' => $cancelled]);
    }

    public function reopen(ReopenTaskRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $reopened = $this->workflow->reopen(
            $task,
            $request->user(),
            $request->string('reason')->toString(),
            $request->string('due_date')->toString(),
        );

        if (! $request->expectsJson()) {
            return redirect()->route('tasks.show', $reopened)->with('status', __('agencyos.tasks.flash.reopened'));
        }

        return response()->json(['data' => $reopened]);
    }

    public function hold(HoldTaskRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $held = $this->workflow->hold($task, $request->user(), $request->string('reason')->toString());

        if (! $request->expectsJson()) {
            return redirect()->route('tasks.show', $held)->with('status', __('agencyos.tasks.flash.held'));
        }

        return response()->json(['data' => $held]);
    }

    public function resume(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $resumed = $this->workflow->resume($task, $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('tasks.show', $resumed)->with('status', __('agencyos.tasks.flash.resumed'));
        }

        return response()->json(['data' => $resumed]);
    }

    public function redirect(RedirectTaskRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $target = Department::findOrFail($request->integer('department_id'));

        $this->workflow->redirect(
            $task,
            $request->user(),
            $target,
            $request->string('reason')->toString(),
        );

        if (! $request->expectsJson()) {
            return redirect()->route('tasks.show', $task)->with('status', __('agencyos.tasks.flash.redirected'));
        }

        return response()->json(['data' => $task->fresh(['currentStep.department'])]);
    }
}
