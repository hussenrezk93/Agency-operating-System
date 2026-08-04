<?php

namespace App\Listeners;

use App\Enums\DeliveryStatus;
use App\Events\ChatMessageSent;
use App\Models\ChatDigestBatch;
use App\Models\User;
use App\Services\NotificationService;

/**
 * BRD §11.1 / §14 — chat gets its own email path (`chat_digest_batches`), separate from
 * `notification_deliveries`: the in-app notification is immediate and mandatory (created
 * here, email-less — `allowEmail: false`), while the email leg is batched into whichever
 * of the recipient's digest windows is still open, closed later by agencyos:chat-digests.
 */
class NotifyOnChatMessageSent
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(ChatMessageSent $event): void
    {
        $message = $event->message;
        $conversation = $message->conversation;

        $recipients = $conversation->activeMembers()
            ->where('user_id', '!=', $message->sender_id)
            ->with('user')
            ->get()
            ->pluck('user');

        foreach ($recipients as $recipient) {
            $this->notifications->notify(
                $recipient,
                'chat.message',
                __('agencyos.notifications.messages.chat_message_title'),
                __('agencyos.notifications.messages.chat_message_body', [
                    'sender' => $message->sender->full_name,
                ]),
                'chat_conversation',
                $conversation->id,
                allowEmail: false,
            );

            $this->addToOpenDigestBatch($recipient, $message->id);
        }
    }

    /**
     * `chat_digest_count_check` requires `message_count > 0` from the moment a row is
     * inserted — a brand-new batch is created with `message_count = 1` directly (never
     * 0-then-incremented) so it never briefly violates the constraint.
     */
    private function addToOpenDigestBatch(User $recipient, int $messageId): void
    {
        $batch = ChatDigestBatch::query()
            ->where('user_id', $recipient->id)
            ->where('status', DeliveryStatus::Queued->value)
            ->where('window_end', '>', now())
            ->first();

        if ($batch === null) {
            $batch = ChatDigestBatch::create([
                'user_id' => $recipient->id,
                'window_start' => now(),
                'window_end' => now()->addHours(2),
                'message_count' => 1,
                'status' => DeliveryStatus::Queued->value,
                'created_at' => now(),
            ]);

            $batch->messages()->attach($messageId, ['added_at' => now()]);

            return;
        }

        $batch->messages()->attach($messageId, ['added_at' => now()]);
        $batch->increment('message_count');
    }
}
