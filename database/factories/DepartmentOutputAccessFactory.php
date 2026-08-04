<?php

namespace Database\Factories;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\DepartmentOutputAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DepartmentOutputAccess> */
class DepartmentOutputAccessFactory extends Factory
{
    protected $model = DepartmentOutputAccess::class;

    public function definition(): array
    {
        return [
            'viewer_department_id' => Department::factory(),
            'source_department_id' => Department::factory(),
            'scope' => 'all_outputs',
            'is_allowed' => true,
            'updated_by' => User::factory()->role(RoleCode::Admin),
        ];
    }
}
