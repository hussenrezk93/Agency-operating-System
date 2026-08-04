<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Approved decisions Q5/Q9 — a hold pauses the deadline clock and extends the due date
 *  by exactly `paused_seconds`, which is stored on resume rather than recomputed. */
class TaskHold extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'paused_seconds' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(TaskStep::class, 'task_step_id');
    }

    public function projectHold(): BelongsTo
    {
        return $this->belongsTo(ProjectHold::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /** True when this hold was created by a project-level hold (Q23). */
    public function isFromProjectHold(): bool
    {
        return $this->project_hold_id !== null;
    }
}
