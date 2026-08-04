<?php

namespace App\Console\Commands;

use App\Enums\DeliveryStatus;
use App\Jobs\SendChatDigestEmailJob;
use App\Jobs\SendProjectInviteEmailJob;
use App\Models\ChatDigestBatch;
use App\Models\ProjectInviteDelivery;
use Illuminate\Console\Command;

/**
 * Q28 — re-dispatches failed WhatsApp invite emails and failed chat digest batches.
 * Neither `project_invite_deliveries` nor `chat_digest_batches` has an
 * attempt_count/next_attempt_at column, so every `Failed` row in either ledger is
 * retried on each pass; this command's own cadence is the only throttle.
 *
 * General notification emails do NOT retry here — they're batched, and
 * agencyos:notification-digest-sweep already re-attempts a due `Failed` row as part of
 * its normal sweep, so a second retry path for that ledger would be redundant.
 */
class NotificationRetrySweep extends Command
{
    protected $signature = 'agencyos:notification-retry-sweep';

    protected $description = 'Re-dispatch WhatsApp invite emails and chat digest batches that are due for retry.';

    public function handle(): int
    {
        $invites = ProjectInviteDelivery::query()
            ->where('status', DeliveryStatus::Failed->value)
            ->get();

        foreach ($invites as $delivery) {
            SendProjectInviteEmailJob::dispatch($delivery);
        }

        $digests = ChatDigestBatch::query()
            ->where('status', DeliveryStatus::Failed->value)
            ->get();

        foreach ($digests as $batch) {
            SendChatDigestEmailJob::dispatch($batch);
        }

        $this->info(sprintf(
            'Retry sweep dispatched — invites: %d, chat digests: %d.',
            $invites->count(),
            $digests->count(),
        ));

        return self::SUCCESS;
    }
}
