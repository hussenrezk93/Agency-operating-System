<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** APPROVED CHANGE REQUEST Q23 — a project hold is a real record, never a visual status.
 *  It owns the child task holds it created, so resuming restores exactly what it paused. */
class ProjectHold extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function taskHolds(): HasMany
    {
        return $this->hasMany(TaskHold::class);
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }
}
