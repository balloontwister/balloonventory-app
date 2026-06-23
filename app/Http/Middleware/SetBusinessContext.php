<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Scopes\BusinessScope;
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

        $memberships = Membership::withoutGlobalScope(BusinessScope::class)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get();

        if ($memberships->isEmpty()) {
            return $next($request);
        }

        $sessionId = $request->session()->get('current_business_id');

        // Prefer businesses where the user has real access (not 'none').
        // This prevents a suspended membership from locking the user out of
        // the dashboard entirely when they have other active businesses.
        $accessible = $memberships->where('role', '!=', 'none');
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
