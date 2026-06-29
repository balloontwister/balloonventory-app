<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Scopes\BusinessScope;
use App\Support\AdminBusinessView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // A Super Admin viewing a business as admin operates without a membership.
        if ($user->isSuperAdmin() && AdminBusinessView::isActive()) {
            return $next($request);
        }

        $hasMembership = Membership::withoutGlobalScope(BusinessScope::class)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->exists();

        if (! $hasMembership) {
            return redirect()->route('onboarding.create-business');
        }

        return $next($request);
    }
}
