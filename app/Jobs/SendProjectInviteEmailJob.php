<?php

namespace App\Jobs;

use App\Mail\ProjectInviteMail;
use App\Models\ProjectInviteDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * BRD §7.3 / Q28 — same never-block, never-framework-retry shape as
 * SendNotificationEmailJob, but `project_invite_deliveries` has no `attempt_count`/
 * `next_attempt_at` columns (unlike `notification_deliveries`), so its retry sweep can
 * only re-attempt every `Failed` row on each pass rather than back off — the 10-minute
 * sweep cadence is the only throttle. Acceptable here: invite volume is one row per
 * project member per link version, not per workflow event.
 */
class SendProjectInviteEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function __construct(public readonly ProjectInviteDelivery $delivery) {}

    public function handle(): void
    {
        $recipient = $this->delivery->user;
        $address = $recipient->activeEmail();
        $groupUrl = $this->delivery->project->whatsapp_group_url;

        if ($address === null || $groupUrl === null) {
            $this->delivery->markFailed('No verified recipient email or no group link currently set.');

            return;
        }

        try {
            Mail::to($address)->send(new ProjectInviteMail($this->delivery->project, $groupUrl));
            $this->delivery->markSent();
        } catch (Throwable $e) {
            $this->delivery->markFailed($e->getMessage());
        }
    }
}
