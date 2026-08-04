<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Models\Task;
use App\Services\TaskWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyTasksController extends Controller
{
    public function __construct(private readonly TaskWorkflowService $workflow) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorize('viewAny', Task::class);

        $newlySeen = $this->workflow->markMyTasksSeen($actor);

        $query = Task::query()
            ->with(['currentStep.department:id,name'])
            ->latest('id');

        if ($actor->hasRole(RoleCode::Employee)) {
            $query->whereHas('steps.assignments', function (Builder $builder) use ($actor): void {
                $builder->where('assignee_id', $actor->id);
            });
        } elseif ($actor->hasRole(RoleCode::TeamLeader)) {
            $query->whereHas('steps', function (Builder $builder) use ($actor): void {
                $builder->where('department_id', $actor->department_id);
            });
        }

        $tasks = $query->get()->map(static function (Task $task): array {
            return [
                'id' => $task->id,
                'task_code' => $task->task_code,
                'title' => $task->title,
                'priority' => $task->priority->value,
                'lifecycle_status' => $task->lifecycle_status->value,
                'current_step' => $task->currentStep === null ? null : [
                    'id' => $task->currentStep->id,
                    'sequence_no' => $task->currentStep->sequence_no,
                    'workflow_status' => $task->currentStep->workflow_status->value,
                    'department' => $task->currentStep->department === null ? null : [
                        'id' => $task->currentStep->department->id,
                        'name' => $task->currentStep->department->name,
                    ],
                ],
            ];
        })->values();

        return response()->json([
            'newly_seen' => $newlySeen,
            'tasks' => $tasks,
        ]);
    }
}
