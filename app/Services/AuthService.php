<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Session authentication — username + password only (BRD §18):
 * no self-registration, no email login, no "forgot password" self-service.
 * Outcomes: ok | invalid (generic — wrong username and wrong password are
 * indistinguishable) | disabled (deactivated accounts are told so, per the
 * approved BRD/UI login states) | throttled (brute-force protection).
 * Every attempt is audited. Passwords never reach the audit log.
 */
class AuthService
{
    public function __construct(private readonly AuditService $audit) {}

    /** Failed sign-in attempts allowed before a temporary lockout. */
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function attempt(string $username, string $password, Request $request, ?string $throttleKey = null): string
    {
        $throttleKey ??= strtolower($username).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $this->audit->log(
                action: 'auth.login_throttled',
                entityType: 'user',
                entityId: null,
                after: [
                    'username_attempted' => $username,
                    'available_in_seconds' => RateLimiter::availableIn($throttleKey),
                ],
                actorId: null,
                request: $request,
            );

            return 'throttled';
        }

        $user = User::where('username', $username)->first();

        if (! $user || ! Hash::check($password, $user->password_hash)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            $this->audit->log(
                action: 'auth.login_failed',
                entityType: 'user',
                entityId: $user?->id,
                after: ['username_attempted' => $username, 'reason' => 'invalid_credentials'],
                actorId: null,
                request: $request,
            );

            return 'invalid';
        }

        if ($user->isSignInBlocked()) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            $this->audit->log(
                action: 'auth.login_blocked',
                entityType: 'user',
                entityId: $user->id,
                after: ['reason' => 'account_inactive'],
                actorId: null,
                request: $request,
            );

            return 'disabled';
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user);
        $request->session()->regenerate(); // session-fixation protection

        $this->audit->log(
            action: 'auth.login_succeeded',
            entityType: 'user',
            entityId: $user->id,
            actorId: $user->id,
            request: $request,
        );

        return 'ok';
    }

    public function logout(Request $request): void
    {
        $userId = Auth::id();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($userId !== null) {
            $this->audit->log(
                action: 'auth.logout',
                entityType: 'user',
                entityId: $userId,
                actorId: $userId,
                request: $request,
            );
        }
    }

    /** Forced rotation of a temporary password (BRD §18). */
    public function completeForcedPasswordChange(User $user, string $newPassword, Request $request): void
    {
        $user->forceFill([
            'password_hash' => Hash::make($newPassword),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        $this->audit->log(
            action: 'auth.forced_password_changed',
            entityType: 'user',
            entityId: $user->id,
            before: ['must_change_password' => true],
            after: ['must_change_password' => false],
            actorId: $user->id,
            request: $request,
        );
    }
}
