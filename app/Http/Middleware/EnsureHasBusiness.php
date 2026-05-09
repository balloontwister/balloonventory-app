<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Scopes\BusinessScope;
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
