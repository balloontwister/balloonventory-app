<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\MagicLoginLink;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Copyable, single-use, short-lived passwordless login links.
 *
 * - {@see store} (admin-only) mints a link and returns its URL for the admin to
 *   copy and hand to a user (or use themselves).
 * - {@see show} / {@see consume} are the public landing: a click-to-confirm
 *   interstitial so email scanners that follow links can't silently burn the
 *   single-use token before the recipient gets to it.
 */
class MagicLoginLinkController extends Controller
{
    /**
     * POST /admin/users/{user}/magic-login — generate a link for $user.
     * Returns JSON so the action menu can drop it straight onto the clipboard.
     */
    public function store(Request $request, User $user): JsonResponse
    {
        abort_if($user->isAnyAdmin(), 422, 'Admin accounts cannot be issued magic login links.');
        abort_if($user->trashed(), 422, 'A deleted account cannot be issued a magic login link.');

        ['token' => $token] = MagicLoginLink::generate($user, $request->user());

        Log::info('Magic login link generated', [
            'admin_id' => $request->user()->id,
            'target_id' => $user->id,
        ]);

        return response()->json([
            'url' => route('magic-login.show', $token),
            'expires_in_minutes' => MagicLoginLink::EXPIRY_MINUTES,
        ]);
    }

    /**
     * GET /magic-login/{token} — the confirmation landing. Validates the link
     * but does NOT consume it; the recipient confirms with a POST.
     */
    public function show(string $token): Response|RedirectResponse
    {
        $link = $this->resolveUsableLink($token);

        if (! $link) {
            return redirect()->route('login')
                ->with('error', __('flash.magic_login.invalid'));
        }

        return Inertia::render('Auth/MagicLogin', [
            'token' => $token,
            'userName' => $link->user->name,
        ]);
    }

    /**
     * POST /magic-login/{token} — burn the link and sign in as its user.
     */
    public function consume(Request $request, string $token): RedirectResponse
    {
        $link = $this->resolveUsableLink($token);

        if (! $link) {
            return redirect()->route('login')
                ->with('error', __('flash.magic_login.invalid'));
        }

        $link->forceFill(['used_at' => now()])->save();

        Auth::login($link->user);
        $request->session()->regenerate();

        Log::info('Magic login link consumed', [
            'target_id' => $link->user_id,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('dashboard')
            ->with('success', __('flash.magic_login.signed_in'));
    }

    /**
     * Look up a link by its raw token and confirm it's still good to use — not
     * expired, not already burned, and pointing at a live non-admin account.
     */
    private function resolveUsableLink(string $token): ?MagicLoginLink
    {
        $link = MagicLoginLink::where('token_hash', MagicLoginLink::hashToken($token))
            ->with('user')
            ->first();

        if (! $link || ! $link->isUsable()) {
            return null;
        }

        // Re-check the target at consume time: the account may have been deleted
        // or promoted to admin after the link was minted.
        if (! $link->user || $link->user->trashed() || $link->user->isAnyAdmin()) {
            return null;
        }

        return $link;
    }
}
