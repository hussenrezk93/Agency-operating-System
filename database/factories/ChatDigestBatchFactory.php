<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\ChatDigestBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChatDigestBatch> */
class ChatDigestBatchFactory extends Factory
{
    protected $model = ChatDigestBatch::class;

    /** `message_count > 0` is a CHECK — an empty digest is never sent (BRD §22.18). */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'window_start' => now()->subHours(2),
            'window_end' => now(),
            'message_count' => fake()->numberBetween(1, 12),
            'status' => DeliveryStatus::Queued->value,
            'created_at' => now(),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => DeliveryStatus::Sent->value,
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => DeliveryStatus::Failed->value,
            'error' => 'Mail provider rejected the batch',
        ]);
    }
}
