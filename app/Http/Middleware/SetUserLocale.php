<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->locale) {
            app()->setLocale($user->locale);
        } elseif ($sessionLocale = $request->session()->get('locale')) {
            app()->setLocale($sessionLocale);
        }

        return $next($request);
    }
}
