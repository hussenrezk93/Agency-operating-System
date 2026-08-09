<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\RoleCode;
use App\Enums\WorkflowStatus;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The write side of the assistant's tools. Every propose* method validates and
 * authorizes exactly like the matching FormRequest/Policy the real screen uses,
 * then caches the already-resolved arguments under a random token instead of
 * executing anything. confirmAction() is the only method that ever mutates data —
 * it re-authorizes against fresh DB state (things may have changed since propose)
 * before calling the same Service the real controller calls. This two-step shape
 * exists specifically so a write action always spans two separate user messages
 * (propose, then an explicit human confirmation) rather than firing inside one
 * model turn — see GroqService's system prompt.
 */
class AssistantActionService
{
    private const CACHE_PREFIX = 'assistant-pending:';

    private const LATEST_PREFIX = 'assistant-latest-pending:';

    private const TTL_MINUTES = 10;

    public function __construct(
        private readonly UserService $userService,
        private readonly DepartmentService $departmentService,
        private readonly TemporaryLeadershipService $temporaryLeadershipService,
        private readonly TaskWorkflowService $taskWorkflowService,
    ) {}

    /** @return array{proposed: true, preview: array<string, mixed>}|array{error: string} */
    public function proposeCreateUser(User $actor, array $args): array
    {
        $role = RoleCode::tryFrom((string) ($args['role'] ?? ''));

        if ($role === null) {
            return ['error' => 'Unknown role. Use admin, manager, tl, or employee.'];
        }

        if ($actor->cannot('createWithRole', [User::class, $role])) {
            return ['error' => 'Not authorized to create a user with that role.'];
        }

        $needsDepartment = in_array($role, [RoleCode::TeamLeader, RoleCode::Employee], true);
        $department = null;

        if ($needsDepartment) {
            $department = $this->findDepartmentByName((string) ($args['department_name'] ?? ''));

            if ($department === null) {
                return ['error' => 'Department not found — required for a Team Leader or Employee.'];
            }
        }

        try {
            $data = Validator::make($args, [
                'full_name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:255', 'unique:users,username'],
                'personal_email' => ['required', 'email', 'max:255', 'unique:users,personal_email'],
            ])->validate();
        } catch (ValidationException $e) {
            return ['error' => $e->validator->errors()->first()];
        }

        $this->store($actor, 'create_user', [
            'full_name' => $data['full_name'],
            'username' => $data['username'],
            'personal_email' => $data['personal_email'],
            'role' => $role->value,
            'department_id' => $department?->id,
        ]);

