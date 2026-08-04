<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Plain (non-queued) Mailable — the email leg of every generic in-app notification.
 * Sent synchronously FROM WITHIN SendNotificationEmailJob, which does its own
 * try/catch around the send so a transport failure never escapes as an unhandled
 * queue-job failure (see the job's docblock for why).
 */
class NotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Notification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notification->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification');
    }
}
