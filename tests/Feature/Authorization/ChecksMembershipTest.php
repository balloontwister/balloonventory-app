<?php

namespace Tests\Feature\Authorization;

use App\Policies\Concerns\ChecksMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecksMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_has_fails_closed_when_role_is_not_a_seeded_spatie_role(): void
    {
        // A role string with no matching Spatie Role row (e.g. a deploy that
        // migrated but never ran PermissionSeeder). roleHas() must return false,
        // not throw RoleDoesNotExist (which Role::findByName would have done).
        $checker = new class
        {
            use ChecksMembership;

            public function check(string $role, string $permission): bool
            {
                return $this->roleHas($role, $permission);
            }
        };

        $this->assertFalse($checker->check('owner', 'inventory.check_in'));
    }
}
