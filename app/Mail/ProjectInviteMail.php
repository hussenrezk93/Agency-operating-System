<?php

namespace App\Mail;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * BRD §7.3 — the current WhatsApp group link for a project, sent to a resolved member.
 * Agency OS never calls the WhatsApp API; this just hands the group URL to the recipient.
 */
class ProjectInviteMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Project $project,
        public readonly string $groupUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('agencyos.notifications.email.project_invite_subject', ['project' => $this->project->name]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.project-invite');
    }
}
