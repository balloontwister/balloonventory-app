<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use App\Policies\Concerns\ChecksMembership;

class BusinessPolicy
{
    use ChecksMembership;

    public function editSettings(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'business.edit_settings');
    }

    public function delete(User $user, Business $business): bool
    {
        return $user->is_super_admin;
    }
}
