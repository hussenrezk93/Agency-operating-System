<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        // The list view never touches per-channel delivery rows — only the notification
        // itself — so there is nothing to eager-load here.
        $notifications = $request->user()->notifications()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('notifications.index', ['notifications' => $notifications]);
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        $this->authorize('markRead', $notification);

        $notification->markRead();

        return redirect()->route('notifications.index');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->notifications()->unread()
            ->update(['is_read' => true, 'read_at' => now()]);

        return redirect()->route('notifications.index')
            ->with('status', __('agencyos.notifications.index.marked_all_read'));
    }
}
