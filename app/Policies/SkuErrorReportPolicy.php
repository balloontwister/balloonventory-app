<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Sku;
use App\Models\SkuErrorReport;
use App\Models\User;
use App\Policies\Concerns\ChecksMembership;

class SkuErrorReportPolicy
{
    use ChecksMembership;

    public function create(User $user, Sku $sku, ?Business $business = null): bool
    {
        // Any user may report any SKU they can see; enforce visibility at the query layer.
        if ($sku->owned_by_business_id === null) {
            return true;
        }

        $owning = $business ?? $sku->owningBusiness;

        return $owning && $this->membershipRole($user, $owning) !== null;
    }

    public function manage(User $user, SkuErrorReport $report): bool
    {
        $sku = $report->sku;

        if ($sku->owned_by_business_id === null) {
            return $user->isSuperAdmin();
        }

        $owning = $sku->owningBusiness;

        if (! $owning) {
            return false;
        }

        $role = $this->membershipRole($user, $owning);

        return in_array($role, ['owner', 'manager'], true);
    }
}
