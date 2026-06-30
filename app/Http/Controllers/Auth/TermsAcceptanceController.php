<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The acceptance interstitial: shown to a logged-in user who hasn't accepted the
 * current Terms/Privacy (gated by EnsureTermsAccepted). Covers first-time
 * acceptance for invited/magic-link users and re-acceptance after a version bump.
 */
class TermsAcceptanceController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Auth/AcceptTerms', [
            'termsUrl' => route('legal.terms'),
            'privacyUrl' => route('legal.privacy'),
            // True when they accepted a prior version (re-acceptance) vs never.
            'previouslyAccepted' => $request->user()->terms_accepted_at !== null,
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $request->validate([
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('legal.consent.error'),
        ]);

        $request->user()->forceFill([
            'terms_accepted_at' => now(),
            'terms_version' => config('legal.terms_version'),
        ])->save();

        return redirect()->intended(route('dashboard'));
    }
}
