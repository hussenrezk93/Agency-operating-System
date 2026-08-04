<?php

namespace App\Console\Commands;

use App\Enums\DeliveryStatus;
use App\Jobs\SendChatDigestEmailJob;
use App\Models\ChatDigestBatch;
use Illuminate\Console\Command;

/**
 * Phase 0 Technical Plan §7 — closes every 2-hour chat digest window that is due and has
 * at least one message (BRD §22.18: an empty window is never sent — also DB-enforced by
 * the `chat_digest_count_check` CHECK constraint, so this query's `message_count > 0`
 * clause is belt-and-braces, not the only guard).
 */
class ChatDigestSweep extends Command
{
    protected $signature = 'agencyos:chat-digests';

    protected $description = 'Send every recipient\'s due 2-hour chat digest email.';

    public function handle(): int
    {
        $due = ChatDigestBatch::query()
            ->where('status', DeliveryStatus::Queued->value)
            ->where('window_end', '<=', now())
            ->where('message_count', '>', 0)
            ->get();

        foreach ($due as $batch) {
            SendChatDigestEmailJob::dispatch($batch);
        }

        $this->info(sprintf('Chat digest sweep dispatched %d batch(es).', $due->count()));

        return self::SUCCESS;
    }
}
