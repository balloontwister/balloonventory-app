<?php

namespace App\Support;

use App\Models\BusinessInvitation;
use App\Models\User;
use App\Scopes\BusinessScope;

class PendingInvitations
{
    /**
     * Pending, non-expired invitations addressed to the given user, shaped for
     * the dashboard / no-business welcome notices. Surfaced in more than one
     * place, so the query lives here rather than in a single controller.
     *
     * @return array<int, array{token: string, business_name: string, inviter_name: string, role_label: string}>
     */
    public static function for(User $user): array
    {
        return BusinessInvitation::withoutGlobalScope(BusinessScope::class)
            // Load the inviter even if soft-deleted: an ownership-transfer invite
            // is sent by an owner who is deleting their account, so by the time the
            // successor sees it the inviter row is trashed.
            ->with(['business', 'inviter' => fn ($q) => $q->withTrashed()])
            ->where('invited_user_id', $user->id)
            ->where('status', BusinessInvitation::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get()
            ->map(fn (BusinessInvitation $inv) => [
                'token' => $inv->token,
                'business_name' => $inv->business->name,
                'inviter_name' => $inv->inviter?->name ?? __('dashboard.invitations.former_owner'),
                'role_label' => self::roleLabel($inv->role),
            ])
            ->values()
            ->all();
    }

    private static function roleLabel(string $role): string
    {
        return match ($role) {
            'owner' => 'Owner',
            'manager' => 'Manager',
            'staff' => 'Artist',
            'guest' => 'Guest Artist',
            default => $role,
        };
    }
}
