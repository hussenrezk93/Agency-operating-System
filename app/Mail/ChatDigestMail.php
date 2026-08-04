<?php

namespace App\Mail;

use App\Models\ChatDigestBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** BRD §11.1 / §14 — the 2-hour chat email digest, one per recipient per closed window. */
class ChatDigestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly ChatDigestBatch $batch) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('agencyos.notifications.email.chat_digest_subject', ['count' => $this->batch->message_count]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.chat-digest',
            with: ['messages' => $this->batch->messages()->visible()->with(['conversation', 'sender'])->get()],
        );
    }
}
