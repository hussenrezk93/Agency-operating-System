<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Outputs are IMMUTABLE (BRD §13). A correction adds a new row and marks the old one
 * superseded; nothing is ever edited or deleted.
 */
class TaskStepOutput extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_final' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(TaskStep::class, 'task_step_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_output_id');
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_by_output_id !== null;
    }
}
