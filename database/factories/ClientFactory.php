<?php

namespace Database\Factories;

use App\Enums\RoleCode;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
            'created_by' => User::factory()->role(RoleCode::Manager),
        ];
    }
}
