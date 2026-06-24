<?php

namespace App\Http\Controllers;

use App\Support\NotificationPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * GET /notifications — the full notification center (history), paginated.
     */
    public function index(Request $request): Response
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        return Inertia::render('Notifications/Index', [
            'notifications' => NotificationPresenter::paginated($request->user(), $filter),
            'filter' => $filter,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

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
