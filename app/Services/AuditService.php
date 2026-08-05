<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * The ONLY writer for audit_logs (append-only; a pair of MySQL triggers blocks edits).
 * Captures actor, action, entity, before/after, IP, and user agent.
 * user_agent is stored inside metadata to stay faithful to the ERD column set.
 * HARD RULE: never pass passwords, hashes, tokens, or chat message content here.
 */
class AuditService
{
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        array $before = [],
        array $after = [],
        ?int $actorId = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::create([
            'actor_user_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => [
                'before' => $before,
                'after' => $after,
                'user_agent' => $request?->userAgent(),
            ],
            'ip_address' => $request?->ip(),
        ]);
    }
}
