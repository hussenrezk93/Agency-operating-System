<?php

namespace App\Models;

use Database\Factories\DepartmentRouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Admin-managed routing matrix (BRD §15). */
class DepartmentRoute extends Model
{
    /** @use HasFactory<DepartmentRouteFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['is_allowed' => 'boolean', 'updated_at' => 'datetime'];

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
