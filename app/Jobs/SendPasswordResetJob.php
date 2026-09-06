<?php

namespace App\Jobs;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * CR-002 — a failed send just means the user requests the link again; still queued
 * (Q28) and still swallows its own failure rather than becoming a failed queue job,
 * logged instead so an operator can see it. Mirrors SendEmailVerificationJob exactly.
 */
class SendPasswordResetJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $email,
        public readonly string $resetUrl,
    ) {}

    public function handle(): void
    {
        try {
            Mail::to($this->email)->send(new PasswordResetMail($this->user, $this->resetUrl));
        } catch (Throwable $e) {
            Log::warning('Password reset email send failed.', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
