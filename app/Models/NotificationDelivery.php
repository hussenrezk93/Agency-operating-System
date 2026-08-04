<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One channel's outcome for one notification (BRD §11.1, §20).
 *
 * Email sending is Best Effort: a failure is recorded here, retried, and never allowed to
 * block a state transition (BRD §22.19). `next_attempt_at` is what the Phase 7 queue worker
 * claims on, and the unique index on (notification, channel) is what makes a retry
 * idempotent instead of producing a second email.
 */
class NotificationDelivery extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'channel' => NotificationChannel::class,
        'status' => DeliveryStatus::class,
        'attempt_count' => 'integer',
        'last_attempt_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
        'bounced_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    /** Rows the worker should pick up now: failed, and due for another attempt. */
    public function scopeDueForRetry(Builder $query): Builder
    {
        return $query
            ->where('status', DeliveryStatus::Failed->value)
            ->where(function (Builder $q): void {
                $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            });
    }

    public function scopeOnChannel(Builder $query, NotificationChannel $channel): Builder
    {
        return $query->where('channel', $channel->value);
    }

    /** A CHECK constraint requires `sent_at` whenever the status is sent. */
    public function markSent(?string $providerMessageId = null): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::Sent->value,
            'provider_message_id' => $providerMessageId,
            'sent_at' => now(),
            'last_attempt_at' => now(),
            'attempt_count' => $this->attempt_count + 1,
            'error' => null,
            'next_attempt_at' => null,
        ])->save();
    }

    public function markFailed(string $error, ?\DateTimeInterface $retryAt = null): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::Failed->value,
            'error' => $error,
            'last_attempt_at' => now(),
            'attempt_count' => $this->attempt_count + 1,
            'next_attempt_at' => $retryAt,
        ])->save();
    }

    /** A bounce is final — the address is wrong, so retrying would only repeat it. */
    public function markBounced(string $error): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::Bounced->value,
            'error' => $error,
            'bounced_at' => now(),
            'last_attempt_at' => now(),
            'attempt_count' => $this->attempt_count + 1,
            'next_attempt_at' => null,
        ])->save();
    }
}
