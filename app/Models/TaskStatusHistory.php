<?php

namespace App\Models;

use App\Enums\TaskEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The task timeline (BRD §9). Written only by TaskWorkflowService. */
class TaskStatusHistory extends Model
{
    protected $table = 'task_status_history';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'event_type' => TaskEvent::class,
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(TaskStep::class, 'task_step_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
