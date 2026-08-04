<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * BRD §14 — text and links only. Messages cannot be edited after sending.
 *
 * DELETION REALLY DELETES THE CONTENT. Only the sender may delete, it disappears for
 * everyone, and `body` and `link_url` are CLEARED — the row survives solely so the
 * conversation keeps its shape. The audit log records that a deletion happened, by whom and
 * when, but never the text (BRD §19). This is not a convention: `chat_msg_content_check`
 * makes a "deleted" row that still holds its text impossible to store.
 */
class ChatMessage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function digestBatches(): BelongsToMany
    {
        return $this->belongsToMany(ChatDigestBatch::class, 'chat_digest_batch_messages', 'message_id', 'batch_id')
            ->withPivot('added_at');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /** BRD §14 — only the author may delete their own message. */
    public function isDeletableBy(User $user): bool
    {
        return ! $this->isDeleted() && $this->sender_id === $user->id;
    }

    /**
     * Clears the content for everyone. The caller is responsible for writing the audit
     * event — without the message text.
     */
    public function deleteForEveryone(User $actor): void
    {
        $this->forceFill([
            'body' => null,
            'link_url' => null,
            'deleted_at' => now(),
            'deleted_by' => $actor->id,
        ])->save();
    }
}
