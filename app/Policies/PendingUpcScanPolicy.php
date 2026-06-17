<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\PendingUpcScan;
use App\Models\User;
use App\Policies\Concerns\ChecksMembership;

class PendingUpcScanPolicy
{
    use ChecksMembership;

    public function viewAny(User $user, Business $business): bool
    {
        return $user->isSuperAdmin() || $this->userCan($user, $business, 'upc.resolve_pending');
    }

    public function resolve(User $user, PendingUpcScan $scan): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->userCan($user, $scan->business, 'upc.resolve_pending');
    }
}
