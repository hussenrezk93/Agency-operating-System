<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationDelivery> */
class NotificationDeliveryFactory extends Factory
{
    protected $model = NotificationDelivery::class;

    public function definition(): array
    {
        return [
            'notification_id' => Notification::factory(),
            'channel' => NotificationChannel::Email->value,
            'status' => DeliveryStatus::Queued->value,
            'attempt_count' => 0,
            'created_at' => now(),
        ];
    }

    public function inApp(): static
    {
        return $this->state(fn (): array => ['channel' => NotificationChannel::InApp->value]);
    }

    /** A sent row must carry `sent_at` (CHECK constraint). */
    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => DeliveryStatus::Sent->value,
            'sent_at' => now(),
            'attempt_count' => 1,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => DeliveryStatus::Failed->value,
            'error' => 'SMTP connection timed out',
            'attempt_count' => 1,
            'last_attempt_at' => now(),
            'next_attempt_at' => now()->addMinutes(5),
        ]);
    }

    /** A bounce must carry `bounced_at` (CHECK constraint). */
    public function bounced(): static
    {
        return $this->state(fn (): array => [
            'status' => DeliveryStatus::Bounced->value,
            'error' => 'Recipient address rejected',
            'bounced_at' => now(),
            'attempt_count' => 1,
        ]);
    }
}
