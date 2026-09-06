<?php

namespace App\Http\Controllers;

use App\Models\DepartmentDailyReport;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function index(Request $request): View
    {
        // The list view never touches per-channel delivery rows — only the notification
        // itself — so there is nothing to eager-load here.
        $notifications = $request->user()->notifications()
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        // Notification::targetUrl() needs a department_report's report_date, which
        // entity_id alone doesn't carry — resolve every row on this page in one query
        // instead of one query per row (up to 50 on this page).
        $reportDates = DepartmentDailyReport::query()
            ->whereIn('id', $notifications->where('entity_type', 'department_report')->pluck('entity_id'))
            ->pluck('report_date', 'id');

        return view('notifications.index', ['notifications' => $notifications, 'reportDates' => $reportDates]);
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse|JsonResponse
    {
        $this->authorize('markRead', $notification);

        $notification->markRead();
        $targetUrl = $notification->targetUrl();

        if ($request->wantsJson()) {
            return response()->json([
                'count' => $request->user()->unreadNotifications()->count(),
                'url' => $targetUrl,
            ]);
        }

        return redirect($targetUrl ?? route('notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->notifications()->unread()
            ->update(['is_read' => true, 'read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['count' => 0]);
        }

        return redirect()->route('notifications.index')
            ->with('status', __('agencyos.notifications.index.marked_all_read'));
    }
}
