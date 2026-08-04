<?php

namespace App\Services;

use App\Jobs\SendEmailVerificationJob;
use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * BRD §18.1 — a 24-hour, single-use, hash-stored verification link. Used both for a
 * brand-new account's `personal_email` and for a `pending_email` change on an already
 * verified account (`UserService::updateProfile()` writes to `pending_email` instead of
 * overwriting `personal_email` directly — see that class for why).
 */
class EmailVerificationService
{
    public function __construct(private readonly AuditService $audit) {}

    public function issueFor(User $user, string $email): void
    {
        $rawToken = Str::random(40);

        EmailVerificationToken::create([
            'user_id' => $user->id,
            'token_hash' => Hash::make($rawToken),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
        ]);

        $verifyUrl = route('email.verify', [$user, $rawToken]);

        SendEmailVerificationJob::dispatch($user, $email, $verifyUrl);
    }

    /** @throws ValidationException when no usable token matches */
    public function consume(User $user, string $rawToken): User
    {
        $token = $user->emailVerificationTokens()
            ->usable()
            ->get()
            ->first(fn (EmailVerificationToken $candidate): bool => $candidate->matches($rawToken));

        if ($token === null) {
            throw ValidationException::withMessages([
                'token' => __('This verification link is invalid or has expired.'),
            ]);
        }

        $token->consume();

        $before = ['personal_email' => $user->personal_email, 'email_verified_at' => $user->email_verified_at];

        if ($user->pending_email !== null) {
            $user->forceFill([
                'personal_email' => $user->pending_email,
                'pending_email' => null,
                'pending_email_requested_at' => null,
                'email_verified_at' => now(),
            ])->save();
        } else {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $this->audit->log(
            action: 'user.email_verified',
            entityType: 'user',
            entityId: $user->id,
            before: $before,
            after: ['personal_email' => $user->personal_email, 'email_verified_at' => $user->email_verified_at],
            actorId: $user->id,
        );

        return $user->refresh();
    }
}
