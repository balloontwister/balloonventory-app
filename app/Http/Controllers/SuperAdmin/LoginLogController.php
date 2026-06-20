<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\LoginEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginLogController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'in:success,failed,lockout'],
        ]);

        $events = LoginEvent::query()
            ->with('user:id,name')
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->input('event')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->input('search');
                $q->where(fn ($inner) => $inner
                    ->where('email', 'like', "%{$term}%")
                    ->orWhere('ip_address', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")));
            })
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $events->through(fn (LoginEvent $e) => [
            'id' => $e->id,
            'event' => $e->event,
            'email' => $e->email,
            'ip_address' => $e->ip_address,
            'user_agent' => $e->user_agent,
            'created_at' => $e->created_at,
            'user' => $e->user ? ['id' => $e->user->id, 'name' => $e->user->name] : null,
        ]);

        return Inertia::render('SuperAdmin/LoginLog/Index', [
            'events' => $events,
            'filters' => [
                'search' => $request->input('search', ''),
                'event' => $request->input('event', ''),
            ],
            'failed7d' => LoginEvent::whereIn('event', [LoginEvent::FAILED, LoginEvent::LOCKOUT])
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ]);
    }
}
