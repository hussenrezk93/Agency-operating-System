<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Notification> */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement([
                'task.assigned', 'task.submitted', 'task.approved',
                'task.changes_requested', 'deadline.due_soon', 'deadline.overdue',
            ]),
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(10),
            'entity_type' => 'task',
            'entity_id' => fake()->numberBetween(1, 500),
            'is_read' => false,
            'read_at' => null,
            'created_at' => now(),
        ];
    }

    /** `is_read` and `read_at` move together — a CHECK constraint requires it. */
    public function read(): static
    {
        return $this->state(fn (): array => ['is_read' => true, 'read_at' => now()]);
    }
}
