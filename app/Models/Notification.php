<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BRD §11.1 — ONE logical notification event. The per-channel outcome lives in
 * `notification_deliveries`, which is why a bounced email can never erase the fact that the
 * user was notified: the event row is untouched by delivery problems.
 *
 * `entity_type` / `entity_id` are a loose pointer rather than a foreign key, because a
 * notification may refer to a task, a step, a project or nothing at all.
 */
class Notification extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * `is_read` and `read_at` are kept in step by a CHECK constraint, so they are always
     * written together rather than separately.
     */
    public function markRead(): void
    {
        if ($this->is_read) {
            return;
        }

        $this->forceFill(['is_read' => true, 'read_at' => now()])->save();
    }

    public function delivery(string $channel): ?NotificationDelivery
    {
        return $this->deliveries->firstWhere('channel', $channel);
    }
}
