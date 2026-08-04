<?php

namespace App\Policies;

use App\Models\ChatMessage;
use App\Models\User;

/** BRD §14 — only the sender may delete their own message; not the TL, not a Manager. */
class ChatMessagePolicy
{
    public function delete(User $actor, ChatMessage $message): bool
    {
        return $message->isDeletableBy($actor);
    }
}
