<?php

namespace App\Http\Controllers;

use App\Models\BusinessInvitation;
use App\Models\Membership;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    /**
     * GET /invitations/{token}/accept — magic-link, auth optional.
     * Logs in the invitee if they arrive as a guest, then accepts.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = BusinessInvitation::withoutGlobalScope(BusinessScope::class)
            ->where('token', $token)
            ->with(['invitedUser', 'business'])
            ->first();

        if (! $invitation || ! $invitation->isAcceptable()) {
            return redirect()->route('login')
                ->with('error', __('flash.invitations.invalid_link'));
        }

        $invitedUser = $invitation->invitedUser;

        if (! $request->user()) {
            Auth::login($invitedUser);
            $request->session()->regenerate();
        } elseif ($request->user()->id !== $invitedUser->id) {
            return redirect()->route('login')
                ->with('error', __('flash.invitations.wrong_account'));
        }

        $this->acceptInvitation($invitation);

        // Magic-link: user followed a link for this specific business, so switch to it.
        session()->put('current_business_id', $invitation->business->id);
        BusinessContext::set($invitation->business->id);

        return redirect()->route('dashboard')
            ->with('success', __('flash.invitations.accepted', ['business' => $invitation->business->name]));
    }

    /**
     * POST /invitations/accept-in-app — already authenticated dashboard path.
     */
    public function acceptInApp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $invitation = BusinessInvitation::withoutGlobalScope(BusinessScope::class)
            ->where('token', $validated['token'])
            ->where('invited_user_id', $request->user()->id)
            ->with('business')
            ->first();

        if (! $invitation || ! $invitation->isAcceptable()) {
            return back()->with('error', __('flash.invitations.invalid_link'));
        }

        $this->acceptInvitation($invitation);

        // Stay in the current business — the membership notice on the dashboard
        // already informs the user about their new access.
        return back()->with('success', __('flash.invitations.accepted', ['business' => $invitation->business->name]));
    }

    /**
     * POST /invitations/decline — authenticated dashboard path.
     */
    public function decline(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $invitation = BusinessInvitation::withoutGlobalScope(BusinessScope::class)
            ->where('token', $validated['token'])
            ->where('invited_user_id', $request->user()->id)
            ->first();

        if (! $invitation || ! $invitation->isAcceptable()) {
            return back()->with('error', __('flash.invitations.invalid_link'));
        }

        $invitation->update([
            'status' => BusinessInvitation::STATUS_DECLINED,
            'responded_at' => now(),
        ]);

        return back()->with('success', __('flash.invitations.declined'));
    }

    /**
     * POST /invitations/acknowledge — clears the post-join status notice.
     */
    public function acknowledge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invitation_id' => ['required', 'string'],
        ]);

        $invitation = BusinessInvitation::withoutGlobalScope(BusinessScope::class)
            ->where('id', $validated['invitation_id'])
            ->where('invited_user_id', $request->user()->id)
            ->first();

        if ($invitation) {
            $invitation->update(['acknowledged_at' => now()]);
        }

        return back();
    }

    private function acceptInvitation(BusinessInvitation $invitation): void
    {
        $business = $invitation->business;
        $invitedUser = $invitation->invitedUser;

        // Check if an active membership already exists (don't downgrade it).
        $existingMembership = Membership::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('user_id', $invitedUser->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $existingMembership) {
            // Restore a soft-deleted membership or create fresh.
            $membership = Membership::withoutGlobalScope(BusinessScope::class)
                ->where('business_id', $business->id)
                ->where('user_id', $invitedUser->id)
                ->withTrashed()
                ->first();

            if ($membership) {
                $membership->restore();
                $membership->update([
                    'role' => $invitation->role,
                    'joined_at' => now(),
                ]);
            } else {
                Membership::withoutGlobalScope(BusinessScope::class)->create([
                    'business_id' => $business->id,
                    'user_id' => $invitedUser->id,
                    'role' => $invitation->role,
                    'business_badge_color' => '#6366F1',
                    'joined_at' => now(),
                ]);
            }
        }

        // Mark accepted and rotate token (single-use).
        $invitation->update([
            'status' => BusinessInvitation::STATUS_ACCEPTED,
            'responded_at' => now(),
            'token' => Str::random(64),
        ]);
    }
}
