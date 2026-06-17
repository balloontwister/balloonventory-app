<?php

namespace Tests\Feature\Policies;

use App\Models\User;
use App\Policies\SkuPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkuPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Editing a shared (platform-owned) SKU is a super-admin-only ability. This
     * guards against the `is_super_admin` column removal regression, where the
     * check silently returned null and locked super admins out.
     */
    public function test_only_super_admins_can_edit_shared_skus(): void
    {
        $policy = new SkuPolicy;

        $this->assertTrue($policy->editShared(User::factory()->superAdmin()->create()));
        $this->assertFalse($policy->editShared(User::factory()->siteAdmin()->create()));
        $this->assertFalse($policy->editShared(User::factory()->create()));
    }
}
