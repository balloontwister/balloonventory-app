<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $this->captureTimezone($request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Persist the browser-reported IANA timezone on first login so that
     * the user does not have to set it manually. Re-detection on every
     * login would clobber an explicit user choice from Preferences.
     */
    private function captureTimezone(Request $request): void
    {
        $user = $request->user();

        if (! $user || $user->timezone) {
            return;
        }

        $timezone = (string) $request->input('timezone', '');

        if ($timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return;
        }

        $user->forceFill(['timezone' => $timezone])->save();
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
