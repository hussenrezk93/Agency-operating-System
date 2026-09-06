<?php

namespace App\Services;

use App\Enums\DeadlineStatus;
use App\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskPresenter;
use Illuminate\Database\Eloquent\Builder;

/**
 * The only data surface the assistant's AI model can reach. Every method here is
 * scoped to $actor exactly like the equivalent human-facing screen — the "my tasks"
 * query mirrors MyTasksController::index's per-role filters, a single-task lookup
 * always goes through TaskPolicy::view, reports go through UserPolicy::viewPerformance,
 * and the audit-log summary goes through AuditLogPolicy::viewAny — so the model can
 * never see more than the asking user already can on the real screens. Called from
 * GroqService.
 */
class AssistantToolService
{
    private const SUSPICIOUS_ACTIONS = ['auth.login_failed', 'auth.login_throttled', 'auth.login_blocked'];

    public function __construct(private readonly PerformanceService $performance) {}

    /** @return array<int, array<string, mixed>> */
    public function myTasks(User $actor): array
    {
        if ($actor->cannot('viewAny', Task::class)) {
            return [];
        }

        return $this->scopeTasksToActor(Task::query(), $actor)
            ->with(['currentStep.department:id,name'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (Task $task) => $this->summarize($task))
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function taskStatus(User $actor, string $taskCode): ?array
    {
        $task = Task::where('task_code', $taskCode)->first();

        if (! $task || ! $actor->can('view', $task)) {
            return null;
        }

        return $this->summarize($task);
    }

    /**
     * The plain company-directory-level list — every department, active or not,
     * with its current effective leader. Not scoped to $actor: department names
     * and who leads them aren't sensitive (already visible in dropdowns/directories
     * throughout the app), unlike get_allowed_departments_for_task, which is
     * deliberately narrower (only what a TL specifically can route a NEW task to
     * right now, so it excludes inactive departments and isn't offered to
     * Admin/Manager at all).
     *
     * @return array<int, array<string, mixed>>
     */
    public function allDepartments(User $actor): array
    {
        return Department::orderBy('name')->get()->map(fn (Department $d) => [
            'name' => $d->name,
            'is_active' => $d->is_active,
            'leader' => $d->effectiveLeader()?->full_name,
        ])->all();
    }

    /**
     * Manager: any named department. Team Leader: always their OWN department,
     * regardless of what name was asked for — this tool is only ever offered to
     * Manager/TL (see GroqService::toolDefinitions), so any other role never reaches
     * here.
     *
     * @return array<string, mixed>
     */
    public function departmentReport(User $actor, ?string $departmentName): array
    {
        if ($actor->hasRole(RoleCode::TeamLeader)) {
            $department = $actor->department;
        } else {
            $department = $departmentName === null ? null : $this->findDepartmentByName($departmentName);
        }

        if ($department === null) {
            return ['error' => 'Department not found.'];
        }

        return [
            'department' => $department->name,
            ...$this->performance->calculateForDepartment($department, now()->startOfMonth()),
        ];
    }

    /**
     * Resolved by name, then gated by the SAME policy the real performance page
     * uses — an Employee can only ever reach their own row (actor.is(subject)),
     * a TL only same-department employees, a Manager anyone.
     *
     * @return array<string, mixed>
     */
    public function employeeReport(User $actor, string $employeeName): array
    {
        $subject = $this->findUserByName($employeeName);

        if ($subject === null || ! $actor->can('viewPerformance', $subject)) {
            return ['error' => 'Employee not found or not accessible to this user.'];
        }

        return [
            'employee' => $subject->full_name,
            ...$this->performance->calculateForUser($subject, now()->startOfMonth()),
        ];
    }

    /**
     * Same role-scoping as myTasks(), narrowed to steps whose cached deadline_status
     * is DueSoon/Overdue (the same cached column every dashboard reads — see
     * DeadlineService, which is the only writer of these two values).
     *
     * @return array<int, array<string, mixed>>
     */
    public function dueOrOverdueTasks(User $actor): array
    {
        if ($actor->cannot('viewAny', Task::class)) {
            return [];
        }

        $statuses = [DeadlineStatus::DueSoon->value, DeadlineStatus::Overdue->value];

        return $this->scopeTasksToActor(Task::query(), $actor)
            ->whereHas('steps', fn (Builder $b) => $b->whereIn('deadline_status', $statuses))
            ->with(['currentStep.department:id,name'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (Task $task) => $this->summarize($task))
            ->all();
    }

    /**
     * Which departments this actor could pick as a new task's FIRST department right
     * now — the exact same restriction TaskController::create() applies (a Team
     * Leader is limited to their own department plus any department their own has an
     * active routing permission toward; anyone else sees every active department).
     * Without this, the model had no real data for "which department can I route
     * to" and would guess — this is what propose_create_task's confirm step actually
     * checks, so a name returned here is guaranteed to be accepted.
     *
     * @return array<int, string>
     */
    public function allowedDepartmentsForTask(User $actor): array
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        if ($actor->hasRole(RoleCode::TeamLeader)) {
            $allowed = array_merge(
                [$actor->department_id],
                $actor->department?->allowedNextDepartmentIds() ?? [],
            );
            $departments = $departments->whereIn('id', $allowed)->values();
        }

        return $departments->pluck('name')->all();
    }

    /**
     * Admin-only heuristic over the append-only audit log: recent failed/throttled/
     * blocked logins, grouped by IP + attempted username. There's no precomputed
     * "suspicious" flag anywhere — this is deliberately a fresh, simple heuristic
     * rather than reusing anything, since nothing else in the app does this today.
     *
     * @return array<string, mixed>
     */
    public function suspiciousActivity(User $actor): array
    {
        if ($actor->cannot('viewAny', AuditLog::class)) {
            return ['error' => 'Not authorized.'];
        }

        $entries = AuditLog::query()
            ->whereIn('action', self::SUSPICIOUS_ACTIONS)
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('created_at')
            ->limit(200)
            ->get();

        $grouped = $entries
            ->groupBy(fn (AuditLog $log) => ($log->ip_address ?? 'unknown').'|'.($log->metadata['after']['username_attempted'] ?? 'unknown'))
            ->map(function ($group, string $key) {
                [$ip, $username] = explode('|', $key, 2);

                return [
                    'ip_address' => $ip,
                    'username_attempted' => $username,
                    'attempts' => $group->count(),
                    'actions' => $group->pluck('action')->unique()->values()->all(),
                    'last_seen' => $group->first()->created_at?->toDateTimeString(),
                ];
            })
            ->sortByDesc('attempts')
            ->values()
            ->take(15);

        return [
            'window' => 'last 7 days',
            'total_events' => $entries->count(),
            'top_offenders' => $grouped->all(),
        ];
    }

    /** Employee: their own assignments. TL: their own department. Manager: everyone. */
    private function scopeTasksToActor(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole(RoleCode::Employee)) {
            return $query->whereHas('steps.assignments', fn (Builder $b) => $b->where('assignee_id', $actor->id));
        }

        if ($actor->hasRole(RoleCode::TeamLeader)) {
            return $query->whereHas('steps', fn (Builder $b) => $b->where('department_id', $actor->department_id));
        }

        return $query;
    }

    /** Must resolve to exactly one match — ambiguous or missing both fail closed. */
    private function findUserByName(string $name): ?User
    {
        $matches = User::where('full_name', 'like', '%'.$name.'%')->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function findDepartmentByName(string $name): ?Department
    {
        $matches = Department::where('name', 'like', '%'.$name.'%')->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** @return array<string, mixed> */
    private function summarize(Task $task): array
    {
        $step = $task->currentStep;

        return [
            'task_code' => $task->task_code,
            'title' => $task->title,
            'lifecycle_status' => TaskPresenter::lifecycleBadge($task->lifecycle_status)['label'],
            'priority' => TaskPresenter::priorityTag($task->priority)['label'],
            'current_step' => $step === null ? null : [
                'department' => $step->department?->name,
                'workflow_status' => TaskPresenter::workflowBadge($step->workflow_status, $step->activeAssignment?->is_self_assigned)['label'],
                'deadline_status' => TaskPresenter::deadlineBadge($step->deadline_status)['label'],
                'assignee' => $step->assignee()?->full_name,
                'due_at' => $step->current_due_at?->toDateString(),
            ],
        ];
    }
}
