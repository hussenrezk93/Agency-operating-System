<?php

namespace Database\Factories;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'role_id' => fn () => Role::firstOrCreate(
                ['code' => RoleCode::Employee->value],
                ['name' => RoleCode::Employee->label()]
            )->id,
            'department_id' => null,
            'full_name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password_hash' => Hash::make('Demo123!'),
            'status' => 'active',
            'must_change_password' => false,
            'personal_email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ];
    }

    public function role(RoleCode $code): static
    {
        return $this->state(fn () => [
            'role_id' => Role::firstOrCreate(['code' => $code->value], ['name' => $code->label()])->id,
        ]);
    }

    public function inDepartment(Department $department): static
    {
        return $this->state(fn () => ['department_id' => $department->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn () => ['must_change_password' => true]);
    }
}
