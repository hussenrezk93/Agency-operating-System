<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Http\Requests\CancelTaskRequest;
use App\Http\Requests\HoldTaskRequest;
use App\Http\Requests\RedirectTaskRequest;
use App\Http\Requests\StoreTaskRequest;
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

        $query = Task::query()->with(['currentStep.department:id,name', 'project:id,name'])->latest('id');

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
            return redirect()->route('tasks.show', $task)->with('status', __('agencyos.tasks.flash.created'));
        }

        return response()->json(['data' => $task], 201);
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
            return $this->showView($request->user(), $task);
        }

        return response()->json(['data' => $task]);
    }

    private function showView(User $actor, Task $task): View
    {
        $step = $task->currentStep;

        return view('tasks.show', [
            'task' => $task,
            'step' => $step,
            'outputs' => $step?->outputsForCurrentSubmission() ?? collect(),
            'previousOutputs' => $step !== null ? $task->approvedOutputsBefore($step->sequence_no) : collect(),
            'comments' => ($step !== null && $actor->can('viewComments', $step))
                ? $step->comments()->with('author:id,full_name')->get()
                : collect(),
            'canAddComment' => $step !== null && $actor->can('addComment', $step),
            'canAssign' => $step !== null && $actor->can('assign', $step),
            'canAddOutput' => $step !== null && $actor->can('addOutput', $step),
            'canSubmit' => $step !== null && $actor->can('submit', $step),
            'canReview' => $step !== null && $actor->can('review', $step),
            'canTransfer' => $step !== null && $actor->can('transfer', $step),
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
