<?php

namespace Database\Factories;

use App\Enums\RoleCode;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'project_code' => 'PRJ-2026-'.fake()->unique()->numerify('####'),
            'name' => fake()->catchPhrase(),
            'status' => 'active',
            'created_by' => User::factory()->role(RoleCode::Manager),
        ];
    }
}
