<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something the company spent money on — transport, maintenance, anything the Manager
 * types in (product decision 2026-09: free description, no fixed categories). Written
 * only by PayrollService.
 */
class Expense extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'spent_on' => 'date',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Everything spent inside one calendar month, by the date the Manager gave it. */
    public function scopeForMonth(Builder $query, \DateTimeInterface $month): Builder
    {
        $start = Carbon::parse($month)->startOfMonth();

        return $query->whereBetween('spent_on', [
            $start->toDateString(),
            $start->clone()->endOfMonth()->toDateString(),
        ]);
    }
}
