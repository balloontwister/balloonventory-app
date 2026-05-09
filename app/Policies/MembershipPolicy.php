<?php

namespace App\Policies;

use App\Exceptions\LastOwnerGuardException;
use App\Models\Business;
use App\Models\Membership;
use App\Models\User;
use App\Policies\Concerns\ChecksMembership;

class MembershipPolicy
{
    use ChecksMembership;

    public function invite(User $user, Business $business, string $targetRole): bool
    {
        return match ($targetRole) {
            'owner', 'manager' => $this->userCan($user, $business, 'membership.invite_owner'),
            'staff' => $this->userCan($user, $business, 'membership.invite_staff'),
            'guest' => $this->userCan($user, $business, 'membership.invite_guest'),
            default => false,
        };
    }

    /**
     * @throws LastOwnerGuardException
     */
    public function changeRole(User $user, Membership $membership, string $newRole): bool
    {
        $business = $membership->business;
        $currentRole = $membership->role;

        // Guard: cannot demote the last owner.
        if ($currentRole === 'owner' && $newRole !== 'owner') {
            if ($this->ownerCount($business) <= 1) {
                throw new LastOwnerGuardException;
            }
        }

        // Owner ↔ anything requires the actor to have the broad power.
        if ($currentRole === 'owner' || $newRole === 'owner') {
            return $this->userCan($user, $business, 'membership.change_role_any');
        }

        // Manager ↔ anything (other than owner) also requires the broad power.
        if ($currentRole === 'manager' || $newRole === 'manager') {
            return $this->userCan($user, $business, 'membership.change_role_any');
        }

        // staff ↔ guest only.
        return $this->userCan($user, $business, 'membership.change_role_staff_guest');
    }

    /**
     * @throws LastOwnerGuardException
     */
    public function remove(User $user, Membership $membership): bool
    {
        $business = $membership->business;
        $targetRole = $membership->role;

        if ($targetRole === 'owner') {
            if ($this->ownerCount($business) <= 1) {
                throw new LastOwnerGuardException;
            }

            return $this->userCan($user, $business, 'membership.remove_owner');
        }

        if ($targetRole === 'manager') {
            return $this->userCan($user, $business, 'membership.remove_manager');
        }

        return $this->userCan($user, $business, 'membership.remove_staff_guest');
    }
}
