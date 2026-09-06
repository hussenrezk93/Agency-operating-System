<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * CR-002 (2026-09) — sent to the user's verified `activeEmail()` when a self-service
 * password reset is requested. The link itself is built by the caller
 * (AuthService::requestPasswordReset()) so this class never touches the raw token
 * beyond carrying it for the view.
 */
class PasswordResetMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('agencyos.password_reset.mail_subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset');
    }
}
