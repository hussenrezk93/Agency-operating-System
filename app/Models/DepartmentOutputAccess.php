<?php

namespace App\Models;

use App\Enums\OutputAccessScope;
use Database\Factories\DepartmentOutputAccessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Admin-managed cross-department output visibility (BRD §15). */
class DepartmentOutputAccess extends Model
{
    /** @use HasFactory<DepartmentOutputAccessFactory> */
    use HasFactory;

    protected $table = 'department_output_access';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'scope' => OutputAccessScope::class,
        'is_allowed' => 'boolean',
        'updated_at' => 'datetime',
    ];

    public function viewerDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'viewer_department_id');
    }

    public function sourceDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'source_department_id');
    }
}
