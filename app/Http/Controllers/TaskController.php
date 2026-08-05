<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Enums\TaskLifecycle;
use App\Enums\UserStatus;
use App\Http\Requests\CancelTaskRequest;
use App\Http\Requests\HoldTaskRequest;
use App\Http\Requests\PublishDraftTaskRequest;
use App\Http\Requests\RedirectTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
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
        $query = Task::query()->with(['currentStep.department:id,name', 'project:id,name'])
            ->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END")
            ->latest('id');

        if ($actor->hasRole(RoleCode::Employee)) {
            $query->whereHas('steps.assignments', fn (Builder $q) => $q->where('assignee_id', $actor->id));
        } elseif ($actor->hasRole(RoleCode::TeamLeader)) {
            if ($request->query('view') === 'my') {
                $query->where(function (Builder $q) use ($actor): void {
                    $q->whereHas('steps.assignments', fn (Builder $qq) => $qq->where('assignee_id', $actor->id))
                        ->orWhere('created_by', $actor->id);
                });
            } else {
                $query->whereHas('steps', fn (Builder $q) => $q->where('department_id', $actor->department_id));
            }
        }

        if ($status = $request->query('status')) {
            $query->whereHas('currentStep', fn (Builder $q) => $q->where('workflow_status', $status));
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }
        if ($departmentId = $request->query('department_id')) {
            $query->whereHas('currentStep', fn (Builder $q) => $q->where('department_id', $departmentId));
        }

        return view('tasks.index', [
            'tasks' => $query->limit(200)->get(),
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
        $task = $this->workflow->createTask($request->user(), $request->validated())
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
     * BRD §15 — a Team Leader only sees an earlier step's output if their department has
     * Admin-granted access to that step's department (their own department always does).
     * Manager keeps the unrestricted visibility they have everywhere else in the app;
     * the assignee's Q26 right to their own task's immediately-prior work is unaffected.
     */
    private function visiblePreviousOutputs(User $actor, Task $task, int $sequenceNo)
    {
        $outputs = $task->approvedOutputsBefore($sequenceNo);

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
                ? $this->visiblePreviousOutputs($actor, $task, $step->sequence_no)
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
