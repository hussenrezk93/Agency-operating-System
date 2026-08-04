<?php

namespace App\Models;

use App\Enums\RoleTransitionReason;
use App\Enums\RoleTransitionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** History of effective-role changes — APPROVED DECISION Q2. Written only by
 *  RoleTransitionService; never edited afterwards. */
class UserRoleTransition extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'transition_type' => RoleTransitionType::class,
        'reason' => RoleTransitionReason::class,
        'effective_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'from_role_id');
    }

    public function toRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'to_role_id');
    }

    public function leadershipAssignment(): BelongsTo
    {
        return $this->belongsTo(DepartmentLeadershipAssignment::class, 'leadership_assignment_id');
    }
}
