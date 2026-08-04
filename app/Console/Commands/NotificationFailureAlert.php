<?php

namespace App\Console\Commands;

use App\Enums\DeliveryStatus;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * BRD §20.1 — "Mail failure or bounce | alert Manager on repeated failure." In-app
 * only, deliberately: alerting about email failures BY email risks the same failure
 * mode. Runs daily, so the 24h lookback window is one cycle, not a de-dupe mechanism —
 * a delivery stuck in the same state across multiple days will be reported again each
 * run (no separate "already alerted" flag exists in this ledger).
 */
class NotificationFailureAlert extends Command
{
    private const MAX_ATTEMPTS = 5;

    protected $signature = 'agencyos:notification-failure-alert';

    protected $description = 'Alert every Manager, in-app only, about notifications that bounced or exhausted retries in the last day.';

    public function handle(NotificationService $notifications): int
    {
        $since = now()->subDay();

        $bounced = NotificationDelivery::query()
            ->where('status', DeliveryStatus::Bounced->value)
            ->where('bounced_at', '>=', $since)
            ->count();

        $exhausted = NotificationDelivery::query()
            ->where('status', DeliveryStatus::Failed->value)
            ->where('attempt_count', '>=', self::MAX_ATTEMPTS)
            ->where('last_attempt_at', '>=', $since)
            ->count();

        if ($bounced === 0 && $exhausted === 0) {
            $this->info('No repeated delivery failures in the last day.');

            return self::SUCCESS;
        }

        $managers = User::query()
            ->whereHas('role', fn ($q) => $q->where('code', RoleCode::Manager->value))
            ->where('status', UserStatus::Active->value)
            ->get();

        foreach ($managers as $manager) {
            $notifications->notify(
                $manager,
                'notification.delivery_failures',
                __('agencyos.notifications.messages.delivery_failure_title'),
                __('agencyos.notifications.messages.delivery_failure_body', [
                    'bounced' => $bounced,
                    'exhausted' => $exhausted,
                ]),
                allowEmail: false,
            );
        }

        $this->info(sprintf('Alerted %d manager(s) — bounced: %d, exhausted: %d.', $managers->count(), $bounced, $exhausted));

        return self::SUCCESS;
    }
}
