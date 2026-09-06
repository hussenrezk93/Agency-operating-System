<?php

namespace App\Models;

use App\Enums\LeadershipType;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'must_change_password' => 'boolean',
        'email_verified_at' => 'datetime',
        'pending_email_requested_at' => 'datetime',
        'status' => UserStatus::class,
    ];

    /** Login = username + password_hash. No self-registration, no email login (BRD §18). */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /** Null when no photo was ever uploaded — every avatar-rendering view falls back to initials. */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
        );
    }

    /** The EFFECTIVE role — temporarily elevated while a temporary-TL period runs (Q2). */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** The substantive role the user returns to; NULL when not elevated (Q2). */
    public function baseRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'base_role_id');
    }

    public function roleTransitions(): HasMany
    {
        return $this->hasMany(UserRoleTransition::class)->orderBy('effective_at');
    }

    public function leadershipAssignments(): HasMany
    {
        return $this->hasMany(DepartmentLeadershipAssignment::class);
    }

    public function primaryLeadershipAssignments(): HasMany
    {
        return $this->leadershipAssignments()
            ->where('assignment_type', LeadershipType::Primary->value);
    }

    public function temporaryLeadershipAssignments(): HasMany
    {
        return $this->leadershipAssignments()
            ->where('assignment_type', LeadershipType::Temporary->value);
    }

    public function createdClients(): HasMany
    {
        return $this->hasMany(Client::class, 'created_by');
    }

    public function createdProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function auditEntries(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function stepAssignments(): HasMany
    {
        return $this->hasMany(TaskStepAssignment::class, 'assignee_id');
    }

    /** Steps currently assigned to this user and still open. */
    public function openStepAssignments(): HasMany
    {
        return $this->stepAssignments()->whereNull('ended_at');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function roleCode(): RoleCode
    {
        return RoleCode::from($this->role->code);
    }

    public function hasRole(RoleCode ...$roles): bool
    {
        return in_array($this->roleCode(), $roles, true);
    }

    /**
     * Only a deactivated account is refused sign-in.
     * `on_leave` users still sign in — APPROVED DECISION Q14: a primary TL on leave
     * keeps read access to their department but performs no leadership action.
     */
    public function isSignInBlocked(): bool
    {
        return $this->status === UserStatus::Inactive;
    }

    /** True while the user is temporarily elevated (Q2). */
    public function isTemporarilyElevated(): bool
    {
        return $this->base_role_id !== null;
    }

    public function substantiveRoleCode(): RoleCode
    {
        return RoleCode::from(($this->base_role_id ? $this->baseRole : $this->role)->code);
    }

    /**
     * Is this user the EFFECTIVE leader of the department right now?
     * Resolved through Department::effectiveLeader(), so a primary TL covered by a
     * temporary leader correctly returns FALSE (approved decisions Q14, Q21).
     * This is the method every policy must use to authorise a leadership action.
     */
    public function leadsDepartment(int $departmentId): bool
    {
        $department = $this->relationLoaded('department') && $this->department?->id === $departmentId
            ? $this->department
            : Department::find($departmentId);

        return $department?->effectiveLeader()?->is($this) ?? false;
    }

    /** Does the user hold a currently active leadership assignment of any type? */
    public function hasLeadershipAssignment(int $departmentId): bool
    {
        return $this->leadershipAssignments()
            ->currentlyActive()
            ->where('department_id', $departmentId)
            ->exists();
    }

    /**
     * APPROVED DECISION Q14 — a primary Team Leader on leave stays signed in and may
     * read, but performs no leadership action while a temporary leader covers the
     * department.
     *
     * DEFECT FIX: the previous implementation asked whether the user still *held* an
     * active leadership assignment. A primary TL keeps that assignment throughout the
     * leave, so the check never fired for the primary TL and wrongly fired for ordinary
     * department members. View-only is now derived from the department's actual
     * leadership resolution:
     *   the user IS the primary leader  AND  somebody ELSE is the effective leader.
     */
    public function isViewOnlyLeader(): bool
    {
        if ($this->department_id === null) {
            return false;
        }

        $department = $this->department;

        if ($department === null) {
            return false;
        }

        $primaryLeader = $department->primaryLeader();

        if ($primaryLeader === null || ! $primaryLeader->is($this)) {
            return false; // only a primary leader can be placed in view-only
        }

        $effectiveLeader = $department->effectiveLeader();

        return $effectiveLeader !== null && ! $effectiveLeader->is($this);
    }

    /** True when the user may execute leadership actions for that department. */
    public function canActAsLeaderOf(int $departmentId): bool
    {
        return $this->leadsDepartment($departmentId) && ! $this->isViewOnlyLeader();
    }

    /** The address the system actually sends to — never the unverified pending one (Q24). */
    public function activeEmail(): ?string
    {
        return $this->email_verified_at !== null ? $this->personal_email : null;
    }

    public function hasPendingEmailChange(): bool
    {
        return $this->pending_email !== null;
    }

    /** CR-001: no email notifications before verification completes. */
    public function canReceiveEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    // ------------------------------------------------ notifications and email

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications(): HasMany
    {
        return $this->notifications()->where('is_read', false);
    }

    public function emailVerificationTokens(): HasMany
    {
        return $this->hasMany(EmailVerificationToken::class);
    }

    public function passwordResetTokens(): HasMany
    {
        return $this->hasMany(PasswordResetToken::class);
    }

    // ------------------------------------------------------------------ chat

    public function chatMemberships(): HasMany
    {
        return $this->hasMany(ChatMember::class);
    }

    /** Conversations the user is currently in — membership is the read permission. */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(ChatConversation::class, 'chat_members', 'user_id', 'conversation_id')
            ->wherePivotNull('left_at')
            ->withPivot(['joined_at', 'left_at']);
    }

    public function chatDigestBatches(): HasMany
    {
        return $this->hasMany(ChatDigestBatch::class);
    }

    // ----------------------------------------------------- invites and scores

    public function projectInviteDeliveries(): HasMany
    {
        return $this->hasMany(ProjectInviteDelivery::class);
    }

    public function performanceSnapshots(): HasMany
    {
        return $this->hasMany(MonthlyPerformanceSnapshot::class);
    }
}
