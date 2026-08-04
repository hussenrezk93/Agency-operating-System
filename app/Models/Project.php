<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'status' => ProjectStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'whatsapp_link_updated_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'project_departments')
            ->withPivot(['is_active', 'added_at', 'removed_at']);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ProjectLink::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** APPROVED CHANGE REQUEST Q23 — project hold records. */
    public function holds(): HasMany
    {
        return $this->hasMany(ProjectHold::class);
    }

    public function openHold(): ?ProjectHold
    {
        return $this->holds()->whereNull('ended_at')->latest('id')->first();
    }

    /** Unfinished tasks — the set a project hold or cancellation acts upon. */
    public function unfinishedTasks()
    {
        return $this->tasks()
            ->whereNotIn('lifecycle_status', ['completed', 'cancelled'])
            ->get();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Present only once the WhatsApp module lands (CR-001); column exists today. */
    public function whatsappLinkUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'whatsapp_link_updated_by');
    }

    /** Completed / cancelled projects are read-only forever (BRD §7.2, Q22). */
    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    public function acceptsNewTasks(): bool
    {
        return $this->status->acceptsNewTasks();
    }

    public function isOnHold(): bool
    {
        return $this->status === ProjectStatus::OnHold;
    }

    // ------------------------------------------------- WhatsApp link (BRD §7.3)

    public function whatsappLinkVersions(): HasMany
    {
        return $this->hasMany(ProjectWhatsappLinkVersion::class);
    }

    /** Exactly one version is current — a partial unique index guarantees it. */
    public function currentWhatsappLinkVersion(): HasOne
    {
        return $this->hasOne(ProjectWhatsappLinkVersion::class)->where('is_current', true);
    }

    public function inviteDeliveries(): HasMany
    {
        return $this->hasMany(ProjectInviteDelivery::class);
    }

    /**
     * BRD §22.12 / §22.21 — invites go out only while the project is ACTIVE and a link is
     * actually stored. A completed or cancelled project makes the link read-only.
     */
    public function canDistributeWhatsappInvites(): bool
    {
        return $this->status === ProjectStatus::Active
            && $this->whatsapp_group_url !== null;
    }
}
