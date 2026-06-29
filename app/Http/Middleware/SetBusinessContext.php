<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Scopes\BusinessScope;
use App\Support\AdminBusinessView;
use App\Support\BusinessContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetBusinessContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Super-Admin "View as business": point the tenant scope at the viewed
        // business even though the admin isn't a member of it.
        if ($user->isSuperAdmin() && AdminBusinessView::isActive()) {
            BusinessContext::set(AdminBusinessView::businessId());

            return $next($request);
        }

        $memberships = Membership::withoutGlobalScope(BusinessScope::class)
            ->with('business')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get();

        if ($memberships->isEmpty()) {
            return $next($request);
        }

        $sessionId = $request->session()->get('current_business_id');

        // Prefer businesses the user can actually use: real access (not 'none')
        // and not suspended (frozen). This keeps a suspended or no-access
        // membership from landing the user in an unusable business when they
        // have other working ones. (If ALL are unusable we still fall back so
        // the request can resolve; EnsureBusinessActive then gates it.)
        $accessible = $memberships->filter(
            fn (Membership $m) => $m->role !== 'none' && ! ($m->business?->isFrozen() ?? false)
        );
        $pool = $accessible->isNotEmpty() ? $accessible : $memberships;

        $current = $pool->firstWhere('business_id', $sessionId)
            ?? $pool->first();

        BusinessContext::set($current->business_id);

        // Keep session in sync if it was missing or stale.
        if ($sessionId !== $current->business_id) {
            $request->session()->put('current_business_id', $current->business_id);
        }

        return $next($request);
    }
}
