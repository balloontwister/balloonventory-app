<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use App\Policies\Concerns\ChecksMembership;

class UpcPolicy
{
    use ChecksMembership;

    public function manage(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'upc.manage');
    }

    public function resolvePending(User $user, Business $business): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return $this->userCan($user, $business, 'upc.resolve_pending');
    }

    public function scan(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'upc.scan');
    }
}
