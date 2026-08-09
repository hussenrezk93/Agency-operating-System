<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * A short, human-readable label for an audit log row's `action` string — used only by
 * the Admin dashboard's "Recent Activity" widget. The full audit log page itself
 * (AuditLogController) is untouched and keeps showing the raw action string; this is
 * purely a presentational label for a tighter widget context.
 */
final class AuditLogPresenter
{
    public static function describe(AuditLog $log): string
    {
        $key = 'agencyos.audit_log.actions.'.$log->action;

        if (Lang::has($key)) {
            return __($key);
        }

        // Fallback for any action string not yet in the map — never a blank/raw dump.
        return Str::of($log->action)->replace(['.', '_'], ' ')->headline()->toString();
    }
}
