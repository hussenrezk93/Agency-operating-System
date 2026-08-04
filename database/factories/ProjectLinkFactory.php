<?php

namespace Database\Factories;

use App\Enums\RoleCode;
use App\Models\Project;
use App\Models\ProjectLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectLink> */
class ProjectLinkFactory extends Factory
{
    protected $model = ProjectLink::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'added_by' => User::factory()->role(RoleCode::Manager),
            'url' => 'https://drive.google.com/drive/folders/'.fake()->lexify('??????????'),
            'label' => fake()->words(2, true),
        ];
    }
}
