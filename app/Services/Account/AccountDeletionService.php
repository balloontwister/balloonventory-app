<?php

namespace App\Services\Account;

use App\Enums\BusinessFrozenReason;
use App\Mail\TemplatedMailable;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\Membership;
use App\Models\User;
use App\Scopes\BusinessScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Owns the side effects of deleting a user account: handing off or freezing any
 * business the user solely owns, detaching their memberships, then soft-deleting
 * the user.
 *
 * A self-serve deletion lets the departing owner *nominate* a successor, who is
 * invited to accept ownership (reusing the business-invitation flow). The
 * business is frozen while that invitation is pending; accepting it thaws the
 * business (see InvitationController), declining/expiring leaves it frozen. An
 * admin force-deletion has no owner to choose a successor, so it freezes
 * sole-owned businesses outright.
 */
class AccountDeletionService
{
    private const INVITATION_TTL_DAYS = 14;

    /**
     * Businesses where the user is the *only* active owner — deleting the user
     * would leave them with no owner. Co-owned businesses are excluded.
     *
     * @return Collection<int, Business>
     */
    public function soleOwnerBusinesses(User $user): Collection
    {
        return Membership::withoutGlobalScope(BusinessScope::class)
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->with('business')
            ->get()
            ->map(fn (Membership $membership): ?Business => $membership->business)
            ->filter(fn (?Business $business): bool => $business !== null && ! $business->trashed())
            ->filter(fn (Business $business): bool => $this->ownerCount($business) === 1)
            ->values();
    }

    /**
     * Other active members of the business (excluding the departing user) who
     * may be nominated to take over. Any role qualifies — including guests.
     *
     * @return Collection<int, Membership> memberships with `user` eager-loaded
     */
    public function handoffCandidates(Business $business, User $departing): Collection
    {
        return Membership::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('user_id', '!=', $departing->id)
            ->whereNull('deleted_at')
            ->with('user')
            ->orderBy('joined_at')
            ->get()
            ->filter(fn (Membership $membership): bool => $membership->user !== null)
            ->values();
    }

    /**
     * Self-serve account deletion. For each sole-owned business: nominate the
     * chosen successor (freeze + invite) or freeze outright. Then detach the
     * user's memberships and soft-delete the user. Call inside a transaction.
     *
     * @param  array<string, string|null>  $handoffs  business id => chosen successor user id (null/absent/invalid = freeze)
     */
    public function handleSelfDeletion(User $user, array $handoffs): void
    {
        foreach ($this->soleOwnerBusinesses($user) as $business) {
            $successor = $this->resolveSuccessor($business, $user, $handoffs[$business->id] ?? null);

            if ($successor !== null) {
                $this->nominateSuccessor($business, $successor, $user);
            } else {
                $business->freeze(BusinessFrozenReason::Ownerless);
            }
        }

        $this->purge($user);
    }

    /**
     * Admin force-deletion. The admin is not the owner, so there is no successor
     * to choose: every sole-owned business is frozen outright. Call inside a
     * transaction.
     */
    public function handleAdminDeletion(User $user): void
    {
        foreach ($this->soleOwnerBusinesses($user) as $business) {
            $business->freeze(BusinessFrozenReason::Ownerless);
        }

        $this->purge($user);
    }

    /**
     * Validate the chosen successor id against the live candidate set, so a
     * tampered id can never grant ownership to a non-member.
     */
    protected function resolveSuccessor(Business $business, User $departing, ?string $chosenUserId): ?Membership
    {
        if ($chosenUserId === null || $chosenUserId === '') {
            return null;
        }

        return $this->handoffCandidates($business, $departing)
            ->first(fn (Membership $membership): bool => $membership->user_id === $chosenUserId);
    }

    /**
     * Freeze the business (ownership-transfer reason) and invite the nominated
     * member to accept ownership. Accepting thaws the business and promotes them.
     */
    protected function nominateSuccessor(Business $business, Membership $successor, User $departing): void
    {
        $business->freeze(BusinessFrozenReason::OwnershipTransfer);

        $invitation = $this->createOwnershipInvitation($business, $successor, $departing);

        $this->sendOwnershipInvitationEmail($business, $successor->user, $departing, $invitation);
    }

    protected function createOwnershipInvitation(Business $business, Membership $successor, User $departing): BusinessInvitation
    {
        $existing = BusinessInvitation::withoutGlobalScope(BusinessScope::class)
            ->withTrashed()
            ->where('business_id', $business->id)
            ->where('invited_user_id', $successor->user_id)
            ->first();

        $attributes = [
            'business_id' => $business->id,
            'invited_email' => $successor->user->email,
            'invited_user_id' => $successor->user_id,
            'role' => 'owner',
            'token' => Str::random(64),
            'invited_by_user_id' => $departing->id,
            'status' => BusinessInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(self::INVITATION_TTL_DAYS),
            'responded_at' => null,
        ];

        if ($existing) {
            $existing->restore();
            $existing->update($attributes);

            return $existing;
        }

        return BusinessInvitation::withoutGlobalScope(BusinessScope::class)->create($attributes);
    }

    protected function sendOwnershipInvitationEmail(Business $business, User $successor, User $departing, BusinessInvitation $invitation): void
    {
        $mail = TemplatedMailable::forKey('business_invitation', [
            'user_name' => $successor->name,
            'inviter_name' => $departing->name,
            'business_name' => $business->name,
            'role_label' => 'Owner',
            'accept_url' => route('invitations.accept', ['token' => $invitation->token]),
        ]);

        if ($mail) {
            Mail::to($successor->email)->send($mail);
        }
    }

    /**
     * Detach the user's memberships, free their email, and soft-delete them.
     */
    protected function purge(User $user): void
    {
        $this->detachMemberships($user);
        $this->releaseEmail($user);

        $user->delete();
    }

    /**
     * Soft-delete every membership the user holds, across all businesses.
     */
    protected function detachMemberships(User $user): void
    {
        Membership::withoutGlobalScope(BusinessScope::class)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Tombstone the email so the address is immediately available for a fresh
     * registration (mirrors PruneUnverifiedUsers). The users.email unique index
     * is not soft-delete aware, so leaving the real email on the soft-deleted
     * row would cause a duplicate-key error when the address is reused.
     */
    protected function releaseEmail(User $user): void
    {
        if ($user->original_email === null) {
            $user->original_email = $user->email;
        }

        $user->email = $user->id.'@deleted.invalid';
        $user->save();
    }

    protected function ownerCount(Business $business): int
    {
        return Membership::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->count();
    }
}
