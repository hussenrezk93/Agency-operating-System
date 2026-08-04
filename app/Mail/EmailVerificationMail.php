<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * BRD §18.1 — sent to whichever address is being verified (the brand-new
 * `personal_email` on account creation, or the `pending_email` on a change). The link
 * itself is built by the caller (EmailVerificationService) so this class never touches
 * the raw token beyond carrying it for the view.
 */
class EmailVerificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $verifyUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('agencyos.email_verification.mail_subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verify-email');
    }
}
