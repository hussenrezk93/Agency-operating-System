<?php

namespace App\Jobs;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Jobs\Concerns\ComputesEmailRetryBackoff;
use App\Mail\NotificationMail;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Q28 — sends one notification's email right away instead of waiting for
 * agencyos:notification-digest-sweep. See NotificationService::notifyInstant() for why
 * the `notification_deliveries` row is written already Sent or Failed and never
 * `Queued`. A failed attempt still gets retried by the digest sweep's own
 * due-for-retry clause, same as every other row in that ledger.
 */
class SendInstantNotificationEmailJob implements ShouldQueue
{
    use ComputesEmailRetryBackoff;
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function __construct(public readonly Notification $notification) {}

    public function handle(): void
    {
        $recipient = $this->notification->user;
        $address = $recipient->activeEmail();

        $delivery = $this->notification->deliveries()->make([
            'channel' => NotificationChannel::Email->value,
            'attempt_count' => 1,
            'last_attempt_at' => now(),
            'created_at' => now(),
        ]);

        if ($address === null) {
            $delivery->forceFill([
                'status' => DeliveryStatus::Failed->value,
                'error' => 'No verified recipient email.',
                'next_attempt_at' => $this->nextRetryAt(1),
            ])->save();

            return;
        }

        try {
            Mail::to($address)->send(new NotificationMail($this->notification));
            $delivery->forceFill(['status' => DeliveryStatus::Sent->value, 'sent_at' => now()])->save();
        } catch (Throwable $e) {
            $delivery->forceFill([
                'status' => DeliveryStatus::Failed->value,
                'error' => $e->getMessage(),
                'next_attempt_at' => $this->nextRetryAt(1),
            ])->save();
        }
    }
}
