<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * PHASE 7 — NotificationService is the single writer of notifications/notification_
 * deliveries; this is the pure channel-gating logic, independent of any event/listener.
 */
class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notifications;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->notifications = app(NotificationService::class);
    }

    public function test_in_app_delivery_is_always_written_as_sent_immediately(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $notification = $this->notifications->notify($user, 'test.type', 'Title', 'Body');

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'channel' => 'in_app',
            'status' => 'sent',
        ]);
    }

    /**
     * PHASE 7 follow-up — email is written `Queued` and left there; nothing is
     * dispatched immediately anymore, since agencyos:notification-digest-sweep is what
     * actually sends it (batched with the recipient's other pending rows).
     */
    public function test_email_delivery_is_only_created_for_a_verified_recipient(): void
    {
        Queue::fake();

        $unverified = User::factory()->create(['email_verified_at' => null]);
        $verified = User::factory()->create(['email_verified_at' => now()]);

        $unverifiedNotification = $this->notifications->notify($unverified, 'test.type', 'Title', 'Body');
        $verifiedNotification = $this->notifications->notify($verified, 'test.type', 'Title', 'Body');

        $this->assertDatabaseMissing('notification_deliveries', [
            'notification_id' => $unverifiedNotification->id,
            'channel' => 'email',
        ]);
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $verifiedNotification->id,
            'channel' => 'email',
            'status' => 'queued',
        ]);

        Queue::assertNothingPushed();
    }

    public function test_allow_email_false_forces_in_app_only_even_for_a_verified_recipient(): void
    {
        Queue::fake();

        $verified = User::factory()->create(['email_verified_at' => now()]);

        $notification = $this->notifications->notify($verified, 'test.type', 'Title', 'Body', allowEmail: false);

        $this->assertDatabaseMissing('notification_deliveries', [
            'notification_id' => $notification->id,
            'channel' => 'email',
        ]);
        Queue::assertNothingPushed();
    }

    public function test_create_record_writes_no_delivery_rows(): void
    {
        $user = User::factory()->create();

        $notification = $this->notifications->createRecord($user, 'project.invite', 'Title', 'Body');

        $this->assertDatabaseMissing('notification_deliveries', ['notification_id' => $notification->id]);
        $this->assertSame($user->id, $notification->user_id);
    }
}
