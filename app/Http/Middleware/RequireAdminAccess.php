<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAnyAdmin()) {
            abort(403);
        }

        return $next($request);
    }
}
