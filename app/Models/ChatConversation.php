<?php

namespace App\Models;

use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * BRD §14 — one of five conversation shapes. Only a department group names a department;
 * a CHECK constraint enforces that pairing so an `all_tls` row cannot claim one.
 */
class ChatConversation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'type' => ConversationType::class,
        'created_at' => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChatMember::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    /** For the conversation list preview — the true latest message, deleted or not. */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latestOfMany('created_at');
    }

    /** Members who have not left. Membership is what grants read access (BRD §14). */
    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('left_at');
    }

    public function scopeOfType(Builder $query, ConversationType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function includes(User $user): bool
    {
        return $this->activeMembers()->where('user_id', $user->id)->exists();
    }
}
