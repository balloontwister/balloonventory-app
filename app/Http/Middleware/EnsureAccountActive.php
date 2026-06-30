<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a frozen user to the account area. They can still sign in, but may
 * only reach their account, profile, preferences, and support — every other
 * route bounces back to the account page with a notice. (A soft suspension for
 * unpaid membership, ToS issues, etc., without destroying the account.)
 */
class EnsureAccountActive
{
    /**
     * Route names a frozen user is still allowed to reach.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'account.index',
        'profile.edit',
        'profile.update',
        'profile.avatar.update',
        'profile.destroy',
        'support.contact',
        'settings.index',
        'settings.preferences.update',
        'locale.switch',
        'logout',
        // A frozen user who also hasn't accepted the terms must still be able to
        // reach the acceptance interstitial (EnsureTermsAccepted runs first).
        'terms.show',
        'terms.accept',
        // Always let an admin exit an impersonation session, even if the
        // impersonated account happens to be frozen.
        'impersonate.stop',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->isFrozen() && ! $this->isAllowed($request)) {
            return redirect()->route('account.index')
                ->with('warning', __('flash.users.frozen_notice'));
        }

        return $next($request);
    }

    private function isAllowed(Request $request): bool
    {
        $name = $request->route()?->getName();

        return $name !== null && in_array($name, self::ALLOWED, true);
    }
}