        return ['proposed' => true, 'preview' => [
            'action' => 'Create a new user',
            'full_name' => $data['full_name'],
            'username' => $data['username'],
            'personal_email' => $data['personal_email'],
            'role' => $role->value,
            'department' => $department?->name,
        ]];
    }

    /** @return array{proposed: true, preview: array<string, mixed>}|array{error: string} */
    public function proposeCreateDepartment(User $actor, array $args): array
    {
        if ($actor->cannot('create', Department::class)) {
            return ['error' => 'Not authorized to create a department.'];
        }

        $leader = $this->findUserByName((string) ($args['primary_leader_name'] ?? ''));

        if ($leader === null) {
            return ['error' => 'Could not find exactly one user matching that leader name.'];
        }

        if ($leader->substantiveRoleCode() !== RoleCode::TeamLeader) {
            return ['error' => 'The primary leader must be a Team Leader.'];
        }

        try {
            $data = Validator::make($args, [
                'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            ])->validate();
        } catch (ValidationException $e) {
            return ['error' => $e->validator->errors()->first()];
        }

        $this->store($actor, 'create_department', [
            'name' => $data['name'],
            'primary_leader_id' => $leader->id,
        ]);

        return ['proposed' => true, 'preview' => [
            'action' => 'Create a new department',
            'name' => $data['name'],
            'primary_leader' => $leader->full_name,
        ]];
    }

    /** @return array{proposed: true, preview: array<string, mixed>}|array{error: string} */
    public function proposeAssignTemporaryTl(User $actor, array $args): array
    {
        if ($actor->cannot('appoint', DepartmentLeadershipAssignment::class)) {
            return ['error' => 'Not authorized to appoint a temporary team leader.'];
        }

        $department = $this->findDepartmentByName((string) ($args['department_name'] ?? ''));
        $candidate = $this->findUserByName((string) ($args['candidate_name'] ?? ''));

        if ($department === null || $candidate === null) {
            return ['error' => 'Could not resolve the department or the candidate by name.'];
        }

        try {
            $data = Validator::make($args, [
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'reason' => ['required', 'string', 'min:5', 'max:500'],
            ])->validate();
        } catch (ValidationException $e) {
            return ['error' => $e->validator->errors()->first()];
        }

        $this->store($actor, 'assign_temporary_tl', [
            'department_id' => $department->id,
            'user_id' => $candidate->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'],
        ]);

        return ['proposed' => true, 'preview' => [
            'action' => 'Appoint a temporary team leader',
            'department' => $department->name,
            'candidate' => $candidate->full_name,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'],
        ]];
    }

    /** @return array{proposed: true, preview: array<string, mixed>}|array{error: string} */
    public function proposeCreateTask(User $actor, array $args): array
    {
        if ($actor->cannot('create', Task::class)) {
            return ['error' => 'Not authorized to create a task.'];
        }

        $department = $this->findDepartmentByName((string) ($args['first_department_name'] ?? ''));

        if ($department === null) {
            return ['error' => 'Department not found.'];
        }

        try {
            $data = Validator::make($args, [
                'title' => ['required', 'string', 'max:255'],
                'brief' => ['required', 'string'],
            ])->validate();
        } catch (ValidationException $e) {
            return ['error' => $e->validator->errors()->first()];
        }

        $priority = Priority::tryFrom(strtolower((string) ($args['priority'] ?? '')));
        $project = empty($args['project_name']) ? null : Project::where('name', 'like', '%'.$args['project_name'].'%')->first();

        $this->store($actor, 'create_task', [
            'title' => $data['title'],
            'brief' => $data['brief'],
            'first_department_id' => $department->id,
            'priority' => $priority?->value,
            'project_id' => $project?->id,
        ]);

        return ['proposed' => true, 'preview' => [
            'action' => 'Create a new task',
            'title' => $data['title'],
            'department' => $department->name,
            'priority' => $priority?->value ?? 'medium (default)',
            'project' => $project?->name ?? 'none',
            'note' => 'Whether this department is actually allowed to receive the task from you is checked when you confirm.',
        ]];
    }

    /** @return array{proposed: true, preview: array<string, mixed>}|array{error: string} */
    public function proposeAssignTaskStep(User $actor, array $args): array
    {
        $task = Task::where('task_code', (string) ($args['task_code'] ?? ''))->first();
        $step = $task?->currentStep;

        if ($step === null || $step->workflow_status !== WorkflowStatus::WaitingAssignment) {
            return ['error' => 'This task is not currently waiting for assignment.'];
        }

        if ($actor->cannot('assign', $step)) {
            return ['error' => 'Not authorized to assign this task — it belongs to another department.'];
        }

        $assignee = $this->findUserByName((string) ($args['assignee_name'] ?? ''));

        if ($assignee === null) {
            return ['error' => 'Could not find exactly one user matching that assignee name.'];
        }

        try {
            $data = Validator::make($args, [
                'start_date' => ['required', 'date'],
                'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            ])->validate();
        } catch (ValidationException $e) {
            return ['error' => $e->validator->errors()->first()];
        }

        $this->store($actor, 'assign_task_step', [
            'task_step_id' => $step->id,
            'assignee_id' => $assignee->id,
            'start_date' => $data['start_date'],
            'due_date' => $data['due_date'],
        ]);

        return ['proposed' => true, 'preview' => [
            'action' => 'Assign a task',
            'task_code' => $task->task_code,
            'task_title' => $task->title,
            'assignee' => $assignee->full_name,
            'start_date' => $data['start_date'],
            'due_date' => $data['due_date'],
        ]];
    }

    /**
     * The only method that mutates anything. Takes no arguments from the model —
     * it always executes $actor's OWN most recently proposed action (see store()'s
     * docblock for why: a token can't reliably round-trip through the model across
     * two separate chat turns). Re-resolves every model fresh from the DB and
     * re-authorizes before executing — state may have changed since propose.
     *
     * @return array{result: array<string, mixed>}|array{error: string}
     */
    public function confirmAction(User $actor): array
    {
        $latestKey = self::LATEST_PREFIX.$actor->id;
        $token = Cache::get($latestKey);
        Cache::forget($latestKey);

        $pending = $token === null ? null : Cache::get(self::CACHE_PREFIX.$token);
        Cache::forget(self::CACHE_PREFIX.$token);

        if (! is_array($pending) || ($pending['actor_id'] ?? null) !== $actor->id) {
            return ['error' => 'There is no pending action to confirm — ask again with the details.'];
        }

        $args = $pending['args'];

        try {
            $result = match ($pending['tool']) {
                'create_user' => $this->executeCreateUser($actor, $args),
                'create_department' => $this->executeCreateDepartment($actor, $args),
                'assign_temporary_tl' => $this->executeAssignTemporaryTl($actor, $args),
                'create_task' => $this->executeCreateTask($actor, $args),
                'assign_task_step' => $this->executeAssignTaskStep($actor, $args),
                default => ['error' => 'Unknown pending action.'],
            };
        } catch (ValidationException $e) {
            return ['error' => $e->validator->errors()->first()];
        } catch (Throwable $e) {
            report($e);

            return ['error' => 'Something went wrong performing this action. Nothing was changed.'];
        }

        return ['result' => $result];
    }

    private function executeCreateUser(User $actor, array $args): array
    {
        $role = RoleCode::from($args['role']);

        if ($actor->cannot('createWithRole', [User::class, $role])) {
            throw ValidationException::withMessages(['role' => 'No longer authorized to create this user.']);
        }

        $department = $args['department_id'] === null ? null : Department::find($args['department_id']);
        $created = $this->userService->create($role, [
            'full_name' => $args['full_name'],
            'username' => $args['username'],
            'personal_email' => $args['personal_email'],
        ], $department, $actor);

        return [
            'created' => true,
            'username' => $created['user']->username,
            'temporary_password' => $created['temporary_password'],
        ];
    }

    private function executeCreateDepartment(User $actor, array $args): array
    {
        if ($actor->cannot('create', Department::class)) {
            throw ValidationException::withMessages(['name' => 'No longer authorized to create a department.']);
        }

        $leader = User::findOrFail($args['primary_leader_id']);
        $department = $this->departmentService->createWithPrimaryLeader($args['name'], $leader, $actor);

        return ['created' => true, 'department' => $department->name];
    }

    private function executeAssignTemporaryTl(User $actor, array $args): array
    {
        if ($actor->cannot('appoint', DepartmentLeadershipAssignment::class)) {
            throw ValidationException::withMessages(['reason' => 'No longer authorized to appoint a temporary leader.']);
        }

        $department = Department::findOrFail($args['department_id']);
        $candidate = User::findOrFail($args['user_id']);

        $this->temporaryLeadershipService->appoint(
            $department,
            $candidate,
            Carbon::parse($args['start_date']),
            Carbon::parse($args['end_date']),
            $args['reason'],
            $actor,
        );

        return ['created' => true, 'department' => $department->name, 'candidate' => $candidate->full_name];
    }

    private function executeCreateTask(User $actor, array $args): array
    {
        $task = $this->taskWorkflowService->createTask($actor, [
            'title' => $args['title'],
            'brief' => $args['brief'],
            'first_department_id' => $args['first_department_id'],
            'priority' => $args['priority'],
            'project_id' => $args['project_id'],
        ]);

        return ['created' => true, 'task_code' => $task->task_code];
    }

    private function executeAssignTaskStep(User $actor, array $args): array
    {
        $step = TaskStep::findOrFail($args['task_step_id']);

        if ($step->workflow_status !== WorkflowStatus::WaitingAssignment) {
            throw ValidationException::withMessages(['task_code' => 'This task is no longer waiting for assignment.']);
        }

        $assignee = User::findOrFail($args['assignee_id']);
        $this->taskWorkflowService->assign($step, $actor, $assignee, $args['start_date'], $args['due_date']);

        return ['created' => true, 'task_code' => $step->task->task_code, 'assignee' => $assignee->full_name];
    }

    /**
     * The token itself never needs to round-trip through the model — the widget's
     * chat history only carries plain reply text between turns, not tool-call
     * internals, so a token the model tried to echo back on the user's confirming
     * message would already be lost. Instead this also points a per-actor "latest
     * pending action" pointer at the token, and confirmAction() just follows that —
     * which also means a second, different proposal naturally supersedes an
     * unconfirmed first one, matching what a human would expect.
     */
    private function store(User $actor, string $tool, array $args): string
    {
        $token = Str::random(40);
        $ttl = now()->addMinutes(self::TTL_MINUTES);

        Cache::put(self::CACHE_PREFIX.$token, [
            'actor_id' => $actor->id,
            'tool' => $tool,
            'args' => $args,
        ], $ttl);

        Cache::put(self::LATEST_PREFIX.$actor->id, $token, $ttl);

        return $token;
    }

    /** Must resolve to exactly one match — ambiguous or missing both fail closed. */
    private function findUserByName(string $name): ?User
    {
        $matches = User::where('full_name', 'like', '%'.$name.'%')->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function findDepartmentByName(string $name): ?Department
    {
        $matches = Department::where('name', 'like', '%'.$name.'%')->where('is_active', true)->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
