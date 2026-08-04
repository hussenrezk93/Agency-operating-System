<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationDigestEmailJob;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Product decision (on top of Q28) — general notification emails are batched into one
 * digest per recipient, sent by agencyos:notification-digest-sweep, instead of one
 * email per event. This sweep is also that ledger's own retry mechanism.
 */
class NotificationDigestSweepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function queuedEmailDelivery(User $user, array $overrides = []): NotificationDelivery
    {
        $notification = Notification::factory()->create(['user_id' => $user->id]);

        return NotificationDelivery::factory()->create(array_merge([
            'notification_id' => $notification->id,
            'channel' => 'email',
            'status' => 'queued',
        ], $overrides));
    }

    public function test_a_newly_queued_delivery_is_picked_up(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $delivery = $this->queuedEmailDelivery($user);

        $this->artisan('agencyos:notification-digest-sweep')->assertExitCode(0);

        Queue::assertPushed(SendNotificationDigestEmailJob::class, function (SendNotificationDigestEmailJob $job) use ($user, $delivery) {
            return $job->recipient->is($user) && $job->deliveries->contains(fn ($d) => $d->is($delivery));
        });
    }

    public function test_two_pending_deliveries_for_the_same_recipient_are_batched_into_one_dispatch(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->queuedEmailDelivery($user);
        $this->queuedEmailDelivery($user);

        $this->artisan('agencyos:notification-digest-sweep')->assertExitCode(0);

        Queue::assertPushed(SendNotificationDigestEmailJob::class, 1);
        Queue::assertPushed(SendNotificationDigestEmailJob::class, fn ($job) => $job->deliveries->count() === 2);
    }

    public function test_a_failed_delivery_due_for_retry_is_included(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $delivery = $this->queuedEmailDelivery($user, [
            'status' => 'failed',
            'attempt_count' => 1,
            'next_attempt_at' => now()->subMinute(),
        ]);

        $this->artisan('agencyos:notification-digest-sweep')->assertExitCode(0);

        Queue::assertPushed(SendNotificationDigestEmailJob::class, fn ($job) => $job->deliveries->contains(fn ($d) => $d->is($delivery)));
    }

    public function test_a_failed_delivery_not_yet_due_is_left_alone(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->queuedEmailDelivery($user, [
            'status' => 'failed',
            'attempt_count' => 1,
            'next_attempt_at' => now()->addHour(),
        ]);

        $this->artisan('agencyos:notification-digest-sweep')->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_a_delivery_that_exhausted_its_attempts_is_not_retried(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->queuedEmailDelivery($user, [
            'status' => 'failed',
            'attempt_count' => 5,
            'next_attempt_at' => now()->subMinute(),
        ]);

        $this->artisan('agencyos:notification-digest-sweep')->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_a_successful_send_marks_every_delivery_in_the_batch_sent(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $first = $this->queuedEmailDelivery($user);
        $second = $this->queuedEmailDelivery($user);

        $this->artisan('agencyos:notification-digest-sweep')->assertExitCode(0);

        $this->assertSame('sent', $first->fresh()->status->value);
        $this->assertSame('sent', $second->fresh()->status->value);
    }

    /** Q28 — a mail transport failure never blocks the sweep, and is recorded per row. */
    public function test_a_mail_failure_marks_every_delivery_in_the_batch_failed_without_crashing_the_sweep(): void
    {
        Mail::shouldReceive('to')->andReturnUsing(function () {
            return new class
            {
                public function send($mailable)
                {
                    throw new \RuntimeException('SMTP host unreachable');
                }
            };
        });

        $user = User::factory()->create(['email_verified_at' => now()]);
        $delivery = $this->queuedEmailDelivery($user);

        $this->artisan('agencyos:notification-digest-sweep')->assertExitCode(0);

        $fresh = $delivery->fresh();
        $this->assertSame('failed', $fresh->status->value);
        $this->assertNotNull($fresh->next_attempt_at);
    }

    /**
     * The sweep's own query does not re-check verification (it trusts the gate at
     * `notify()` time) — but the job does, defensively, for the rare case a recipient
     * became unverified after the row was queued. Run through the real (sync) queue so
     * the job body actually executes, rather than faking it away.
     */
    public function test_a_recipient_who_became_unverified_is_left_queued_for_re_evaluation(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $delivery = $this->queuedEmailDelivery($user);

        $this->artisan('agencyos:notification-digest-sweep')->assertExitCode(0);

        $this->assertSame('queued', $delivery->fresh()->status->value);
    }
}
