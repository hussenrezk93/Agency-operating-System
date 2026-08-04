<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Jobs\SendProjectInviteEmailJob;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\ProjectInviteDelivery;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * PHASE 7/8 — the WhatsApp invite + chat digest retry sweep, and the Manager
 * delivery-failure alert. The chat digest half of this command is covered by
 * ChatDigestTest::test_a_failed_batch_is_redispatched_by_the_retry_sweep() instead, next
 * to the rest of that ledger's coverage. General `notification_deliveries` retries are
 * covered by NotificationDigestSweepTest — that ledger's retry IS its normal send sweep.
 */
class NotificationRetrySweepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_a_failed_project_invite_delivery_is_redispatched(): void
    {
        Queue::fake();

        $failed = ProjectInviteDelivery::factory()->failed()->create();

        $this->artisan('agencyos:notification-retry-sweep')->assertExitCode(0);

        Queue::assertPushed(SendProjectInviteEmailJob::class, fn ($job) => $job->delivery->is($failed));
    }

    public function test_the_failure_alert_notifies_every_manager_in_app_only_once_the_attempt_cap_is_hit(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();
        NotificationDelivery::factory()->failed()->create(['attempt_count' => 5, 'last_attempt_at' => now()]);

        $this->artisan('agencyos:notification-failure-alert')->assertExitCode(0);

        $notification = Notification::where('user_id', $manager->id)
            ->where('type', 'notification.delivery_failures')
            ->first();

        $this->assertNotNull($notification);
        $this->assertDatabaseMissing('notification_deliveries', [
            'notification_id' => $notification->id,
            'channel' => 'email',
        ]);
    }

    public function test_the_failure_alert_skips_a_disabled_manager(): void
    {
        $disabledManager = User::factory()->role(RoleCode::Manager)->create(['status' => 'inactive']);
        NotificationDelivery::factory()->failed()->create(['attempt_count' => 5, 'last_attempt_at' => now()]);

        $this->artisan('agencyos:notification-failure-alert')->assertExitCode(0);

        $this->assertFalse(
            Notification::where('user_id', $disabledManager->id)->where('type', 'notification.delivery_failures')->exists(),
        );
    }

    public function test_the_failure_alert_notifies_managers_about_a_bounce(): void
    {
        $manager = User::factory()->role(RoleCode::Manager)->create();
        NotificationDelivery::factory()->bounced()->create();

        $this->artisan('agencyos:notification-failure-alert')->assertExitCode(0);

        $this->assertTrue(
            Notification::where('user_id', $manager->id)->where('type', 'notification.delivery_failures')->exists(),
        );
    }

    public function test_the_failure_alert_does_nothing_when_there_is_nothing_to_report(): void
    {
        User::factory()->role(RoleCode::Manager)->create();

        $this->artisan('agencyos:notification-failure-alert')->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', ['type' => 'notification.delivery_failures']);
    }
}
