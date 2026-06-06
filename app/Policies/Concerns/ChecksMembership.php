<?php

namespace App\Policies\Concerns;

use App\Models\Business;
use App\Models\Membership;
use App\Models\User;
use App\Scopes\BusinessScope;
use Spatie\Permission\Models\Role;

trait ChecksMembership
{
    /**
     * Return the user's active role string in the given business, or null if not a member.
     */
    protected function membershipRole(User $user, Business $business): ?string
    {
        return Membership::withoutGlobalScope(BusinessScope::class)
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->whereNull('deleted_at')
            ->value('role');
    }

    /**
     * Check whether the named Spatie role grants the given permission.
     */
    protected function roleHas(string $roleName, string $permission): bool
    {
        // Resolve with first() rather than Role::findByName(), which throws
        // RoleDoesNotExist on a miss. An unknown role string (bad data) must
        // fail closed to a denial, not a 500.
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

        return $role !== null && $role->hasPermissionTo($permission);
    }

    /**
     * Resolve the user's role in the business, then check the Spatie role-permission matrix.
     */
    protected function userCan(User $user, Business $business, string $permission): bool
    {
        $role = $this->membershipRole($user, $business);

        return $role !== null && $this->roleHas($role, $permission);
    }

    /**
     * Count the active (non-deleted) owners of a business.
     */
    protected function ownerCount(Business $business): int
    {
        return Membership::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->count();
    }
}
