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
        return $user->is_super_admin || $this->userCan($user, $business, 'upc.resolve_pending');
    }

    public function resolve(User $user, PendingUpcScan $scan): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return $this->userCan($user, $scan->business, 'upc.resolve_pending');
    }
}
