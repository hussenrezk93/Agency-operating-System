<?php

namespace App\Models;

use App\Enums\AdjustmentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bonus or deduction the Manager attached to one person for one month (product
 * decision 2026-09). Written only by PerformanceAdjustmentService.
 */
class PerformanceAdjustment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'type' => AdjustmentType::class,
        'month_start' => 'date',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForMonth(Builder $query, \DateTimeInterface $month): Builder
    {
        return $query->whereDate('month_start', $month->format('Y-m-01'));
    }

    public function isBonus(): bool
    {
        return $this->type === AdjustmentType::Bonus;
    }
}
