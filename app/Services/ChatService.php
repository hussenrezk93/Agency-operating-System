<?php

namespace App\Services;

use App\Enums\ConversationType;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Events\ChatMessageSent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * PHASE 8 — the ONLY writer of `chat_conversations`/`chat_members`/`chat_messages`.
 * Same two-boundary authorization as every other service in this codebase (Form Request
 * + `Gate::forUser()` here), and the same audit rule `TaskWorkflowService::addComment()`
 * already established: log the message ID, never the text (BRD §19).
 *
 * Group membership (department group / all-TLs / manager+TLs / an employee's own
 * Employee-TL conversation) is resolved LAZILY: every resolve*() call finds-or-creates the
 * conversation, then syncs in any currently-eligible member who isn't a `chat_members` row
 * yet. Nothing is ever removed — a disabled or moved user can't sign in regardless, so a
 * stale row is inert (same "never hard-delete, only supersede" convention as leadership
 * assignments and department routes). `DirectTl` is the one exception: its two members are
 * fixed at creation, never synced.
 */
class ChatService
{
    private const URL_PATTERN = '#^https://\S+$#i';

    public function __construct(private readonly AuditService $audit) {}

    /**
     * Every conversation the actor currently belongs to, for the chat sidebar — resolving
     * each applicable group conversation (which also syncs in any newly-eligible member,
     * decision 2) plus any `DirectTl` conversation they've already explicitly joined.
     *
     * @return Collection<int, ChatConversation>
     */
    public function conversationsFor(User $actor): Collection
    {
        $conversations = collect();

        if ($actor->hasRole(RoleCode::Employee) && $actor->department !== null) {
            $conversations->push($this->resolveEmployeeTlConversation($actor));
            $conversations->push($this->resolveDepartmentGroupConversation($actor->department));
        }

        if ($actor->hasRole(RoleCode::TeamLeader)) {
            if ($actor->department !== null) {
                $conversations->push($this->resolveDepartmentGroupConversation($actor->department));
            }
            $conversations->push($this->resolveAllTlsConversation());
            $conversations->push($this->resolveManagerTlsConversation());
        }

        if ($actor->hasRole(RoleCode::Manager)) {
            $conversations->push($this->resolveManagerTlsConversation());
        }

        $conversations = $conversations->merge(
            $actor->conversations()->ofType(ConversationType::DirectTl)->get(),
        );

        $conversations = $conversations->merge(
            $actor->conversations()->ofType(ConversationType::Direct)->get(),
        );

        return $conversations->filter()->unique('id')->values();
    }

    // ------------------------------------------------------- conversation resolution

    /** BRD §14 — the employee and their department's current effective TL. */
    public function resolveEmployeeTlConversation(User $employee): ChatConversation
    {
        return DB::transaction(function () use ($employee): ChatConversation {
            $conversation = ChatConversation::ofType(ConversationType::EmployeeTl)
                ->whereHas('members', fn ($q) => $q->where('user_id', $employee->id))
                ->first();

            $conversation ??= ChatConversation::create([
                'type' => ConversationType::EmployeeTl->value,
                'created_at' => now(),
            ]);

            $this->syncMembers($conversation, array_filter([
                $employee,
                $employee->department?->effectiveLeader(),
            ]));

            return $conversation;
        });
    }

    /** BRD §14 — the department's active users plus its current effective leader. */
    public function resolveDepartmentGroupConversation(Department $department): ChatConversation
    {
        return DB::transaction(function () use ($department): ChatConversation {
            $conversation = ChatConversation::ofType(ConversationType::DepartmentGroup)
                ->where('department_id', $department->id)
                ->first();

            $conversation ??= ChatConversation::create([
                'type' => ConversationType::DepartmentGroup->value,
                'department_id' => $department->id,
                'title' => $department->name,
                'created_at' => now(),
            ]);

            $members = $department->users()->where('status', UserStatus::Active->value)->get();
            $leader = $department->effectiveLeader();

            $this->syncMembers($conversation, $leader !== null ? $members->push($leader) : $members);

            return $conversation;
        });
    }

    /** BRD §14 — every department's current effective Team Leader, one singleton conversation. */
    public function resolveAllTlsConversation(): ChatConversation
    {
        return DB::transaction(function (): ChatConversation {
            $conversation = ChatConversation::ofType(ConversationType::AllTls)->first();

            $conversation ??= ChatConversation::create([
                'type' => ConversationType::AllTls->value,
                'created_at' => now(),
            ]);

            $this->syncMembers($conversation, $this->currentEffectiveTeamLeaders());

            return $conversation;
        });
    }

    /** BRD §14 — every active Manager plus every department's current effective Team Leader. */
    public function resolveManagerTlsConversation(): ChatConversation
    {
        return DB::transaction(function (): ChatConversation {
            $conversation = ChatConversation::ofType(ConversationType::ManagerTls)->first();

            $conversation ??= ChatConversation::create([
                'type' => ConversationType::ManagerTls->value,
                'created_at' => now(),
            ]);

            $managers = User::whereHas('role', fn ($q) => $q->where('code', RoleCode::Manager->value))
                ->where('status', UserStatus::Active->value)
                ->get();

            $this->syncMembers($conversation, $managers->merge($this->currentEffectiveTeamLeaders()));

            return $conversation;
        });
    }

