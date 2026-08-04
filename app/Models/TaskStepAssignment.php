<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskStepAssignment extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_self_assigned' => 'boolean',
        'start_date' => 'date',
        'due_date' => 'date',
        'first_seen_at' => 'datetime',
        'assigned_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(TaskStep::class, 'task_step_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /** Approved decision Q19 — the timestamp is written once and never reset. */
    public function hasBeenSeen(): bool
    {
        return $this->first_seen_at !== null;
    }
}
