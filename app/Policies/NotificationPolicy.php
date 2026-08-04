<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/** A notification is visible, and can be marked read, only by the user it belongs to. */
class NotificationPolicy
{
    public function view(User $actor, Notification $notification): bool
    {
        return $notification->user_id === $actor->id;
    }

    public function markRead(User $actor, Notification $notification): bool
    {
        return $this->view($actor, $notification);
    }
}
