<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Admin "Switch to user" impersonation. The acting admin's id is stashed in the
 * session (`impersonator_id`) and they're logged in as the target user so they
 * see exactly what the user sees. A persistent banner offers a one-click return,
 * which restores the admin's session. Login-history / last-login records are
 * suppressed during the switch so a support session doesn't masquerade as the
 * user genuinely signing in — see {@see Impersonation}.
 */
class ImpersonationController extends Controller
{
    /**
     * POST /admin/users/{user}/impersonate — begin impersonating $user.
     * Admin-only (route group). Cannot target self, another admin, or a
     * deleted account.
     */
    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        abort_if($user->id === $admin->id, 422, 'You cannot impersonate yourself.');
        abort_if($user->isAnyAdmin(), 422, 'Admin accounts cannot be impersonated.');
        abort_if($user->trashed(), 422, 'A deleted account cannot be impersonated.');
        abort_if($request->session()->has(Impersonation::SESSION_KEY), 422, 'Already impersonating a user.');

        Log::info('Admin impersonation started', [
            'admin_id' => $admin->id,
            'target_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        $request->session()->put(Impersonation::SESSION_KEY, $admin->id);
        $request->session()->put(Impersonation::TRANSITION_KEY, true);
        Auth::login($user);
        $request->session()->forget(Impersonation::TRANSITION_KEY);

        return redirect()->route('dashboard')
            ->with('success', __('flash.impersonation.started', ['name' => $user->name]));
    }

    /**
     * POST /impersonate/stop — return to the admin account. Reachable by any
     * authenticated user (the impersonated account may not be an admin) and is
     * a no-op when not impersonating.
     */
    public function stop(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull(Impersonation::SESSION_KEY);

        if (! $impersonatorId) {
            return redirect()->route('dashboard');
        }

        $impersonatedId = Auth::id();
        $admin = User::find($impersonatorId);

        // The original admin is gone — don't strand the session on the user.
        if (! $admin) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        Log::info('Admin impersonation ended', [
            'admin_id' => $admin->id,
            'target_id' => $impersonatedId,
        ]);

        $request->session()->put(Impersonation::TRANSITION_KEY, true);
        Auth::login($admin);
        $request->session()->forget(Impersonation::TRANSITION_KEY);

        return redirect()->route('admin.users.show', $impersonatedId)
            ->with('success', __('flash.impersonation.stopped'));
    }
}
