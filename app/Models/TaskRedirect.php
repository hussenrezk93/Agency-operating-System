<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Manager-only route correction with a mandatory reason (BRD §10). */
class TaskRedirect extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function fromStep(): BelongsTo
    {
        return $this->belongsTo(TaskStep::class, 'from_step_id');
    }

    public function toStep(): BelongsTo
    {
        return $this->belongsTo(TaskStep::class, 'to_step_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function redirectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redirected_by');
    }
}
