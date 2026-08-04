<?php

namespace App\Models;

use App\Enums\DeadlineStatus;
use App\Enums\WorkflowStatus;
use Database\Factories\TaskStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TaskStep extends Model
{
    /** @use HasFactory<TaskStepFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'workflow_status' => WorkflowStatus::class,
        'deadline_status' => DeadlineStatus::class,
        'current_start_date' => 'date',
        'current_due_at' => 'datetime',
        'created_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskStepAssignment::class);
    }

    /** The one open assignment — a step never has two at once (DB-enforced). */
    public function activeAssignment(): HasOne
    {
        return $this->hasOne(TaskStepAssignment::class)->whereNull('ended_at');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(TaskStepOutput::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskStepComment::class)->orderBy('created_at');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(TaskStepReview::class)->orderBy('reviewed_at');
    }

    public function assignee(): ?User
    {
        return $this->activeAssignment?->assignee;
    }

    /** Which submission round the step is on — 1 until the first changes are requested. */
    public function currentSubmissionNo(): int
    {
        return max(1, (int) $this->reviews()
            ->where('decision', 'changes_requested')->count() + 1);
    }

    public function outputsForCurrentSubmission()
    {
        return $this->outputs()->where('submission_no', $this->currentSubmissionNo())->get();
    }

    /** Q12 — a step the effective TL assigned to themselves is reviewed by a Manager. */
    public function isSelfAssigned(): bool
    {
        return (bool) $this->activeAssignment?->is_self_assigned;
    }
}
