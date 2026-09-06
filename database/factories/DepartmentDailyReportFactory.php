<?php

namespace Database\Factories;

use App\Enums\DepartmentReportType;
use App\Models\Department;
use App\Models\DepartmentDailyReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DepartmentDailyReport> */
class DepartmentDailyReportFactory extends Factory
{
    protected $model = DepartmentDailyReport::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'report_date' => now()->toDateString(),
            'type' => DepartmentReportType::Summary->value,
            'auto_summary' => [],
            'created_at' => now(),
        ];
    }

    public function moderatorHandoff(): static
    {
        return $this->state(fn () => ['type' => DepartmentReportType::ModeratorHandoff->value, 'auto_summary' => null]);
    }

    public function submitted(User $by, bool $late = false): static
    {
        return $this->state(fn () => [
            'details' => 'Test details',
            'submitted_at' => now(),
            'submitted_by' => $by->id,
            'is_late' => $late,
        ]);
    }
}
