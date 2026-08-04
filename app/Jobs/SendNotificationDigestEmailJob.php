<?php

namespace App\Jobs;

use App\Jobs\Concerns\ComputesEmailRetryBackoff;
use App\Mail\NotificationDigestMail;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One email per recipient covering every `NotificationDelivery` row
 * agencyos:notification-digest-sweep batched together — never one email per event
 * (product decision) — and never blocks anything on failure (Q28): the whole batch
 * shares one send attempt, so it either all becomes Sent or all becomes Failed
 * together, each row keeping its OWN attempt_count/backoff for the next sweep.
 */
class SendNotificationDigestEmailJob implements ShouldQueue
{
    use ComputesEmailRetryBackoff;
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    /** @param  Collection<int, NotificationDelivery>  $deliveries */
    public function __construct(
        public readonly User $recipient,
        public readonly Collection $deliveries,
    ) {}

    public function handle(): void
    {
        $address = $this->recipient->activeEmail();

        if ($address === null) {
            // Became unverified since these rows were queued — leave them for the next
            // sweep to re-evaluate rather than guessing at an error message.
            return;
        }

        $notifications = $this->deliveries->map(fn (NotificationDelivery $delivery) => $delivery->notification);

        try {
            Mail::to($address)->send(new NotificationDigestMail($notifications));

            foreach ($this->deliveries as $delivery) {
                $delivery->markSent();
            }
        } catch (Throwable $e) {
            foreach ($this->deliveries as $delivery) {
                $delivery->markFailed($e->getMessage(), $this->nextRetryAt($delivery->attempt_count + 1));
            }
        }
    }
}
