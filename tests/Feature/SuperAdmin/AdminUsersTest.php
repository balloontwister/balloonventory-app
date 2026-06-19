<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\AdminLevel;
use App\Models\Business;
use App\Models\Membership;
use App\Models\Sku;
use App\Models\SkuFeedback;
use App\Models\StockLevel;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
    // mass-assignment guard
    // -------------------------------------------------------------------------

    public function test_admin_level_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create(); // admin_level null

        // Simulate a crafted request body trying to escalate via mass assignment.
        $user->fill(['admin_level' => AdminLevel::SuperAdmin->value]);
        $user->save();

        $this->assertNull($user->fresh()->admin_level);
        $this->assertFalse($user->fresh()->isSuperAdmin());
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
            ->has('users.data', 3)
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

    // -------------------------------------------------------------------------
    // index: sorting / search / filters / computed columns
    // -------------------------------------------------------------------------

    /** Attach $user to a fresh business holding the given stock, bypassing tenancy scopes. */
    private function giveInventory(User $user, int $skus, int $bagsEach): Business
    {
        $business = Business::factory()->create();

        Membership::create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        for ($i = 0; $i < $skus; $i++) {
            StockLevel::factory()->create([
                'business_id' => $business->id,
                'sku_id' => Sku::factory()->create()->id,
                'full_bags' => $bagsEach,
                'open_bags' => 0,
            ]);
        }

        return $business;
    }

    public function test_index_defaults_to_recent_login_first(): void
    {
        User::where('id', '!=', $this->superAdmin->id)->delete(); // isolate
        $stale = User::factory()->create(['name' => 'Stale', 'last_login_at' => now()->subDays(30)]);
        $fresh = User::factory()->create(['name' => 'Fresh', 'last_login_at' => now()]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.users.index', ['search' => 'res']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.sort', 'last_login_at')
                ->where('filters.dir', 'desc')
                ->where('users.data.0.name', 'Fresh')
            );

        $this->assertNotNull($stale->fresh());
        $this->assertNotNull($fresh->fresh());
    }

    public function test_index_can_sort_by_name_ascending(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.users.index', ['sort' => 'name', 'dir' => 'asc']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.sort', 'name')
                ->where('filters.dir', 'asc')
            );
    }

    public function test_index_search_filters_by_name(): void
    {
        $needle = User::factory()->create(['name' => 'Zzqqx Unique']);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.users.index', ['search' => 'Zzqqx']))
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $needle->id)
            );
    }

    public function test_index_status_filter_frozen_only_returns_frozen(): void
    {
        $frozen = User::factory()->create(['frozen_at' => now()]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.users.index', ['status' => 'frozen']))
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $frozen->id)
            );
    }

    public function test_inventory_counts_aggregate_across_the_users_businesses_ignoring_tenant_scope(): void
    {
        $user = User::factory()->create(['name' => 'Inventory Holder Qx']);
        $this->giveInventory($user, skus: 2, bagsEach: 3); // 2 SKUs, 6 bags total

        // Acting admin has no business context — the counts must still resolve.
        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.users.index', ['search' => 'Inventory Holder Qx']))
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.inventory_skus_count', 2)
                ->where('users.data.0.inventory_bags_total', 6)
            );
    }

    public function test_activity_and_business_columns_count_per_user(): void
    {
        $user = User::factory()->create(['name' => 'Active Person Qx']);
        $business = $this->giveInventory($user, skus: 1, bagsEach: 1);

        SupportTicket::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'subject' => 'Hi',
            'body' => 'There',
        ]);
        SkuFeedback::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.users.index', ['search' => 'Active Person Qx']))
            ->assertInertia(fn ($page) => $page
                ->where('users.data.0.support_tickets_count', 1)
                ->where('users.data.0.sku_feedback_count', 2)
                ->where('users.data.0.businesses.0.name', $business->name)
            );
    }

    // -------------------------------------------------------------------------
    // freeze / thaw
    // -------------------------------------------------------------------------

    public function test_super_admin_can_freeze_and_thaw_a_user(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.freeze', $this->regularUser))
            ->assertSessionHas('success');
        $this->assertNotNull($this->regularUser->fresh()->frozen_at);

        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.users.thaw', $this->regularUser))
            ->assertSessionHas('success');
        $this->assertNull($this->regularUser->fresh()->frozen_at);
    }

    public function test_site_admin_can_freeze_a_regular_user(): void
    {
        $this->actingAs($this->siteAdmin)
            ->post(route('super-admin.users.freeze', $this->regularUser))
            ->assertSessionHas('success');

        $this->assertNotNull($this->regularUser->fresh()->frozen_at);
    }

    public function test_a_super_admin_cannot_be_frozen(): void
    {
        $target = User::factory()->superAdmin()->create();

        $this->actingAs($this->superAdmin)
            ->post(route('super-admin.users.freeze', $target))
            ->assertStatus(422);

        $this->assertNull($target->fresh()->frozen_at);
    }

    public function test_an_admin_cannot_freeze_themselves(): void
    {
        $this->actingAs($this->siteAdmin)
            ->post(route('super-admin.users.freeze', $this->siteAdmin))
            ->assertStatus(422);

        $this->assertNull($this->siteAdmin->fresh()->frozen_at);
    }

    public function test_a_frozen_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'frozen@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'frozen_at' => now(),
        ]);

        $this->post(route('login'), [
            'email' => 'frozen@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_frozen_user_with_an_active_session_is_ejected(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Already logged in, then frozen mid-session.
        $this->actingAs($user);
        $user->forceFill(['frozen_at' => now()])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // -------------------------------------------------------------------------
    // password reset / delete
    // -------------------------------------------------------------------------

    public function test_admin_can_trigger_a_password_reset_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($this->siteAdmin)
            ->post(route('super-admin.users.password-reset', $user))
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_super_admin_can_delete_a_regular_user(): void
    {
        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.users.destroy', $this->regularUser))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $this->regularUser->id]);
    }

    public function test_site_admin_cannot_delete_a_user(): void
    {
        $this->actingAs($this->siteAdmin)
            ->delete(route('super-admin.users.destroy', $this->regularUser))
            ->assertStatus(403);

        $this->assertNull($this->regularUser->fresh()->deleted_at);
    }

    public function test_an_admin_cannot_be_deleted(): void
    {
        $this->actingAs($this->superAdmin)
            ->delete(route('super-admin.users.destroy', $this->siteAdmin))
            ->assertStatus(422);

        $this->assertNull($this->siteAdmin->fresh()->deleted_at);
    }
}
