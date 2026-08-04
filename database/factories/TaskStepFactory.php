<?php

namespace Database\Factories;

use App\Enums\DeadlineStatus;
use App\Enums\WorkflowStatus;
use App\Models\Department;
use App\Models\Task;
use App\Models\TaskStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaskStep> */
class TaskStepFactory extends Factory
{
    protected $model = TaskStep::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'department_id' => Department::factory(),
            'sequence_no' => 1,
            'workflow_status' => WorkflowStatus::WaitingAssignment->value,
            'deadline_status' => DeadlineStatus::NotStarted->value,
        ];
    }

    public function status(WorkflowStatus $status): static
    {
        return $this->state(fn () => ['workflow_status' => $status->value]);
    }

    public function due(string $date): static
    {
        return $this->state(fn () => [
            'current_start_date' => now()->toDateString(),
            'current_due_at' => $date.' 23:59:00',
        ]);
    }
}
