<?php

namespace App\Enums;

/** BRD §7.3 — every edit of the group link creates a new version carrying one of these. */
enum WhatsappLinkAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Removed = 'removed';

    /** A removal clears the URL; the other two must carry one (enforced by a CHECK). */
    public function clearsLink(): bool
    {
        return $this === self::Removed;
    }
}
