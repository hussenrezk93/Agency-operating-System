<?php

namespace App\Console\Commands;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Jobs\SendNotificationDigestEmailJob;
use App\Models\NotificationDelivery;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Product decision (on top of Q28) — general notification emails are batched into one
 * digest per recipient every 3 hours instead of one email per event. Picks up both
 * freshly-queued rows and failed rows that are due for another attempt, so this single
 * sweep also IS the retry mechanism for `notification_deliveries` — a separate
 * `agencyos:notification-retry-sweep` pass is not needed for this ledger (it still
 * exists for the unrelated `project_invite_deliveries` ledger, which stays immediate).
 */
class NotificationDigestSweep extends Command
{
    private const MAX_ATTEMPTS = 5;

    protected $signature = 'agencyos:notification-digest-sweep';

    protected $description = 'Batch every recipient\'s pending notification emails into one digest, at most every 3 hours.';

    public function handle(): int
    {
        $due = NotificationDelivery::query()
            ->where('channel', NotificationChannel::Email->value)
            ->where(function (Builder $query): void {
                $query->where('status', DeliveryStatus::Queued->value)
                    ->orWhere(function (Builder $retryable): void {
                        $retryable->where('status', DeliveryStatus::Failed->value)
                            ->where('attempt_count', '<', self::MAX_ATTEMPTS)
                            ->where(fn (Builder $q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()));
                    });
            })
            ->with('notification.user')
            ->get()
            ->groupBy(fn (NotificationDelivery $delivery) => $delivery->notification->user_id);

        foreach ($due as $deliveries) {
            SendNotificationDigestEmailJob::dispatch($deliveries->first()->notification->user, $deliveries);
        }

        $this->info(sprintf(
            'Digest sweep dispatched %d recipient(s) covering %d notification(s).',
            $due->count(),
            $due->flatten(1)->count(),
        ));

        return self::SUCCESS;
    }
}
