<?php

namespace App\Http\Controllers;

use App\Enums\ReviewDecision;
use App\Http\Requests\AddTaskOutputRequest;
use App\Http\Requests\AddTaskStepCommentRequest;
use App\Http\Requests\AssignTaskStepRequest;
use App\Http\Requests\ReviewTaskStepRequest;
use App\Http\Requests\TransferTaskStepRequest;
use App\Models\Department;
use App\Models\TaskStep;
use App\Models\User;
use App\Services\TaskRoutingService;
use App\Services\TaskWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskStepController extends Controller
{
    public function __construct(
        private readonly TaskWorkflowService $workflow,
        private readonly TaskRoutingService $routing,
    ) {}

    public function assign(AssignTaskStepRequest $request, TaskStep $step): JsonResponse|RedirectResponse
    {
        $assignment = $this->workflow->assign(
            $step,
            $request->user(),
            User::findOrFail($request->integer('assignee_id')),
            $request->date('start_date')->toDateString(),
            $request->date('due_date')->toDateString(),
        );

        return $this->respond($request, $step, $assignment, __('agencyos.tasks.flash.assigned'), 201);
    }

    public function reassign(AssignTaskStepRequest $request, TaskStep $step): JsonResponse|RedirectResponse
    {
        $assignment = $this->workflow->reassign(
            $step,
            $request->user(),
            User::findOrFail($request->integer('assignee_id')),
            $request->date('start_date')->toDateString(),
            $request->date('due_date')->toDateString(),
            $request->string('reason')->toString(),
        );

        return $this->respond($request, $step, $assignment, __('agencyos.tasks.flash.reassigned'), 201);
    }

    public function addOutput(AddTaskOutputRequest $request, TaskStep $step): JsonResponse|RedirectResponse
    {
        $output = $this->workflow->addOutput(
            $step,
            $request->user(),
            $request->string('url')->toString(),
            $request->filled('label') ? $request->string('label')->toString() : null,
        );

        return $this->respond($request, $step, $output, __('agencyos.tasks.flash.output_added'), 201);
    }

    public function submit(Request $request, TaskStep $step): JsonResponse|RedirectResponse
    {
        $this->authorize('submit', $step);

        $result = $this->workflow->submit($step, $request->user());

        return $this->respond($request, $step, $result, __('agencyos.tasks.flash.submitted'));
    }

    public function review(ReviewTaskStepRequest $request, TaskStep $step): JsonResponse|RedirectResponse
    {
        $decision = ReviewDecision::from($request->string('decision')->toString());
        $comment = $request->filled('comment') ? $request->string('comment')->toString() : null;

        $review = $decision === ReviewDecision::Approved
            ? $this->workflow->approve($step, $request->user(), $comment)
            : $this->workflow->requestChanges($step, $request->user(), (string) $comment);

        $flash = $decision === ReviewDecision::Approved
            ? __('agencyos.tasks.flash.approved')
            : __('agencyos.tasks.flash.changes_requested');

        return $this->respond($request, $step, $review, $flash, 201);
    }

    public function transfer(TransferTaskStepRequest $request, TaskStep $step): JsonResponse|RedirectResponse
    {
        $next = $this->workflow->sendToNextDepartment(
            $step,
            $request->user(),
            Department::findOrFail($request->integer('to_department_id')),
            $request->filled('reason') ? $request->string('reason')->toString() : null,
        );

        return $this->respond($request, $step, $next, __('agencyos.tasks.flash.transferred'), 201);
    }

    public function complete(Request $request, TaskStep $step): JsonResponse|RedirectResponse
    {
        $task = $this->workflow->completeTask($step, $request->user());

        if (! $request->expectsJson()) {
            return redirect()->route('tasks.show', $task)->with('status', __('agencyos.tasks.flash.completed'));
        }

        return response()->json(['data' => $task]);
    }

    public function addComment(AddTaskStepCommentRequest $request, TaskStep $step): JsonResponse|RedirectResponse
    {
        $comment = $this->workflow->addComment($step, $request->user(), $request->string('body')->toString());

        return $this->respond($request, $step, $comment, __('agencyos.tasks.flash.comment_added'), 201);
    }

    public function allowedDepartments(Request $request, TaskStep $step): JsonResponse
    {
        $this->authorize('transfer', $step);

        $departments = $this->routing->allowedNextDepartments($step)
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
            ])
            ->values();

        return response()->json(['departments' => $departments]);
    }

    public function firstSeen(Request $request, TaskStep $step): JsonResponse
    {
        $this->authorize('viewFirstSeen', $step);

        return response()->json([
            'first_seen_at' => $step->activeAssignment?->first_seen_at?->toIso8601String(),
        ]);
    }

    /** JSON keeps its exact original shape; a classic form redirects back to the task page. */
    private function respond(
        Request $request,
        TaskStep $step,
        mixed $data,
        string $flash,
        int $jsonStatus = 200,
    ): JsonResponse|RedirectResponse {
        if (! $request->expectsJson()) {
            return redirect()->route('tasks.show', $step->task_id)->with('status', $flash);
        }

        return response()->json(['data' => $data], $jsonStatus);
    }
}
