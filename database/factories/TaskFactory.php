<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\RoleCode;
use App\Enums\TaskLifecycle;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'task_code' => 'TSK-2026-'.fake()->unique()->numerify('#####'),
            'project_id' => null,                       // standalone by default (BRD §8)
            'title' => fake()->catchPhrase(),
            'brief' => fake()->paragraph(),
            'priority' => Priority::Medium->value,
            'lifecycle_status' => TaskLifecycle::Active->value,
            'created_by' => User::factory()->role(RoleCode::Manager),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['lifecycle_status' => TaskLifecycle::Draft->value]);
    }

    public function urgent(): static
    {
        return $this->state(fn () => ['priority' => Priority::Urgent->value]);
    }

    public function inProject(Project $project): static
    {
        return $this->state(fn () => ['project_id' => $project->id]);
    }

    public function completed(User $by): static
    {
        return $this->state(fn () => [
            'lifecycle_status' => TaskLifecycle::Completed->value,
            'completed_at' => now(),
            'completed_by' => $by->id,
        ]);
    }

    public function cancelled(User $by, string $reason = 'Client dropped the request'): static
    {
        return $this->state(fn () => [
            'lifecycle_status' => TaskLifecycle::Cancelled->value,
            'cancelled_at' => now(),
            'cancelled_by' => $by->id,
            'cancelled_reason' => $reason,
        ]);
    }
}
