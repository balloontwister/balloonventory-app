<?php

namespace App\Policies;

use App\Models\BalloonList;
use App\Models\Business;
use App\Models\User;
use App\Policies\Concerns\ChecksMembership;

class BalloonListPolicy
{
    use ChecksMembership;

    public function viewAny(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'list.view');
    }

    public function view(User $user, BalloonList $list): bool
    {
        return $this->userCan($user, $list->business, 'list.view');
    }

    public function create(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'list.create');
    }

    public function update(User $user, BalloonList $list): bool
    {
        if ($list->is_business_favorites) {
            // Rename is blocked by the model observer; this covers the permission layer.
            return false;
        }

        return $this->userCan($user, $list->business, 'list.edit');
    }

    public function delete(User $user, BalloonList $list): bool
    {
        if ($list->is_business_favorites) {
            return false;
        }

        return $this->userCan($user, $list->business, 'list.delete');
    }

    public function editItem(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'list.edit');
    }

    public function editFavorites(User $user, Business $business): bool
    {
        return $this->userCan($user, $business, 'favorites.edit');
    }
}
