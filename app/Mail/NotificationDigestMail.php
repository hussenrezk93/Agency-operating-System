<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Product decision (on top of Q28) — one email per recipient batching every pending
 * notification, instead of one email per event, so a busy workflow does not turn into
 * an inbox flood. Sent by agencyos:notification-digest-sweep, at most every 3 hours.
 */
class NotificationDigestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @param  Collection<int, Notification>  $notifications */
    public function __construct(public readonly Collection $notifications) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('agencyos.notifications.email.digest_subject', ['count' => $this->notifications->count()]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification-digest');
    }
}
