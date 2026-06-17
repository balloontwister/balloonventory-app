<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Sku;
use App\Models\User;
use App\Policies\Concerns\ChecksMembership;

class SkuPolicy
{
    use ChecksMembership;

    public function createPrivate(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'sku.create_private');
    }

    public function editPrivate(User $user, Sku $sku): bool
    {
        if ($sku->owned_by_business_id === null) {
            return false;
        }
        $business = $sku->owningBusiness;

        return $business && $this->userCan($user, $business, 'sku.edit_private');
    }

    public function deletePrivate(User $user, Sku $sku): bool
    {
        if ($sku->owned_by_business_id === null) {
            return false;
        }
        $business = $sku->owningBusiness;

        return $business && $this->userCan($user, $business, 'sku.delete_private');
    }

    public function editOverride(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'sku.edit_override');
    }

    public function editShared(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function reportError(User $user, Sku $sku, ?Business $business = null): bool
    {
        if ($sku->owned_by_business_id === null) {
            // Shared SKU — any authenticated user may report.
            return true;
        }
        // Private SKU — reporter must be a member of the owning business.
        $owning = $business ?? $sku->owningBusiness;

        return $owning && $this->membershipRole($user, $owning) !== null;
    }
}
