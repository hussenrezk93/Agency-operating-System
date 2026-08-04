<?php

namespace App\Http\Controllers;

use App\Enums\DeadlineStatus;
use App\Enums\LeadershipType;
use App\Enums\ProjectStatus;
use App\Enums\RoleCode;
use App\Enums\SnapshotType;
use App\Enums\TaskLifecycle;
use App\Enums\WorkflowStatus;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\MonthlyPerformanceSnapshot;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\TaskStepAssignment;
use App\Models\User;
use App\Support\LocalizedDemoData;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** BRD §16 — one dashboard per role, each showing what that role actually acts on. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return match ($user->roleCode()) {
            RoleCode::Employee => $this->employeeDashboard($user),
            RoleCode::TeamLeader => $this->tlDashboard($user),
            RoleCode::Manager => $this->managerDashboard($user),
            default => $this->adminDashboard($user),
        };
    }

    private function employeeDashboard(User $user): View
    {
        $openAssignments = $user->openStepAssignments()->with('step')->get();

        return view('dashboard.employee', [
            'current' => $openAssignments->where('step.workflow_status', WorkflowStatus::InProgress)->count(),
            'changesRequested' => $openAssignments->where('step.workflow_status', WorkflowStatus::ChangesRequested)->count(),
            'overdue' => $openAssignments->where('step.deadline_status', DeadlineStatus::Overdue)->count(),
            'completed' => Task::whereHas('steps.assignments', fn ($q) => $q->where('assignee_id', $user->id))
                ->where('lifecycle_status', TaskLifecycle::Completed->value)
                ->count(),
            'score' => $this->currentMonthSnapshot($user, SnapshotType::Employee),
            'myTasks' => $openAssignments->take(10),
        ]);
    }

    private function tlDashboard(User $user): View
    {
        $departmentId = $user->department_id;
        $deptSteps = TaskStep::where('department_id', $departmentId);

        $tasksByEmployee = TaskStepAssignment::whereNull('ended_at')
            ->whereHas('step', fn ($q) => $q->where('department_id', $departmentId))
            ->with(['assignee:id,full_name', 'step.task:id,title,task_code'])
            ->get()
            ->groupBy('assignee_id');

        return view('dashboard.tl', [
            'waitingAssignment' => (clone $deptSteps)->where('workflow_status', WorkflowStatus::WaitingAssignment->value)->count(),
            'inProgress' => (clone $deptSteps)->where('workflow_status', WorkflowStatus::InProgress->value)->count(),
            'awaitingReview' => (clone $deptSteps)->where('workflow_status', WorkflowStatus::UnderReview->value)->count(),
            'overdue' => (clone $deptSteps)->where('deadline_status', DeadlineStatus::Overdue->value)->count(),
            'waitingAssignmentSteps' => (clone $deptSteps)->where('workflow_status', WorkflowStatus::WaitingAssignment->value)
                ->with('task:id,title,task_code')->limit(10)->get(),
            'submissionsAwaitingReview' => (clone $deptSteps)->where('workflow_status', WorkflowStatus::UnderReview->value)
                ->with(['task:id,title,task_code', 'activeAssignment.assignee:id,full_name'])->limit(10)->get(),
            'tasksByEmployee' => $tasksByEmployee,
            'myTasks' => $user->openStepAssignments()->where('is_self_assigned', true)->with('step.task:id,title,task_code')->get(),
            'personalScore' => $this->currentMonthSnapshot($user, SnapshotType::TlPersonal),
            'teamScore' => $this->currentMonthSnapshot($user, SnapshotType::TlTeam),
            'nextDeadlines' => (clone $deptSteps)->whereNotNull('current_due_at')
                ->whereIn('workflow_status', [WorkflowStatus::InProgress->value, WorkflowStatus::ChangesRequested->value])
                ->orderBy('current_due_at')->with('task:id,title,task_code')->limit(5)->get(),
        ]);
    }

    private function managerDashboard(User $user): View
    {
        $monthStart = now()->startOfMonth();

        return view('dashboard.manager', [
            'activeTasks' => Task::where('lifecycle_status', TaskLifecycle::Active->value)->count(),
            'overdueStepsCount' => TaskStep::where('deadline_status', DeadlineStatus::Overdue->value)->count(),
            'waitingAssignment' => TaskStep::where('workflow_status', WorkflowStatus::WaitingAssignment->value)->count(),
            'activeProjectsCount' => Project::where('status', ProjectStatus::Active->value)->count(),
            'overdueSteps' => TaskStep::where('deadline_status', DeadlineStatus::Overdue->value)
                ->with(['task:id,title,task_code', 'department:id,name'])->limit(10)->get(),
            'onHoldTasks' => Task::where('lifecycle_status', TaskLifecycle::OnHold->value)
                ->with('currentStep.department:id,name')->limit(10)->get(),
            'awaitingManagerReview' => TaskStep::where('workflow_status', WorkflowStatus::UnderReview->value)
                ->whereHas('activeAssignment', fn ($q) => $q->where('is_self_assigned', true))
                ->with(['task:id,title,task_code', 'department:id,name'])->limit(10)->get(),
            'departmentScores' => MonthlyPerformanceSnapshot::ofType(SnapshotType::Department)
                ->forMonth($monthStart)->with('department:id,name')->get(),
            'activeTempTls' => DepartmentLeadershipAssignment::currentlyActive()
                ->where('assignment_type', LeadershipType::Temporary->value)
                ->with(['user:id,full_name', 'department:id,name'])->get(),
            'activeProjects' => Project::where('status', ProjectStatus::Active->value)
                ->with('client:id,name')->latest('id')->limit(10)->get(),
        ]);
    }

    private function adminDashboard(User $user): View
    {
        return view('dashboard', [
            'user' => $user,
            'displayName' => LocalizedDemoData::userName($user),
            'roleLabel' => LocalizedDemoData::roleName($user),
            'demo' => LocalizedDemoData::dashboard(),
        ]);
    }

    private function currentMonthSnapshot(User $user, SnapshotType $type): ?MonthlyPerformanceSnapshot
    {
        return $user->performanceSnapshots()
            ->where('month_start', now()->startOfMonth()->toDateString())
            ->where('snapshot_type', $type->value)
            ->first();
    }
}
