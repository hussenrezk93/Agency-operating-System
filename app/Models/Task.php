<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TaskLifecycle;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'priority' => Priority::class,
        'lifecycle_status' => TaskLifecycle::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    public function steps(): HasMany
    {
        return $this->hasMany(TaskStep::class)->orderBy('sequence_no');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(TaskStep::class, 'current_step_id');
    }

    public function referenceLinks(): HasMany
    {
        return $this->hasMany(TaskReferenceLink::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(TaskHold::class);
    }

    public function redirects(): HasMany
    {
        return $this->hasMany(TaskRedirect::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class)->orderBy('created_at');
    }

    /** Completed and cancelled tasks are read-only forever (BRD §22.6). */
    public function isClosed(): bool
    {
        return $this->lifecycle_status->isClosed();
    }

    public function isOnHold(): bool
    {
        return $this->lifecycle_status === TaskLifecycle::OnHold;
    }

    /** The hold that is currently pausing the clock, if any. */
    public function openHold(): ?TaskHold
    {
        return $this->holds()->whereNull('ended_at')->latest('id')->first();
    }

    /** Approved outputs of every completed earlier step (approved decision Q26). */
    public function approvedOutputsBefore(int $sequenceNo)
    {
        return TaskStepOutput::query()
            ->whereIn('task_step_id', $this->steps()
                ->where('sequence_no', '<', $sequenceNo)
                ->pluck('id'))
            ->where('is_final', true)
            ->whereNull('superseded_by_output_id')
            ->with('step:id,department_id')
            ->get();
    }
}
