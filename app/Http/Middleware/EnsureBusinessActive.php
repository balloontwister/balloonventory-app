<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Support\AdminBusinessView;
use App\Support\BusinessContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hard-enforces business suspension. When the user's current business is frozen
 * (suspended by an admin), every business-scoped route bounces back to the
 * account hub with a notice — so members can still sign in and reach their
 * account/settings, but cannot transact within a suspended business.
 *
 * This is the server-side teeth behind the "suspended business = No Access"
 * model; the frontend (BusinessSwitcher) additionally prevents entering one.
 * Runs after SetBusinessContext (which sets the current business id) and only
 * matters on routes already behind the `ensure.business` group.
 */
class EnsureBusinessActive
{
    /**
     * Routes a member of a suspended business may still reach. These are the
     * neutral, user-level pages that live inside the business-gated group;
     * everything else in that group is blocked while suspended.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'account.index',
        'settings.index',
        'settings.preferences.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // A Super Admin viewing a business as admin may enter it even when it's
        // suspended — that's often the reason they're going in.
        if (AdminBusinessView::isActive() && $request->user()?->isSuperAdmin()) {
            return $next($request);
        }

        $businessId = BusinessContext::currentId();

        if ($businessId === null || $this->isAllowed($request)) {
            return $next($request);
        }

        $business = Business::find($businessId);

        if ($business && $business->isFrozen()) {
            return redirect()->route('account.index')
                ->with('warning', __('flash.businesses.frozen_notice'));
        }

        return $next($request);
    }

    private function isAllowed(Request $request): bool
    {
        $name = $request->route()?->getName();

        return $name !== null && in_array($name, self::ALLOWED, true);
    }
}
