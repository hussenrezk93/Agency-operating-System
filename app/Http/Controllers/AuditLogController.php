<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * BRD §19 — read-only, Admin-only. audit_logs is append-only at the database level
 * (see the migration's trigger pair); nothing here ever writes to it.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        return view('audit-log.index', [
            'logs' => $this->filtered($request)
                ->with('actor:id,full_name')
                ->latest('created_at')
                ->paginate(30)
                ->withQueryString(),
            'actors' => User::whereIn('id', AuditLog::query()->whereNotNull('actor_user_id')->distinct()->pluck('actor_user_id'))
                ->orderBy('full_name')
                ->get(['id', 'full_name']),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    /** Same filters as the list, streamed as CSV instead of paginated HTML. */
    public function export(Request $request): Response
    {
        $this->authorize('export', AuditLog::class);

        $rows = $this->filtered($request)->with('actor:id,full_name')->latest('created_at')->get();

        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, ['Timestamp', 'Actor', 'Action', 'Entity type', 'Entity ID', 'IP address']);
        foreach ($rows as $log) {
            fputcsv($csv, [
                $log->created_at->toDateTimeString(),
                $log->actor?->full_name ?? 'system',
                $log->action,
                $log->entity_type,
                $log->entity_id,
                $log->ip_address,
            ]);
        }
        rewind($csv);
        $contents = stream_get_contents($csv);
        fclose($csv);

        return response($contents, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="agencyos-audit-log-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    private function filtered(Request $request): Builder
    {
        $query = AuditLog::query();

        if ($actorId = $request->query('actor_id')) {
            $query->where('actor_user_id', $actorId);
        }
        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }
        if ($date = $request->query('date')) {
            $query->whereDate('created_at', $date);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($sub) use ($q): void {
                $sub->where('action', 'like', "%{$q}%")
                    ->orWhere('entity_type', 'like', "%{$q}%");
            });
        }

        return $query;
    }
}