    /** BRD §14 — a private conversation between two named Team Leaders. Fixed membership, no sync. */
    public function startDirectConversation(User $actor, User $otherLeader): ChatConversation
    {
        Gate::forUser($actor)->authorize('startDirect', ChatConversation::class);

        if ($actor->is($otherLeader)) {
            throw ValidationException::withMessages([
                'user_id' => __('You cannot start a conversation with yourself.'),
            ]);
        }

        if (! $otherLeader->hasRole(RoleCode::TeamLeader)) {
            throw ValidationException::withMessages([
                'user_id' => __('Direct conversations are only between Team Leaders.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $otherLeader): ChatConversation {
            $conversation = ChatConversation::ofType(ConversationType::DirectTl)
                ->whereHas('members', fn ($q) => $q->where('user_id', $actor->id))
                ->whereHas('members', fn ($q) => $q->where('user_id', $otherLeader->id))
                ->first();

            if ($conversation !== null) {
                return $conversation;
            }

            $conversation = ChatConversation::create([
                'type' => ConversationType::DirectTl->value,
                'created_at' => now(),
            ]);

            foreach ([$actor, $otherLeader] as $member) {
                $conversation->members()->create(['user_id' => $member->id, 'joined_at' => now()]);
            }

            return $conversation;
        });
    }

    /** Every other active Team Leader — who a TL may start a Direct TL conversation with. */
    public function directConversationCandidates(User $actor): Collection
    {
        return User::whereHas('role', fn ($q) => $q->where('code', RoleCode::TeamLeader->value))
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($actor->id)
            ->orderBy('full_name')
            ->get();
    }

    /**
     * The company directory for the "message anyone" panel: active users grouped by
     * department (each with their title/role), a company-wide Managers section, and an
     * Admins section (Admin has no department). The actor never sees themself listed.
     *
     * @return array{departments: Collection, managers: Collection, admins: Collection}
     */
    public function directory(User $actor): array
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => [
                'department' => $department,
                'members' => $department->users()
                    ->where('status', UserStatus::Active->value)
                    ->whereKeyNot($actor->id)
                    ->orderBy('full_name')
                    ->get(),
            ])
            ->filter(fn (array $group) => $group['members']->isNotEmpty())
            ->values();

        $managers = User::whereHas('role', fn ($q) => $q->where('code', RoleCode::Manager->value))
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($actor->id)
            ->orderBy('full_name')
            ->get();

        $admins = User::whereHas('role', fn ($q) => $q->where('code', RoleCode::Admin->value))
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($actor->id)
            ->orderBy('full_name')
            ->get();

        return ['departments' => $departments, 'managers' => $managers, 'admins' => $admins];
    }

    /**
     * A deliberate, later-approved extension of BRD §14: an open direct conversation
     * between any two chat-eligible users (including Admin), started from the company
     * directory. Unlike `startDirectConversation()` this has no role restriction — only
     * that the target isn't the actor and is an active account.
     */
    public function startDirectMessage(User $actor, User $other): ChatConversation
    {
        Gate::forUser($actor)->authorize('startDirectMessage', ChatConversation::class);

        if ($actor->is($other)) {
            throw ValidationException::withMessages([
                'user_id' => __('You cannot start a conversation with yourself.'),
            ]);
        }

        if ($other->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'user_id' => __('That person is not available for messaging.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $other): ChatConversation {
            $conversation = ChatConversation::ofType(ConversationType::Direct)
                ->whereHas('members', fn ($q) => $q->where('user_id', $actor->id))
                ->whereHas('members', fn ($q) => $q->where('user_id', $other->id))
                ->first();

            if ($conversation !== null) {
                return $conversation;
            }

            $conversation = ChatConversation::create([
                'type' => ConversationType::Direct->value,
                'created_at' => now(),
            ]);

            foreach ([$actor, $other] as $member) {
                $conversation->members()->create(['user_id' => $member->id, 'joined_at' => now()]);
            }

            return $conversation;
        });
    }

    // --------------------------------------------------------------- messages

    public function sendMessage(ChatConversation $conversation, User $actor, string $message): ChatMessage
    {
        Gate::forUser($actor)->authorize('send', $conversation);

        $message = trim($message);

        if ($message === '') {
            throw ValidationException::withMessages([
                'message' => __('A message cannot be empty.'),
            ]);
        }

        $isLink = (bool) preg_match(self::URL_PATTERN, $message);

        return DB::transaction(function () use ($conversation, $actor, $message, $isLink): ChatMessage {
            $chatMessage = $conversation->messages()->create([
                'sender_id' => $actor->id,
                'body' => $isLink ? null : $message,
                'link_url' => $isLink ? $message : null,
                'created_at' => now(),
            ]);

            $this->audit->log(
                action: 'chat.message_sent',
                entityType: 'chat_message',
                entityId: $chatMessage->id,
                after: ['conversation_id' => $conversation->id],
                actorId: $actor->id,
            );

            ChatMessageSent::dispatch($chatMessage->refresh());

            return $chatMessage;
        });
    }

    /** BRD §14 — only the sender, and it disappears for everyone permanently. */
    public function deleteMessage(ChatMessage $message, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $message);

        $conversationId = $message->conversation_id;

        $message->deleteForEveryone($actor);

        $this->audit->log(
            action: 'chat.message_deleted',
            entityType: 'chat_message',
            entityId: $message->id,
            after: ['conversation_id' => $conversationId],
            actorId: $actor->id,
        );
    }

    // ------------------------------------------------------------- helpers

    /** @param  iterable<int, User>  $eligible */
    private function syncMembers(ChatConversation $conversation, iterable $eligible): void
    {
        $existingIds = $conversation->members()->pluck('user_id')->all();

        foreach (collect($eligible)->unique('id') as $user) {
            if (! in_array($user->id, $existingIds, true)) {
                $conversation->members()->create(['user_id' => $user->id, 'joined_at' => now()]);
            }
        }
    }

    /** @return Collection<int, User> */
    private function currentEffectiveTeamLeaders(): Collection
    {
        return Department::where('is_active', true)
            ->get()
            ->map(fn (Department $department) => $department->effectiveLeader())
            ->filter()
            ->unique('id')
            ->values();
    }
}
