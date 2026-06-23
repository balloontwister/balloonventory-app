<?php

namespace App\Http\Controllers;

use App\Exceptions\LastOwnerGuardException;
use App\Mail\TemplatedMailable;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\Membership;
use App\Models\User;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MembershipController extends Controller
{
    /** POST /memberships/invite */
    public function invite(Request $request): RedirectResponse
    {
        $business = Business::findOrFail(BusinessContext::currentId());

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'string', 'in:owner,staff,guest'],
        ]);

        Gate::authorize('membership.invite', [$business, $validated['role']]);

        $invitee = User::where('email', $validated['email'])->first();

        if (! $invitee) {
            return back()->with('error', __('flash.memberships.unknown_email'));
        }

        if ($invitee->id === $request->user()->id) {
            return back()->with('error', __('flash.memberships.self_invite'));
        }

        $activeMembership = Membership::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('user_id', $invitee->id)
            ->whereNull('deleted_at')
            ->first();

        if ($activeMembership) {
            return back()->with('error', __('flash.memberships.already_member'));
        }

        // Reuse or create an invitation row.
        $invitation = BusinessInvitation::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('invited_user_id', $invitee->id)
            ->first();

        if ($invitation) {
            $invitation->restore();
            $invitation->update([
                'role' => $validated['role'],
                'invited_email' => $invitee->email,
                'invited_by_user_id' => $request->user()->id,
                'token' => Str::random(64),
                'status' => BusinessInvitation::STATUS_PENDING,
                'expires_at' => now()->addDays(14),
                'responded_at' => null,
                'acknowledged_at' => null,
            ]);
        } else {
            $invitation = BusinessInvitation::withoutGlobalScope(BusinessScope::class)->create([
                'business_id' => $business->id,
                'invited_email' => $invitee->email,
                'invited_user_id' => $invitee->id,
                'role' => $validated['role'],
                'token' => Str::random(64),
                'invited_by_user_id' => $request->user()->id,
                'status' => BusinessInvitation::STATUS_PENDING,
                'expires_at' => now()->addDays(14),
            ]);
        }

        $roleLabel = $this->roleLabel($invitation->role);
        $mail = TemplatedMailable::forKey('business_invitation', [
            'user_name' => $invitee->name,
            'inviter_name' => $request->user()->name,
            'business_name' => $business->name,
            'role_label' => $roleLabel,
            'accept_url' => route('invitations.accept', ['token' => $invitation->token]),
        ]);

        if (! $mail) {
            return back()->with('warning', __('flash.memberships.invite_sent_no_email', ['name' => $invitee->name]));
        }

        Mail::to($invitee->email)->send($mail);

        return back()->with('success', __('flash.memberships.invited', ['name' => $invitee->name]));
    }

    /** PATCH /memberships/{membership}/role */
    public function updateRole(Request $request, Membership $membership): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:owner,staff,guest,none'],
        ]);

        try {
            Gate::authorize('membership.change_role', [$membership, $validated['role']]);
        } catch (LastOwnerGuardException $e) {
            return back()->with('error', $e->getMessage());
        }

        $membership->update(['role' => $validated['role']]);

        return back()->with('success', __('flash.memberships.role_updated', ['name' => $membership->user->name]));
    }

    /** DELETE /memberships/{membership}/leave — the authenticated user removes themselves */
    public function leave(Request $request, Membership $membership): RedirectResponse
    {
        abort_unless($membership->user_id === $request->user()->id, 403);

        if ($membership->role === 'owner') {
            $ownerCount = Membership::withoutGlobalScope(BusinessScope::class)
                ->where('business_id', $membership->business_id)
                ->where('role', 'owner')
                ->whereNull('deleted_at')
                ->count();

            if ($ownerCount <= 1) {
                return back()->with('error', __('flash.memberships.last_owner_leave'));
            }
        }

        $businessName = $membership->business->name;
        $membership->delete();

        return redirect()->route('account.index')
            ->with('success', __('flash.memberships.left', ['business' => $businessName]));
    }

    /** DELETE /memberships/{membership} */
    public function destroy(Request $request, Membership $membership): RedirectResponse
    {
        try {
            Gate::authorize('membership.remove', $membership);
        } catch (LastOwnerGuardException $e) {
            return back()->with('error', $e->getMessage());
        }

        $membership->delete();

        return back()->with('success', __('flash.memberships.removed', ['name' => $membership->user->name]));
    }

    /** DELETE /memberships/invitations/{invitation}/revoke */
    public function revokeInvite(Request $request, BusinessInvitation $invitation): RedirectResponse
    {
        $business = Business::findOrFail(BusinessContext::currentId());

        abort_unless($invitation->business_id === $business->id, 403);
        Gate::authorize('membership.invite', [$business, 'staff']);

        $invitation->update([
            'status' => BusinessInvitation::STATUS_REVOKED,
            'responded_at' => now(),
        ]);

        return back()->with('success', __('flash.memberships.invite_revoked'));
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'owner' => 'Owner',
            'manager' => 'Manager',
            'staff' => 'Artist',
            'guest' => 'Guest',
            'none' => 'No Access',
            default => $role,
        };
    }
}
