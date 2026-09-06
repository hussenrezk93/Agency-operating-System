<?php

namespace Tests\Concerns;

use App\Enums\ActivationState;
use App\Enums\LeadershipType;
use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\DepartmentLeadershipAssignment;
use App\Models\DepartmentRoute;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\TaskStepAssignment;
use App\Models\User;
use App\Services\TaskRoutingService;
use App\Services\TaskWorkflowService;
use App\Services\TemporaryLeadershipService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

/**
 * Scenario builders for the Phase 1B slice 2 tests.
 *
 * Leadership is created through the SAME resolution path production uses — a real
 * `department_leadership_assignments` row that satisfies scopeCurrentlyActive(), and the
 * real TemporaryLeadershipService for temporary cover. Nothing here fakes
 * Department::effectiveLeader(); if that resolution breaks, these tests break, which is
 * the point.
 */
trait BuildsWorkflowScenarios
{
    private ?User $systemManager = null;

    protected function seedRoles(): void
    {
        $this->seed(RoleSeeder::class);
    }

    protected function workflow(): TaskWorkflowService
    {
        return app(TaskWorkflowService::class);
    }

    protected function routing(): TaskRoutingService
    {
        return app(TaskRoutingService::class);
    }

    protected function makeDepartment(?string $name = null): Department
    {
        return Department::factory()->create(array_filter(['name' => $name]));
    }

    protected function makeManager(): User
    {
        return User::factory()->role(RoleCode::Manager)->create();
    }

    protected function makeAdmin(): User
    {
        return User::factory()->role(RoleCode::Admin)->create();
    }

    /** The account every fixture uses as `assigned_by`, so no test needs to invent one. */
    protected function systemManager(): User
    {
        return $this->systemManager ??= $this->makeManager();
    }

    /** A primary Team Leader with a currently-active assignment (the production shape). */
    protected function makeTeamLeader(Department $department): User
    {
        $leader = User::factory()
            ->role(RoleCode::TeamLeader)
            ->inDepartment($department)
            ->create();

        DepartmentLeadershipAssignment::create([
            'department_id' => $department->id,
            'user_id' => $leader->id,
            'assignment_type' => LeadershipType::Primary->value,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => null,
            'is_active' => true,
            'activation_state' => ActivationState::Active->value,
            'assigned_by' => $this->systemManager()->id,
        ]);

        return $leader->refresh();
    }

    protected function makeEmployee(Department $department): User
    {
        return User::factory()
            ->role(RoleCode::Employee)
            ->inDepartment($department)
            ->create();
    }

    /**
     * Q2/Q11/Q14 — real temporary cover: the employee's effective role becomes Team
     * Leader and the primary Team Leader becomes view-only for the period.
     */
    protected function appointTemporaryLeader(Department $department, User $candidate): User
    {
        app(TemporaryLeadershipService::class)->appoint(
            $department,
            $candidate,
            Carbon::today(),
            Carbon::today()->addWeek(),
            'Primary Team Leader on annual leave',
            $this->systemManager(),
        );

        return $candidate->refresh();
    }

    /** BRD §15 — an Admin-managed routing rule. */
    protected function allowRoute(Department $from, Department $to, bool $allowed = true): DepartmentRoute
    {
        return DepartmentRoute::create([
            'from_department_id' => $from->id,
            'to_department_id' => $to->id,
            'is_allowed' => $allowed,
            'updated_by' => $this->makeAdmin()->id,
        ]);
    }

    protected function addDepartmentToProject(Project $project, Department $department): void
    {
        $project->departments()->attach($department->id, ['is_active' => true, 'added_at' => now()]);
    }

    // ------------------------------------------------------------- task states

    /** A brand-new task sitting on its first department in Waiting Assignment. */
    protected function newTask(Department $first, User $creator, array $overrides = []): Task
    {
        return $this->workflow()->createTask($creator, array_merge([
            'title' => 'Launch teaser video',
            'brief' => 'Cut a 30 second teaser for the campaign.',
            'first_department_id' => $first->id,
        ], $overrides));
    }

    /**
     * A task whose first step is assigned and In Progress.
     *
     * @return array{0: Task, 1: TaskStep, 2: TaskStepAssignment}
     */
    protected function taskInProgress(Department $department, User $leader, User $assignee, ?User $creator = null, array $overrides = []): array
    {
        $task = $this->newTask($department, $creator ?? $leader, $overrides);
        $step = $task->currentStep;

        $assignment = $this->workflow()->assign(
            $step,
            $leader,
            $assignee,
            now()->toDateString(),
            now()->addDays(3)->toDateString(),
        );

        return [$task->refresh(), $step->refresh(), $assignment];
    }

    /**
     * A task whose first step has been submitted and is awaiting review.
     *
     * @return array{0: Task, 1: TaskStep, 2: TaskStepAssignment}
     */
    protected function taskUnderReview(Department $department, User $leader, User $assignee, ?User $creator = null, array $overrides = []): array
    {
        [$task, $step, $assignment] = $this->taskInProgress($department, $leader, $assignee, $creator, $overrides);

        $this->workflow()->addOutput($step, $assignee, 'https://drive.example.com/teaser-v1');
        $this->workflow()->submit($step, $assignee);

        return [$task->refresh(), $step->refresh(), $assignment->refresh()];
    }

    /**
     * A task whose first step is approved and ready to route onward or finish.
     *
     * @return array{0: Task, 1: TaskStep}
     */
    protected function taskApproved(Department $department, User $leader, User $assignee, ?User $creator = null, array $overrides = []): array
    {
        [$task, $step] = $this->taskUnderReview($department, $leader, $assignee, $creator, $overrides);

        // Product decision 2026-09 — a step needs BOTH a TL-stage AND a Manager-stage
        // approval to truly reach Approved. Q12 still applies at the TL stage: a
        // self-assigned step is reviewed by the Manager there too, same as always.
        $tlReviewer = $step->fresh()->isSelfAssigned() ? $this->systemManager() : $leader;
        $this->workflow()->approve($step, $tlReviewer);
        $this->workflow()->approve($step->refresh(), $this->systemManager());

        return [$task->refresh(), $step->refresh()];
    }
}
