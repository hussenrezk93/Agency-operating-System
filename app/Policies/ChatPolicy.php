<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\ChatConversation;
use App\Models\User;

/**
 * BRD §14 / §19 — the Admin never reads content in the five BRD-restricted conversation
 * types, only the audit event a deletion leaves behind. This used to be a blanket role
 * block; it's now enforced structurally through membership instead: `ChatService`'s
 * resolve*() methods for those five types never query Admin users (department-group
 * membership comes from `Department::users()`, and Admins have no `department_id`), so
 * Admin can never become a member of one and `view()`/`send()`'s membership check already
 * excludes them. The one deliberate, later-approved exception is `Direct` — general
 * person-to-person messaging via the company directory, which Admin can use like anyone
 * else (see `startDirectMessage()`).
 */
class ChatPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    /** Membership is the read permission (BRD §14). */
    public function view(User $actor, ChatConversation $conversation): bool
    {
        return $conversation->includes($actor);
    }

    public function send(User $actor, ChatConversation $conversation): bool
    {
        return $this->view($actor, $conversation);
    }

    /** BRD §14 — Direct TL conversations are started by, and only by, a Team Leader. */
    public function startDirect(User $actor): bool
    {
        return $actor->hasRole(RoleCode::TeamLeader);
    }

    /** The company directory's "message anyone" capability — open to any chat-eligible user. */
    public function startDirectMessage(User $actor): bool
    {
        return true;
    }
}
