<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Support\ChatPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushed over Pusher (BRD §14's "no live-transport requirement" is satisfied more
 * fully than the plain-polling fallback used before this) to every member of the
 * conversation the instant a message is sent — queued, so sending never waits on the
 * Pusher API call.
 *
 * `is_mine`/`deletable` in the payload are meaningless here (one payload goes to every
 * subscriber, who each have a different actor) — the client recomputes both from
 * `sender_id` against its own page's actor id instead of trusting these fields when the
 * source is a broadcast. They stay in the shared payload shape only because
 * ChatPresenter::messagePayload() is also used by the per-viewer poll()/store() JSON
 * responses, where they ARE meaningful.
 */
class ChatMessageBroadcast implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly ChatMessage $message) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.conversation.'.$this->message->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.new';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ChatPresenter::messagePayload($this->message, actorId: 0);
    }
}
