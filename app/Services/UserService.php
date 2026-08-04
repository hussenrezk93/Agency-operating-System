<?php

namespace App\Services;

use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * BRD §18 — no self-registration; accounts are created by an administrator, issued a
 * temporary password, and forced to change it on first login. BRD §6 — a TL/Employee
 * belongs to exactly one department; Admin/Manager belong to none.
 * HARD RULE (see AuditService): the plaintext password is returned to the caller once,
 * for hand-off to the new user, and is never written to the audit log.
 */
class UserService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly EmailVerificationService $emailVerification,
    ) {}

    /**
     * @param  array{full_name: string, username: string, personal_email: string}  $attributes
     * @return array{user: User, temporary_password: string}
     */
    public function create(RoleCode $role, array $attributes, ?Department $department, User $actor): array
    {
        $this->assertDepartmentRule($role, $department);

        $temporaryPassword = Str::password(12);

        $user = User::create($attributes + [
            'role_id' => Role::where('code', $role->value)->firstOrFail()->id,
            'department_id' => $department?->id,
            'password_hash' => Hash::make($temporaryPassword),
            'status' => UserStatus::Active->value,
            'must_change_password' => true,
        ]);

        $this->audit->log(
            action: 'user.created',
            entityType: 'user',
            entityId: $user->id,
            after: [
                'role' => $role->value,
                'department_id' => $department?->id,
                'username' => $user->username,
            ],
            actorId: $actor->id,
        );

        $this->emailVerification->issueFor($user, $user->personal_email);

        return ['user' => $user, 'temporary_password' => $temporaryPassword];
    }

    /**
     * BRD §18.1 — a `personal_email` change does NOT take effect immediately. It goes
     * to `pending_email` and a verification link is sent to the NEW address; the OLD,
     * already-verified address keeps receiving notifications until that link is
     * clicked (`EmailVerificationService::consume()` is what promotes it). Every other
     * attribute (full_name, department_id) still applies immediately.
     */
    public function updateProfile(User $subject, array $attributes, User $actor): User
    {
        $before = [
            'full_name' => $subject->full_name,
            'personal_email' => $subject->personal_email,
            'department_id' => $subject->department_id,
        ];

        if (array_key_exists('department_id', $attributes)) {
            $targetDepartment = $attributes['department_id'] !== null
                ? Department::find($attributes['department_id'])
                : null;

            $this->assertDepartmentRule($subject->roleCode(), $targetDepartment);
        }

        $newEmail = $attributes['personal_email'] ?? null;
        $emailChanged = $newEmail !== null
            && $newEmail !== $subject->personal_email
            && $newEmail !== $subject->pending_email;

        unset($attributes['personal_email']);

        if ($emailChanged) {
            $attributes['pending_email'] = $newEmail;
            $attributes['pending_email_requested_at'] = now();
        }

        $subject->fill($attributes)->save();

        $this->audit->log(
            action: 'user.updated',
            entityType: 'user',
            entityId: $subject->id,
            before: $before,
            after: array_intersect_key($attributes, $before) + ($emailChanged ? ['pending_email' => $newEmail] : []),
            actorId: $actor->id,
        );

        if ($emailChanged) {
            $this->emailVerification->issueFor($subject, $newEmail);
        }

        return $subject->refresh();
    }

    public function disable(User $subject, User $actor): User
    {
        $subject->forceFill(['status' => UserStatus::Inactive->value])->save();

        $this->audit->log(
            action: 'user.disabled',
            entityType: 'user',
            entityId: $subject->id,
            before: ['status' => UserStatus::Active->value],
            after: ['status' => UserStatus::Inactive->value],
            actorId: $actor->id,
        );

        return $subject->refresh();
    }

    public function reactivate(User $subject, User $actor): User
    {
        $subject->forceFill(['status' => UserStatus::Active->value])->save();

        $this->audit->log(
            action: 'user.reactivated',
            entityType: 'user',
            entityId: $subject->id,
            before: ['status' => UserStatus::Inactive->value],
            after: ['status' => UserStatus::Active->value],
            actorId: $actor->id,
        );

        return $subject->refresh();
    }

    /** @return string the new temporary password, returned once for hand-off */
    public function resetPassword(User $subject, User $actor): string
    {
        $temporaryPassword = Str::password(12);

        $subject->forceFill([
            'password_hash' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ])->save();

        $this->audit->log(
            action: 'user.password_reset',
            entityType: 'user',
            entityId: $subject->id,
            after: ['must_change_password' => true],
            actorId: $actor->id,
        );

        return $temporaryPassword;
    }

    private function assertDepartmentRule(RoleCode $role, ?Department $department): void
    {
        $requiresDepartment = in_array($role, [RoleCode::TeamLeader, RoleCode::Employee], true);

        if ($requiresDepartment && $department === null) {
            throw ValidationException::withMessages([
                'department_id' => __('A Team Leader or Employee must belong to a department.'),
            ]);
        }

        if (! $requiresDepartment && $department !== null) {
            throw ValidationException::withMessages([
                'department_id' => __('Only a Team Leader or Employee may belong to a department.'),
            ]);
        }
    }
}
