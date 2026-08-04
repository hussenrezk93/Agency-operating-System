<?php

namespace Database\Factories;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\DepartmentRoute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DepartmentRoute> */
class DepartmentRouteFactory extends Factory
{
    protected $model = DepartmentRoute::class;

    public function definition(): array
    {
        return [
            'from_department_id' => Department::factory(),
            'to_department_id' => Department::factory(),
            'is_allowed' => true,
            'updated_by' => User::factory()->role(RoleCode::Admin),
        ];
    }
}
