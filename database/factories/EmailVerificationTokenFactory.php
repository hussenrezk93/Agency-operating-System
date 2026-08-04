<?php

namespace Database\Factories;

use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<EmailVerificationToken> */
class EmailVerificationTokenFactory extends Factory
{
    protected $model = EmailVerificationToken::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => Hash::make(Str::random(40)),
            'expires_at' => now()->addDay(),        // BRD §18.1 — 24 hours
            'consumed_at' => null,
            'created_at' => now(),
        ];
    }

    /** `expires_at > created_at` is a CHECK, so an expired token backdates both. */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'created_at' => now()->subDays(3),
            'expires_at' => now()->subDays(2),
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn (): array => ['consumed_at' => now()]);
    }
}
