<?php

namespace App\Enums;

/**
 * The life of one delivery attempt on one channel.
 *
 * `Bounced` is deliberately distinct from `Failed`: a failure is worth retrying (the
 * provider was down), a bounce is not (the address is wrong) and BRD §20 requires the
 * Manager to be alerted on a final bounce instead.
 */
enum DeliveryStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Bounced = 'bounced';

    public function isRetryable(): bool
    {
        return $this === self::Failed;
    }

    public function isFinal(): bool
    {
        return $this === self::Sent || $this === self::Bounced;
    }
}
