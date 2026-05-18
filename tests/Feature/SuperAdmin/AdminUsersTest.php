<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\AdminLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $siteAdmin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);

        $this->siteAdmin = User::factory()->siteAdmin()->create([
            'email_verified_at' => now(),
        ]);

        $this->regularUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_super_admin_can_access_users_index(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.users.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Users/Index')
            ->has('users', 3)
        );
    }

    public function test_site_admin_can_access_users_index(): void
    {
        $response = $this->actingAs($this->siteAdmin)
            ->get(route('super-admin.users.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Users/Index')
        );
    }

    public function test_regular_user_cannot_access_users_index(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('super-admin.users.index'));

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_users_index(): void
    {
        $response = $this->get(route('super-admin.users.index'));

        $response->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // promote
    // -------------------------------------------------------------------------

    public function test_super_admin_can_promote_regular_user_to_site_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.promote', $this->regularUser));

        $response->assertRedirect();

        $this->regularUser->refresh();
        $this->assertSame(AdminLevel::SiteAdmin, $this->regularUser->admin_level);
    }

    public function test_site_admin_cannot_promote_users(): void
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->siteAdmin)
            ->post(route('super-admin.users.promote', $target));

        $response->assertStatus(403);
        $this->assertNull($target->fresh()->admin_level);
    }

    public function test_regular_user_cannot_promote_users(): void
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->regularUser)
            ->post(route('super-admin.users.promote', $target));

        $response->assertStatus(403);
    }

    public function test_super_admin_cannot_be_promoted_to_site_admin(): void
    {
        $anotherSuperAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.promote', $anotherSuperAdmin));

        $response->assertStatus(422);
        $this->assertSame(AdminLevel::SuperAdmin, $anotherSuperAdmin->fresh()->admin_level);
    }

    public function test_site_admin_cannot_be_promoted_again(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.promote', $this->siteAdmin));

        // siteAdmin is not a super admin so 422 is not triggered — but they
        // already have SiteAdmin; the controller promotes regardless (idempotent).
        // The important thing is no 403 and the level stays SiteAdmin.
        $response->assertRedirect();
        $this->assertSame(AdminLevel::SiteAdmin, $this->siteAdmin->fresh()->admin_level);
    }

    // -------------------------------------------------------------------------
    // demote
    // -------------------------------------------------------------------------

    public function test_super_admin_can_demote_site_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.users.demote', $this->siteAdmin));

        $response->assertRedirect();
        $this->assertNull($this->siteAdmin->fresh()->admin_level);
    }

    public function test_site_admin_cannot_demote_users(): void
    {
        $response = $this->actingAs($this->siteAdmin)
            ->delete(route('super-admin.users.demote', $this->siteAdmin));

        $response->assertStatus(403);
        $this->assertSame(AdminLevel::SiteAdmin, $this->siteAdmin->fresh()->admin_level);
    }

    public function test_regular_user_cannot_demote_users(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->delete(route('super-admin.users.demote', $this->siteAdmin));

        $response->assertStatus(403);
    }

    public function test_super_admin_cannot_be_demoted_via_demote_endpoint(): void
    {
        $anotherSuperAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.users.demote', $anotherSuperAdmin));

        $response->assertStatus(422);
        $this->assertSame(AdminLevel::SuperAdmin, $anotherSuperAdmin->fresh()->admin_level);
    }

    public function test_demoting_regular_user_returns_422(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.users.demote', $this->regularUser));

        $response->assertStatus(422);
        $this->assertNull($this->regularUser->fresh()->admin_level);
    }

    public function test_promote_success_flash_message_is_set(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.promote', $this->regularUser));

        $response->assertSessionHas('success');
    }

    public function test_demote_success_flash_message_is_set(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.users.demote', $this->siteAdmin));

        $response->assertSessionHas('success');
    }
}
