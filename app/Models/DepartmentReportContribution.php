<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's own part of their department's daily report (product decision 2026-09,
 * Sales). Written only by DepartmentReportService, and merged verbatim into the report's
 * `details` when the Team Leader submits.
 */
class DepartmentReportContribution extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DepartmentDailyReport::class, 'report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
