<?php

namespace Database\Factories;

use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<PasswordResetToken> */
class PasswordResetTokenFactory extends Factory
{
    protected $model = PasswordResetToken::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => Hash::make(Str::random(40)),
            'expires_at' => now()->addHours(2), // CR-002 — 2 hours
            'consumed_at' => null,
            'created_at' => now(),
        ];
    }

    /** `expires_at > created_at` is a CHECK, so an expired token backdates both. */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'created_at' => now()->subHours(5),
            'expires_at' => now()->subHours(3),
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn (): array => ['consumed_at' => now()]);
    }
}
