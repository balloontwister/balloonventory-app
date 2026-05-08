<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use App\Policies\Concerns\ChecksMembership;

class LocalPricePolicy
{
    use ChecksMembership;

    public function viewAny(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'local_price.view');
    }

    public function manage(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'local_price.edit');
    }
}
