<?php

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * BRD §14/§19 — a user may subscribe to a conversation's live channel under EXACTLY the
 * same rule ChatPolicy::view() already enforces for the HTTP routes (conversation
 * membership). This is not a second copy of the rule — it calls the same policy — so the
 * two can never drift apart.
 */
Broadcast::channel('chat.conversation.{conversationId}', function (User $user, int $conversationId) {
    $conversation = ChatConversation::find($conversationId);

    return $conversation !== null && $user->can('view', $conversation);
});
