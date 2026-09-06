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
            // fake()->phoneNumber() sometimes produces an extension letter (e.g. "x123"),
            // which fails the same digits-only regex the store/update requests enforce
            // (StoreClientRequest et al.) — numerify() guarantees a format that passes.
            'phone' => '+20'.fake()->numerify('##########'),
            'status' => 'active',
            'created_by' => User::factory()->role(RoleCode::Manager),
        ];
    }
}
