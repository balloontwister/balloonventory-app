<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\AdminLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_factory_state_sets_correct_admin_level(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertSame(AdminLevel::SuperAdmin, $user->admin_level);
    }

    public function test_site_admin_factory_state_sets_correct_admin_level(): void
    {
        $user = User::factory()->siteAdmin()->create();

        $this->assertSame(AdminLevel::SiteAdmin, $user->admin_level);
    }

    public function test_default_user_has_null_admin_level(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->admin_level);
    }

    public function test_is_super_admin_returns_true_for_super_admin(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_for_site_admin(): void
    {
        $user = User::factory()->siteAdmin()->create();

        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_for_regular_user(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_is_site_admin_returns_true_for_site_admin(): void
    {
        $user = User::factory()->siteAdmin()->create();

        $this->assertTrue($user->isSiteAdmin());
    }

    public function test_is_site_admin_returns_false_for_super_admin(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertFalse($user->isSiteAdmin());
    }

    public function test_is_site_admin_returns_false_for_regular_user(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isSiteAdmin());
    }

    public function test_is_any_admin_returns_true_for_super_admin(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue($user->isAnyAdmin());
    }

    public function test_is_any_admin_returns_true_for_site_admin(): void
    {
        $user = User::factory()->siteAdmin()->create();

        $this->assertTrue($user->isAnyAdmin());
    }

    public function test_is_any_admin_returns_false_for_regular_user(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isAnyAdmin());
    }

    public function test_deleting_super_admin_throws_exception(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Super admin accounts cannot be deleted.');

        $user->delete();
    }

    public function test_deleting_site_admin_is_permitted(): void
    {
        $user = User::factory()->siteAdmin()->create();

        $user->delete();

        $this->assertSoftDeleted($user);
    }

    public function test_deleting_regular_user_is_permitted(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertSoftDeleted($user);
    }

    public function test_admin_level_is_cast_to_enum(): void
    {
        $user = User::factory()->superAdmin()->create();

        $fresh = User::find($user->id);

        $this->assertInstanceOf(AdminLevel::class, $fresh->admin_level);
        $this->assertSame(AdminLevel::SuperAdmin, $fresh->admin_level);
    }

    public function test_null_admin_level_is_preserved_after_save(): void
    {
        $user = User::factory()->create();

        $user->save();

        $this->assertNull(User::find($user->id)->admin_level);
    }
}
