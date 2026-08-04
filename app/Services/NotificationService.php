<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Models\User;

/**
 * PHASE 7 — the ONLY writer of `notifications`/`notification_deliveries`. Every
 * listener and the WhatsApp invite activation call into this one method so the
 * channel-gating rule (BRD §11.1: no email until the recipient's address is verified)
 * is enforced in exactly one place.
 *
 * In-app delivery is synchronous: creating the Notification row IS the delivery, so its
 * NotificationDelivery row is written already `Sent`. The email leg is written `Queued`
 * here but deliberately NOT sent immediately — `agencyos:notification-digest-sweep`
 * batches every recipient's pending rows into one email at most every 3 hours, so a
 * busy workflow does not turn into one email per event (product decision, on top of
 * Q28's "never blocks the caller").
 *
 * `entityType`/`entityId` follow the same short-string convention AuditService uses
 * ('task', 'task_step', 'project'...), not a fully-qualified class name.
 */
class NotificationService
{
    /**
     * @param  bool  $allowEmail  false forces in-app only, regardless of verification —
     *                            used by the delivery-failure alert (BRD §20.1: never
     *                            alert about an email failure BY email).
     */
    public function notify(
        User $recipient,
        string $type,
        string $title,
        string $body,
        ?string $entityType = null,
        ?int $entityId = null,
        bool $allowEmail = true,
    ): Notification {
        $notification = $this->createRecord($recipient, $type, $title, $body, $entityType, $entityId);

        $notification->deliveries()->create([
            'channel' => NotificationChannel::InApp->value,
            'status' => DeliveryStatus::Sent->value,
            'sent_at' => now(),
            'created_at' => now(),
        ]);

        if ($allowEmail && $recipient->canReceiveEmail()) {
            // Left `Queued` on purpose — agencyos:notification-digest-sweep picks it up
            // and batches it with the recipient's other pending rows.
            $notification->deliveries()->create([
                'channel' => NotificationChannel::Email->value,
                'status' => DeliveryStatus::Queued->value,
                'created_at' => now(),
            ]);
        }

        return $notification;
    }

    /**
     * The bare logical event, with no `notification_deliveries` rows and no email
     * dispatch — used only by `ProjectWhatsappService`, which already has its OWN
     * per-channel delivery ledger (`project_invite_deliveries`) and its own Mailable
     * carrying the actual group link. Going through `notify()` there would fire a
     * second, generic email alongside the WhatsApp-specific one.
     */
    public function createRecord(
        User $recipient,
        string $type,
        string $title,
        string $body,
        ?string $entityType = null,
        ?int $entityId = null,
    ): Notification {
        return Notification::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'is_read' => false,
            'created_at' => now(),
        ]);
    }
}
