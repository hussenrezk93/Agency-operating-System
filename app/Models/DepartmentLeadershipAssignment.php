<?php

namespace App\Models;

use App\Enums\ActivationState;
use App\Enums\LeadershipType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartmentLeadershipAssignment extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'assignment_type' => LeadershipType::class,
        'activation_state' => ActivationState::class,
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'ended_early_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * THE single definition of "currently active" used by every leadership check.
     * An assignment counts only when it is valid (`is_active`), has been activated by
     * the scheduler (`activation_state = active`) AND today — in the application
     * timezone, Africa/Cairo — falls inside its date window. The date window is what
     * protects the system if the scheduler is late: an expired temporary leader loses
     * authority on the correct day even before the nightly pass runs.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrentlyActive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where('is_active', true)
            ->where('activation_state', ActivationState::Active->value)
            ->whereDate('start_date', '<=', $today)
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    /** Set when a temporary leader is swapped mid-period (Q16). */
    public function replacedByAssignment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_assignment_id');
    }

    public function roleTransitions(): HasMany
    {
        return $this->hasMany(UserRoleTransition::class, 'leadership_assignment_id');
    }

    public function isTemporary(): bool
    {
        return $this->assignment_type === LeadershipType::Temporary;
    }

    /** Mirrors scopeCurrentlyActive() for an already-loaded record. */
    public function isEffective(): bool
    {
        if (! $this->is_active || $this->activation_state !== ActivationState::Active) {
            return false;
        }

        $today = now()->startOfDay();

        return ! $this->start_date->startOfDay()->greaterThan($today)
            && ($this->end_date === null || ! $this->end_date->startOfDay()->lessThan($today));
    }
}
