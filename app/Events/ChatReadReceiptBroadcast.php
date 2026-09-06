<?php

namespace App\Events;

use App\Models\ChatConversation;
use Carbon\CarbonInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells every OTHER open tab on this conversation "this member has now read up to message
 * X" — the sender's tab uses it to flip their own sent messages to the seen/double-tick
 * state. One event per reader per read-advance, not one per message: a reader who catches
 * up on 20 messages at once still only sends a single watermark forward.
 */
class ChatReadReceiptBroadcast implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly ChatConversation $conversation,
        public readonly int $userId,
        public readonly int $lastReadMessageId,
        public readonly CarbonInterface $readAt,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.conversation.'.$this->conversation->id)];
    }

    public function broadcastAs(): string
    {
        return 'message.read';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'last_read_message_id' => $this->lastReadMessageId,
            'read_at' => $this->readAt->toIso8601String(),
        ];
    }
}
