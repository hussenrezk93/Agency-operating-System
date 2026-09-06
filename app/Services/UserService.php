<?php

namespace App\Services;

use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
    /** Every newly created account gets this password and must change it on first login. */
    private const DEFAULT_TEMPORARY_PASSWORD = 'Demo123!';

    public function __construct(
        private readonly AuditService $audit,
        private readonly EmailVerificationService $emailVerification,
        private readonly ProjectWhatsappService $whatsapp,
        private readonly TaskWorkflowService $workflow,
    ) {}

    /**
     * @param  array{full_name: string, username: string, personal_email: string}  $attributes
     * @return array{user: User, temporary_password: string}
     */
    public function create(RoleCode $role, array $attributes, ?Department $department, User $actor): array
    {
        $this->assertDepartmentRule($role, $department);

        $temporaryPassword = self::DEFAULT_TEMPORARY_PASSWORD;

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

        // BRD §7.3 — a brand-new department member is invited to its active projects.
        if ($department !== null) {
            $this->whatsapp->fanOutForUserJoiningDepartment($user, $department);
        }

        return ['user' => $user, 'temporary_password' => $temporaryPassword];
    }

    /**
     * BRD §18.1 — a SELF-service `personal_email` change does NOT take effect
     * immediately. It goes to `pending_email` and a verification link is sent to the
     * NEW address; the OLD, already-verified address keeps receiving notifications
     * until that link is clicked (`EmailVerificationService::consume()` is what
     * promotes it). Product decision (2026-08): when an Admin/Manager edits someone
     * ELSE's email from `UserController` instead, that verification loop can never
     * complete — the admin doesn't own the new inbox — so the change applies
     * immediately there. Every other attribute (full_name, department_id) always
     * applies immediately either way.
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
        $emailChanged = $newEmail !== null && $newEmail !== $subject->personal_email;
        $selfEdit = $actor->is($subject);
        $shouldIssueVerification = false;

        unset($attributes['personal_email']);

        if ($emailChanged && $selfEdit) {
            // Dedup only applies here: don't re-queue a fresh token/email for an
            // address that's already sitting unverified in pending_email (e.g. an
            // accidental double submit). This must NOT gate the admin branch below —
            // a stale pending_email left over from an earlier, never-completed
            // self-service attempt would otherwise silently block an admin's retry
            // of that same address.
            if ($newEmail !== $subject->pending_email) {
                $attributes['pending_email'] = $newEmail;
                $attributes['pending_email_requested_at'] = now();
                $shouldIssueVerification = true;
            }
        } elseif ($emailChanged) {
            $attributes['personal_email'] = $newEmail;
            $attributes['pending_email'] = null;
            $attributes['pending_email_requested_at'] = null;
            $attributes['email_verified_at'] = now();
        }

        $departmentChanged = array_key_exists('department_id', $attributes)
            && $attributes['department_id'] !== $before['department_id'];
        $previousDepartment = $departmentChanged && $before['department_id'] !== null
            ? Department::find($before['department_id'])
            : null;

        $subject->fill($attributes)->save();

        $this->audit->log(
            action: 'user.updated',
            entityType: 'user',
            entityId: $subject->id,
            before: $before,
            after: array_intersect_key($attributes, $before) + ($shouldIssueVerification ? ['pending_email' => $newEmail] : []),
            actorId: $actor->id,
        );

        if ($shouldIssueVerification) {
            $this->emailVerification->issueFor($subject, $newEmail);
        }

        // BRD §7.3 — moving departments invites the new one's active projects and asks
        // the old one's leader to manually drop the person from its WhatsApp groups.
        if ($departmentChanged) {
            if ($previousDepartment !== null) {
                $this->whatsapp->alertLeaderToRemoveMember($subject, $previousDepartment);
            }
            if ($subject->department !== null) {
                $this->whatsapp->fanOutForUserJoiningDepartment($subject, $subject->department);
            }
        }

        return $subject->refresh();
    }

    /** @param  string  $storedPath  Already-stored path on the `public` disk (e.g. from `$file->store('avatars', 'public')`) — this method never touches an UploadedFile directly. */
    public function updateAvatar(User $subject, string $storedPath, User $actor): User
    {
        $previousPath = $subject->avatar_path;

        $subject->forceFill(['avatar_path' => $storedPath])->save();

        $this->audit->log(
            action: 'user.avatar_updated',
            entityType: 'user',
            entityId: $subject->id,
            before: ['avatar_path' => $previousPath],
            after: ['avatar_path' => $storedPath],
            actorId: $actor->id,
        );

        // Deleted only after the new path is safely saved, so a mid-request failure
        // never leaves the account pointing at a photo that no longer exists on disk.
        if ($previousPath !== null) {
            Storage::disk('public')->delete($previousPath);
        }

        return $subject->refresh();
    }

    public function removeAvatar(User $subject, User $actor): User
    {
        $previousPath = $subject->avatar_path;

        if ($previousPath === null) {
            return $subject;
        }

        $subject->forceFill(['avatar_path' => null])->save();

        $this->audit->log(
            action: 'user.avatar_removed',
            entityType: 'user',
            entityId: $subject->id,
            before: ['avatar_path' => $previousPath],
            after: ['avatar_path' => null],
            actorId: $actor->id,
        );

        Storage::disk('public')->delete($previousPath);

        return $subject->refresh();
    }

    public function disable(User $subject, User $actor): User
    {
        $department = $subject->department;

        // Wrapped in one transaction so a failure partway through (the WhatsApp alert,
        // or the assignment-release loop) can never leave the account marked Inactive
        // while it still holds an open task-step assignment nothing else can touch.
        DB::transaction(function () use ($subject, $actor, $department): void {
            $subject->forceFill(['status' => UserStatus::Inactive->value])->save();

            $this->audit->log(
                action: 'user.disabled',
                entityType: 'user',
                entityId: $subject->id,
                before: ['status' => UserStatus::Active->value],
                after: ['status' => UserStatus::Inactive->value],
                actorId: $actor->id,
            );

            // BRD §7.3 — someone still has to remove them from the WhatsApp groups by hand.
            if ($department !== null) {
                $this->whatsapp->alertLeaderToRemoveMember($subject, $department);
            }

            // BRD §6 — a disabled account cannot receive work, so any step it currently
            // holds is released back to Waiting Assignment for the department to reassign.
            $this->workflow->releaseAssignmentsForDisabledUser($subject, $actor);
        });

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
