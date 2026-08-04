<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\InviteRecipientSource;
use App\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BRD §22.13 — one invite per member, per link version, per channel.
 *
 * The unique index on (project, user, link_version, channel) makes that a DATABASE
 * guarantee, so a retry, a double click or a re-run of the distribution job cannot produce
 * a second invite. `recipient_source` records WHY the person qualified, which decides
 * whether removing a department also ends their membership (BRD §7.3).
 */
class ProjectInviteDelivery extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'recipient_source' => InviteRecipientSource::class,
        'channel' => NotificationChannel::class,
        'status' => DeliveryStatus::class,
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function linkVersion(): BelongsTo
    {
        return $this->belongsTo(ProjectWhatsappLinkVersion::class, 'link_version_id');
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::Sent->value,
            'sent_at' => now(),
            'error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill(['status' => DeliveryStatus::Failed->value, 'error' => $error])->save();
    }
}
