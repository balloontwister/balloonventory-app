<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use App\Policies\Concerns\ChecksMembership;

class InventoryPolicy
{
    use ChecksMembership;

    public function checkIn(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'inventory.check_in');
    }

    public function checkOut(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'inventory.check_out');
    }

    public function manualAdjust(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'inventory.manual_adjust');
    }

    public function overrideCount(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'inventory.override_count');
    }

    public function viewCounts(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'inventory.view_counts');
    }

    public function viewAuditLog(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'inventory.view_audit_log');
    }
}
