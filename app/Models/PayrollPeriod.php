<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's pay window for one month: the dates the Manager opened by hand, and —
 * once closed — a frozen record of what was actually paid (product decision 2026-09).
 * Written only by PayrollService.
 */
class PayrollPeriod extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'month_start' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'base_amount' => 'decimal:2',
        'bonus_total' => 'decimal:2',
        'deduction_total' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'status' => PayrollStatus::class,
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeForMonth(Builder $query, \DateTimeInterface $month): Builder
    {
        return $query->whereDate('month_start', $month->format('Y-m-01'));
    }

    public function isClosed(): bool
    {
        return $this->status === PayrollStatus::Closed;
    }
}
