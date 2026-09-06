<?php

namespace App\Services;

use App\Jobs\SendPasswordResetJob;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Session authentication — username + password only (BRD §18): no self-registration,
 * no email login.
 * Outcomes: ok | invalid (generic — wrong username and wrong password are
 * indistinguishable) | disabled (deactivated accounts are told so, per the
 * approved BRD/UI login states) | throttled (brute-force protection).
 * Every attempt is audited. Passwords never reach the audit log.
 *
 * CR-002 (2026-09) reversed the earlier "no forgot password self-service" rule —
 * requestPasswordReset()/resetPassword() below are that self-service path, alongside
 * (not replacing) the Manager/Admin-driven UserService::resetPassword().
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
        $this->rotatePassword($user, $newPassword, $request);

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

    /**
     * CR-002 — always a no-op response to the caller, whether or not the username
     * exists: found/not-found, blocked, and unverified-email all fall through silently
     * so the "forgot password" form can never be used to enumerate accounts. The three
     * silent branches perform an equivalent-shaped dummy hash so they cost roughly the
     * same as each other — not full timing-parity with the success path, which isn't
     * worth chasing on an internal system already gated by login rate limiting.
     */
    public function requestPasswordReset(string $username, Request $request): void
    {
        $user = User::where('username', $username)->first();

        if ($user === null || $user->isSignInBlocked() || $user->activeEmail() === null) {
            Hash::make(Str::random(40));

            return;
        }

        $rawToken = Str::random(40);

        PasswordResetToken::create([
            'user_id' => $user->id,
            'token_hash' => Hash::make($rawToken),
            'expires_at' => now()->addHours(2),
            'created_at' => now(),
        ]);

        $resetUrl = route('password.reset.show', [$user, $rawToken]);

        SendPasswordResetJob::dispatch($user, $user->activeEmail(), $resetUrl);

        $this->audit->log(
            action: 'auth.password_reset_requested',
            entityType: 'user',
            entityId: $user->id,
            actorId: null,
            request: $request,
        );
    }

    /** @throws ValidationException when no usable token matches, or the account is now blocked */
    public function resetPassword(User $user, string $rawToken, string $newPassword, Request $request): void
    {
        $token = $user->passwordResetTokens()
            ->usable()
            ->get()
            ->first(fn (PasswordResetToken $candidate): bool => $candidate->matches($rawToken));

        // Blocked is checked here too, not just at issuance — an admin could disable
        // the account in the window between the email being sent and the link being
        // clicked. Folded into the same generic error as a bad token so an
        // unauthenticated requester never learns an account exists and was disabled.
        if ($token === null || $user->isSignInBlocked()) {
            throw ValidationException::withMessages([
                'token' => __('agencyos.password_reset.invalid_or_expired'),
            ]);
        }

        DB::transaction(function () use ($user, $token, $newPassword, $request): void {
            $token->consume();

            $this->rotatePassword($user, $newPassword, $request);

            // A stale second link (still in an inbox or a mail-relay log) must not
            // remain a live account-takeover path once the user has regained access.
            $user->passwordResetTokens()->usable()->update(['consumed_at' => now()]);

            $this->audit->log(
                action: 'auth.password_reset_completed',
                entityType: 'user',
                entityId: $user->id,
                actorId: $user->id,
                request: $request,
            );
        });
    }

    private function rotatePassword(User $user, string $newPassword, Request $request): void
    {
        $user->forceFill([
            'password_hash' => Hash::make($newPassword),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();
    }
}
