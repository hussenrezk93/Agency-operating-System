<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Enums\SnapshotType;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * BRD §17 — a single person's score, due/on-time/late breakdown, and step history.
 * No `$user` param = the actor's own; a named `$user` is gated by
 * `UserPolicy::viewPerformance()` (self, Manager, or same-department TL).
 */
class PerformanceController extends Controller
{
    public function show(Request $request, ?User $user = null): View
    {
        $subject = $user ?? $request->user();

        $this->authorize('viewPerformance', $subject);

        $type = $subject->hasRole(RoleCode::TeamLeader) ? SnapshotType::TlPersonal : SnapshotType::Employee;

        $snapshots = $subject->performanceSnapshots()
            ->where('snapshot_type', $type->value)
            ->orderByDesc('month_start')
            ->limit(6)
            ->get();

        $current = $snapshots->firstWhere('month_start', now()->startOfMonth()->toDateString());

        $recentSteps = TaskStep::whereHas('assignments', fn ($q) => $q->where('assignee_id', $subject->id))
            ->whereNotNull('current_due_at')
            ->with('task:id,title,task_code')
            ->orderByDesc('current_due_at')
            ->limit(15)
            ->get();

        return view('performance.show', [
            'subject' => $subject,
            'isSelf' => $subject->is($request->user()),
            'current' => $current,
            'history' => $snapshots,
            'recentSteps' => $recentSteps,
        ]);
    }
}
