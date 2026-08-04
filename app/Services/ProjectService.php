<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\RoleCode;
use App\Events\ProjectCancelled;
use App\Models\Client;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BRD §7.2 — project lifecycle (Active/OnHold/Completed/Cancelled) and participating
 * departments. Authorization is defined once in ProjectPolicy and enforced here via
 * Gate::forUser(), the same convention TaskWorkflowService uses.
 *
 * SCOPE NOTE: cancel() still flips the PROJECT's own status only — cascading cancel into
 * unfinished tasks is a separate authority question not part of this slice. hold()/
 * resume() DO cascade (Q23): every unfinished task that isn't already individually on
 * hold is paused through TaskWorkflowService::hold(), tagged with this project_holds
 * row, and resume() resumes exactly those — a task paused individually beforehand stays
 * paused after the project resumes.
 */
class ProjectService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly TaskWorkflowService $taskWorkflow,
    ) {}

    /**
     * @param  array{name: string, description?: ?string}  $attributes
     * @param  int[]  $departmentIds
     * @param  array<int, array{url: string, label?: ?string}>  $links
     */
    public function create(
        array $attributes,
        Client $client,
        array $departmentIds,
        array $links,
        User $actor,
    ): Project {
        Gate::forUser($actor)->authorize('create', Project::class);
        $this->assertDepartmentsAllowed($departmentIds, $actor);

        return DB::transaction(function () use ($attributes, $client, $departmentIds, $links, $actor): Project {
            $project = Project::create($attributes + [
                'client_id' => $client->id,
                'project_code' => $this->nextProjectCode(),
                'status' => ProjectStatus::Active->value,
                'created_by' => $actor->id,
                'started_at' => now(),
            ]);

            foreach (array_unique($departmentIds) as $departmentId) {
                $project->departments()->attach($departmentId, [
                    'is_active' => true,
                    'added_at' => now(),
                ]);
            }

            foreach ($links as $link) {
                $project->links()->create([
                    'url' => $link['url'],
                    'label' => $link['label'] ?? null,
                    'added_by' => $actor->id,
                ]);
            }

            $this->audit->log(
                action: 'project.created',
                entityType: 'project',
                entityId: $project->id,
                after: ['name' => $project->name, 'client_id' => $client->id, 'department_ids' => $departmentIds],
                actorId: $actor->id,
            );

            return $project->refresh();
        });
    }

    public function addDepartment(Project $project, Department $department, User $actor): Project
    {
        Gate::forUser($actor)->authorize('update', $project);
        $this->assertMutable($project);
        $this->assertDepartmentsAllowed([$department->id], $actor);

        if ($project->departments()->where('departments.id', $department->id)->exists()) {
            throw ValidationException::withMessages([
                'department_id' => __('This department already participates in the project.'),
            ]);
        }

        $project->departments()->attach($department->id, ['is_active' => true, 'added_at' => now()]);

        $this->audit->log(
            action: 'project.department_added',
            entityType: 'project',
            entityId: $project->id,
            after: ['department_id' => $department->id],
            actorId: $actor->id,
        );

        return $project->refresh();
    }

    public function removeDepartment(Project $project, Department $department, User $actor): Project
    {
        Gate::forUser($actor)->authorize('update', $project);
        $this->assertMutable($project);
        $this->assertDepartmentsAllowed([$department->id], $actor);

        $project->departments()->updateExistingPivot($department->id, [
            'is_active' => false,
            'removed_at' => now(),
        ]);

        $this->audit->log(
            action: 'project.department_removed',
            entityType: 'project',
            entityId: $project->id,
            after: ['department_id' => $department->id],
            actorId: $actor->id,
        );

        return $project->refresh();
    }

    public function complete(Project $project, User $actor): Project
    {
        Gate::forUser($actor)->authorize('complete', $project);

        $project->forceFill([
            'status' => ProjectStatus::Completed->value,
            'completed_at' => now(),
            'completed_by' => $actor->id,
        ])->save();

        $this->audit->log(
            action: 'project.completed',
            entityType: 'project',
            entityId: $project->id,
            actorId: $actor->id,
        );

        return $project->refresh();
    }

    public function cancel(Project $project, string $reason, User $actor): Project
    {
        Gate::forUser($actor)->authorize('cancel', $project);

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => __('A reason is required when cancelling a project.'),
            ]);
        }

        $project->forceFill([
            'status' => ProjectStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancelled_by' => $actor->id,
            'cancelled_reason' => $reason,
        ])->save();

        $this->audit->log(
            action: 'project.cancelled',
            entityType: 'project',
            entityId: $project->id,
            after: ['reason' => $reason],
            actorId: $actor->id,
        );

        $cancelled = $project->refresh();
        ProjectCancelled::dispatch($cancelled);

        return $cancelled;
    }

    public function hold(Project $project, string $reason, User $actor): Project
    {
        Gate::forUser($actor)->authorize('hold', $project);

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => __('A reason is required when placing a project on hold.'),
            ]);
        }

        return DB::transaction(function () use ($project, $reason, $actor): Project {
            $hold = $project->holds()->create([
                'created_by' => $actor->id,
                'reason' => $reason,
                'started_at' => now(),
            ]);

            $project->forceFill(['status' => ProjectStatus::OnHold->value])->save();

            $cascaded = 0;

            foreach ($project->unfinishedTasks() as $task) {
                if ($task->isOnHold() || $task->isClosed()) {
                    continue;
                }

                $this->taskWorkflow->hold($task, $actor, $reason, $hold);
                $cascaded++;
            }

            $this->audit->log(
                action: 'project.held',
                entityType: 'project',
                entityId: $project->id,
                after: ['reason' => $reason, 'cascaded_task_count' => $cascaded],
                actorId: $actor->id,
            );

            return $project->refresh();
        });
    }

    public function resume(Project $project, User $actor): Project
    {
        Gate::forUser($actor)->authorize('resume', $project);

        return DB::transaction(function () use ($project, $actor): Project {
            $hold = $project->openHold();

            $hold?->forceFill(['ended_at' => now(), 'resumed_by' => $actor->id])->save();

            $project->forceFill(['status' => ProjectStatus::Active->value])->save();

            if ($hold !== null) {
                foreach ($hold->taskHolds()->whereNull('ended_at')->get() as $taskHold) {
                    $this->taskWorkflow->resume($taskHold->task, $actor);
                }
            }

            $this->audit->log(
                action: 'project.resumed',
                entityType: 'project',
                entityId: $project->id,
                actorId: $actor->id,
            );

            return $project->refresh();
        });
    }

    /** BRD §7.2 — a TL may only pick their own department plus Admin-allowed targets. */
    private function assertDepartmentsAllowed(array $departmentIds, User $actor): void
    {
        if (! $actor->hasRole(RoleCode::TeamLeader)) {
            return;
        }

        $allowed = array_merge(
            [$actor->department_id],
            Department::find($actor->department_id)?->allowedNextDepartmentIds() ?? [],
        );

        $disallowed = array_diff($departmentIds, $allowed);

        if ($disallowed !== []) {
            throw ValidationException::withMessages([
                'department_ids' => __('You may only add your own department or departments you are allowed to route to.'),
            ]);
        }
    }

    private function assertMutable(Project $project): void
    {
        if ($project->isClosed()) {
            throw ValidationException::withMessages([
                'project' => __('A completed or cancelled project can no longer be modified.'),
            ]);
        }
    }

    /** PRJ-YYYY-NNNN — same collision-retry approach as TaskWorkflowService::nextTaskCode(). */
    private function nextProjectCode(): string
    {
        $year = now()->year;
        $prefix = "PRJ-{$year}-";

        $last = Project::query()
            ->where('project_code', 'like', $prefix.'%')
            ->orderByDesc('project_code')
            ->value('project_code');

        $next = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        for ($attempt = 0; $attempt < 50; $attempt++, $next++) {
            $candidate = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);

            if (! Project::query()->where('project_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages(['project_code' => __('Could not allocate a project code.')]);
    }
}
