<?php

namespace App\Jobs;

use App\Mail\ChatDigestMail;
use App\Models\ChatDigestBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Q28 — same never-block, never-framework-retry shape as every other mail job in this
 * app: `chat_digest_batches` has no attempt_count/next_attempt_at columns (same as
 * `project_invite_deliveries`), so a failed batch is picked up again by
 * agencyos:notification-retry-sweep's own cadence rather than a backoff clock.
 */
class SendChatDigestEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function __construct(public readonly ChatDigestBatch $batch) {}

    public function handle(): void
    {
        $address = $this->batch->user->activeEmail();

        if ($address === null) {
            $this->batch->markFailed('Recipient has no verified email address.');

            return;
        }

        try {
            Mail::to($address)->send(new ChatDigestMail($this->batch));
            $this->batch->markSent();
        } catch (Throwable $e) {
            $this->batch->markFailed($e->getMessage());
        }
    }
}
