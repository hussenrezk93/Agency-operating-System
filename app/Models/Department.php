<?php

namespace App\Models;

use App\Enums\DepartmentSpecialRole;
use App\Enums\LeadershipType;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'special_role' => DepartmentSpecialRole::class,
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The one department carrying a given special role. `name` is freely editable by an
     * Admin, so every rule that singles out a department (Content's review of Graphic's
     * work, the Moderator's report, ...) resolves it through the stable role instead.
     */
    public static function withSpecialRole(DepartmentSpecialRole $role): ?self
    {
        return static::where('special_role', $role->value)->first();
    }

    /**
     * Active departments, plus ones created without a primary leader yet (product
     * decision 2026-08) — those are inactive but should still take on staff (employees
     * or the TL who will later be promoted via assignPrimaryLeader()) while awaiting
     * one. A department deliberately deactivated by an admin keeps its old leadership
     * assignment row, so `whereDoesntHave('leadershipAssignments')` alone tells the two
     * apart without a dedicated flag.
     */
    public function scopeAvailableForStaffing(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('is_active', true)->orWhereDoesntHave('leadershipAssignments');
        });
    }

    public function leadershipAssignments(): HasMany
    {
        return $this->hasMany(DepartmentLeadershipAssignment::class);
    }

    public function routesFrom(): HasMany
    {
        return $this->hasMany(DepartmentRoute::class, 'from_department_id');
    }

    public function routesTo(): HasMany
    {
        return $this->hasMany(DepartmentRoute::class, 'to_department_id');
    }

    public function taskSteps(): HasMany
    {
        return $this->hasMany(TaskStep::class);
    }

    public function dailyReports(): HasMany
    {
        return $this->hasMany(DepartmentDailyReport::class);
    }

    public function outputAccessAsViewer(): HasMany
    {
        return $this->hasMany(DepartmentOutputAccess::class, 'viewer_department_id');
    }

    /** BRD §15 — has Admin granted this department's TL the right to view $source's outputs? */
    public function hasOutputAccessTo(int $sourceDepartmentId): bool
    {
        if ($this->id === $sourceDepartmentId) {
            return true;
        }

        return $this->outputAccessAsViewer()
            ->where('source_department_id', $sourceDepartmentId)
            ->where('is_allowed', true)
            ->exists();
    }

    public function outputAccessAsSource(): HasMany
    {
        return $this->hasMany(DepartmentOutputAccess::class, 'source_department_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_departments')
            ->withPivot(['is_active', 'added_at', 'removed_at']);
    }

    /**
     * The single authority for "who leads this department right now"
     * (approved decisions Q11, Q14, Q21): an active TEMPORARY assignment wins;
     * otherwise the active PRIMARY Team Leader.
     * Every authorization check must call this — never `users.role` alone.
     */
    public function effectiveLeader(): ?User
    {
        return $this->temporaryLeader() ?? $this->primaryLeader();
    }

    /** The assignment that currently confers leadership, temporary taking precedence. */
    public function activeLeadershipAssignment(): ?DepartmentLeadershipAssignment
    {
        if ($this->relationLoaded('leadershipAssignments')) {
            return $this->leadershipAssignments
                ->filter(fn (DepartmentLeadershipAssignment $a) => $a->isEffective())
                ->sortBy(fn (DepartmentLeadershipAssignment $a) => $a->assignment_type === LeadershipType::Temporary ? 0 : 1)
                ->first();
        }

        return $this->leadershipAssignments()
            ->currentlyActive()
            ->orderByRaw("CASE WHEN assignment_type = 'temporary' THEN 0 ELSE 1 END")
            ->with('user')
            ->first();
    }

    /**
     * The substantive department leader. NOTE: the primary assignment is NOT
     * deactivated while a temporary leader covers the department (approved decision
     * Q14) — the primary TL keeps the assignment and merely loses authority, so this
     * method must never be used on its own to decide whether an action is permitted.
     *
     * PERFORMANCE: when the caller has already eager-loaded `leadershipAssignments.user`
     * (e.g. looping over many departments), this filters the in-memory collection
     * instead of firing a fresh query per department — identical result, same rule
     * (`DepartmentLeadershipAssignment::isEffective()` mirrors `scopeCurrentlyActive()`
     * exactly), just no extra round trip. Falls back to the query when it isn't loaded.
     */
    public function primaryLeader(): ?User
    {
        if ($this->relationLoaded('leadershipAssignments')) {
            return $this->leadershipAssignments
                ->first(fn (DepartmentLeadershipAssignment $a) => $a->isEffective()
                    && $a->assignment_type === LeadershipType::Primary)?->user;
        }

        return $this->leadershipAssignments()
            ->currentlyActive()
            ->where('assignment_type', LeadershipType::Primary->value)
            ->with('user')
            ->first()?->user;
    }

    /** The covering leader during a primary TL's leave, or null (Q2, Q13). */
    public function temporaryLeader(): ?User
    {
        return $this->temporaryLeadershipAssignment()?->user;
    }

    public function temporaryLeadershipAssignment(): ?DepartmentLeadershipAssignment
    {
        if ($this->relationLoaded('leadershipAssignments')) {
            return $this->leadershipAssignments
                ->first(fn (DepartmentLeadershipAssignment $a) => $a->isEffective()
                    && $a->assignment_type === LeadershipType::Temporary);
        }

        return $this->leadershipAssignments()
            ->currentlyActive()
            ->where('assignment_type', LeadershipType::Temporary->value)
            ->with('user')
            ->first();
    }

    public function hasActiveTemporaryLeader(): bool
    {
        return $this->temporaryLeadershipAssignment() !== null;
    }

    /** Departments this one may send work to (Admin matrix, BRD §15). */
    public function allowedNextDepartmentIds(): array
    {
        return DepartmentRoute::query()
            ->where('from_department_id', $this->id)
            ->where('is_allowed', true)
            ->pluck('to_department_id')
            ->all();
    }
}
