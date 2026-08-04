<?php

namespace Database\Factories;

use App\Enums\SnapshotType;
use App\Models\Department;
use App\Models\MonthlyPerformanceSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MonthlyPerformanceSnapshot> */
class MonthlyPerformanceSnapshotFactory extends Factory
{
    protected $model = MonthlyPerformanceSnapshot::class;

    /**
     * Several CHECK constraints must hold together, so the counts are derived rather than
     * randomised independently: on_time + overdue <= due, and score is NULL exactly when
     * due is 0 (BRD §17 — N/A, not zero).
     */
    public function definition(): array
    {
        $due = fake()->numberBetween(4, 20);
        $onTime = fake()->numberBetween(0, $due);

        return [
            'user_id' => User::factory(),
            'department_id' => null,
            'month_start' => now()->startOfMonth()->toDateString(),
            'due_steps' => $due,
            'on_time_steps' => $onTime,
            'overdue_steps' => $due - $onTime,
            'score' => MonthlyPerformanceSnapshot::calculateScore($due, $onTime),
            'snapshot_type' => SnapshotType::Employee->value,
            'calculated_at' => now(),
        ];
    }

    /** BRD §17 — nothing due means N/A, which is NULL and never 0. */
    public function notApplicable(): static
    {
        return $this->state(fn (): array => [
            'due_steps' => 0,
            'on_time_steps' => 0,
            'overdue_steps' => 0,
            'score' => null,
        ]);
    }

    public function perfect(): static
    {
        return $this->state(fn (): array => [
            'due_steps' => 10,
            'on_time_steps' => 10,
            'overdue_steps' => 0,
            'score' => 100,
        ]);
    }

    /** A department snapshot names a department and NOT a user (`mps_subject_check`). */
    public function forDepartment(?Department $department = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'department_id' => $department?->id ?? Department::factory(),
            'snapshot_type' => SnapshotType::Department->value,
        ]);
    }

    public function ofType(SnapshotType $type): static
    {
        return $this->state(fn (): array => ['snapshot_type' => $type->value]);
    }
}
