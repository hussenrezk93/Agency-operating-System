<?php

namespace App\Jobs;

use App\Mail\EmailVerificationMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Appendix B — email verification has no in-app leg and no delivery ledger row (unlike
 * every other notification): a failed send just means the user requests a resend.
 * Still queued (Q28) and still swallows its own failure rather than becoming a failed
 * queue job, logged instead so an operator can see it.
 */
class SendEmailVerificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $email,
        public readonly string $verifyUrl,
    ) {}

    public function handle(): void
    {
        try {
            Mail::to($this->email)->send(new EmailVerificationMail($this->user, $this->verifyUrl));
        } catch (Throwable $e) {
            Log::warning('Email verification send failed.', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
