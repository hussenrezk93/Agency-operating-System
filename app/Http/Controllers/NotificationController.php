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
        $notifications = $request->user()->notifications()
            ->with('deliveries')
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
}
