<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * DELETE /notifications/{notification} — dismiss a notice by marking it read.
     * Scoped to the authenticated user's own unread notifications.
     */
    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $request->user()->unreadNotifications()
            ->where('id', $notification)
            ->first()
            ?->markAsRead();

        return back();
    }

    /**
     * POST /notifications/read-all — mark every unread notification as read.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
