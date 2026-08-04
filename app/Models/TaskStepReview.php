<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskStepReview extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'decision' => ReviewDecision::class,
        'reviewed_at' => 'datetime',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(TaskStep::class, 'task_step_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
