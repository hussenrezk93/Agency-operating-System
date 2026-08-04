<?php

namespace App\Models;

use App\Enums\WhatsappLinkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BRD §7.3 / §22.15 — the history behind `projects.whatsapp_link_version`.
 *
 * Agency OS never touches the WhatsApp Business API. It stores an invite link a human created
 * outside the system and distributes it. Every edit creates a NEW version and re-invites the
 * current members exactly once for that version, which is the entire mechanism: versioning
 * is what makes "invite once, never twice" answerable.
 *
 * A partial unique index guarantees exactly one current version per project.
 */
class ProjectWhatsappLinkVersion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'action_type' => WhatsappLinkAction::class,
        'version_no' => 'integer',
        'is_current' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ProjectInviteDelivery::class, 'link_version_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /** A removed link carries no URL, so there is nothing left to distribute. */
    public function isDistributable(): bool
    {
        return $this->is_current
            && $this->action_type !== WhatsappLinkAction::Removed
            && $this->group_url !== null;
    }
}
