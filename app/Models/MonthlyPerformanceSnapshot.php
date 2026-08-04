<?php

namespace App\Models;

use App\Enums\SnapshotType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BRD §17 — the monthly score, STORED rather than recomputed.
 *
 * The score depends on the state of deadlines at month end. Recomputing it later from
 * current data would silently rewrite history the moment a hold or a reassignment landed,
 * so it is calculated once and frozen.
 *
 * `score` is NULL when nothing was due — that is the N/A the BRD asks for, and it is
 * deliberately not 0. A zero would be an unearned failing mark dragging the department
 * average down; NULL is excluded from the average instead. `mps_na_check` enforces the
 * pairing, so the two can never drift apart.
 *
 * A step counts in the month its DEADLINE falls in, not the month it was worked on.
 */
class MonthlyPerformanceSnapshot extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'snapshot_type' => SnapshotType::class,
        'month_start' => 'date',
        'due_steps' => 'integer',
        'on_time_steps' => 'integer',
        'overdue_steps' => 'integer',
        'score' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopeForMonth(Builder $query, \DateTimeInterface $month): Builder
    {
        return $query->whereDate('month_start', $month->format('Y-m-01'));
    }

    public function scopeOfType(Builder $query, SnapshotType $type): Builder
    {
        return $query->where('snapshot_type', $type->value);
    }

    /** True when nothing was due, which the reports must show as N/A rather than zero. */
    public function isNotApplicable(): bool
    {
        return $this->due_steps === 0;
    }

    public function displayScore(): string
    {
        return $this->isNotApplicable() ? 'N/A' : number_format((float) $this->score, 2).'%';
    }

    /** BRD §17 — on-time steps over steps due, as a percentage. */
    public static function calculateScore(int $dueSteps, int $onTimeSteps): ?float
    {
        if ($dueSteps === 0) {
            return null;
        }

        return round($onTimeSteps / $dueSteps * 100, 2);
    }
}
