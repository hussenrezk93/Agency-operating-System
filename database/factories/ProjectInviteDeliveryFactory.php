<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Enums\InviteRecipientSource;
use App\Enums\NotificationChannel;
use App\Models\Project;
use App\Models\ProjectInviteDelivery;
use App\Models\ProjectWhatsappLinkVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectInviteDelivery> */
class ProjectInviteDeliveryFactory extends Factory
{
    protected $model = ProjectInviteDelivery::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'department_id' => null,
            'link_version_id' => ProjectWhatsappLinkVersion::factory(),
            'recipient_source' => InviteRecipientSource::Department->value,
            'channel' => NotificationChannel::Email->value,
            'status' => DeliveryStatus::Queued->value,
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
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => DeliveryStatus::Failed->value,
            'error' => 'Delivery failed',
        ]);
    }
}
