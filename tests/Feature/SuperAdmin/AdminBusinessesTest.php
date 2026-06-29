<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Business;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Sku;
use App\Models\StockLevel;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\BusinessFrozen;
use App\Notifications\BusinessThawed;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminBusinessesTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $siteAdmin;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);

        $this->siteAdmin = User::factory()->siteAdmin()->create([
            'email_verified_at' => now(),
        ]);

        $this->business = Business::factory()->create();
    }

    /**
     * Convenience: attach a user to a business with a role (always sets the
     * required joined_at column).
     */
    private function addMember(Business $business, User $user, string $role = 'owner'): Membership
    {
        return Membership::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // index
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_access_businesses_index(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Businesses/Index')
            ->has('businessList.data', 1)
        );
    }

    public function test_site_admin_can_access_businesses_index(): void
    {
        $response = $this->actingAs($this->siteAdmin)
            ->get(route('admin.businesses.index'));

        $response->assertStatus(200);
    }

    public function test_regular_user_cannot_access_businesses_index(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get(route('admin.businesses.index'));

        $response->assertStatus(403);
    }

    public function test_businesses_index_search_by_name(): void
    {
        Business::factory()->create(['name' => 'Acme Balloons']);
        Business::factory()->create(['name' => 'Balloons Plus']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.index', ['search' => 'Acme']));

        $response->assertInertia(fn ($page) => $page
            ->has('businessList.data', 1)
            ->where('businessList.data.0.name', 'Acme Balloons')
        );
    }

    public function test_businesses_index_search_by_slug(): void
    {
        Business::factory()->create(['slug' => 'test-business']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.index', ['search' => 'test-business']));

        $response->assertInertia(fn ($page) => $page
            ->has('businessList.data', 1)
        );
    }

    public function test_businesses_index_search_by_email(): void
    {
        Business::factory()->create(['contact_email' => 'hello@balloons.test']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.index', ['search' => 'hello@balloons.test']));

        $response->assertInertia(fn ($page) => $page
            ->has('businessList.data', 1)
        );
    }

    public function test_businesses_index_filter_by_status_active(): void
    {
        $this->business->forceDelete(); // isolate from the setUp business
        $activeBusiness = Business::factory()->create();
        Business::factory()->create(['frozen_at' => now()]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.index', ['status' => 'active']));

        $response->assertInertia(fn ($page) => $page
            ->has('businessList.data', 1)
            ->where('businessList.data.0.id', $activeBusiness->id)
        );
    }

    public function test_businesses_index_filter_by_status_frozen(): void
    {
        Business::factory()->create();
        $frozenBusiness = Business::factory()->create(['frozen_at' => now()]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.index', ['status' => 'frozen']));

        $response->assertInertia(fn ($page) => $page
            ->has('businessList.data', 1)
            ->where('businessList.data.0.id', $frozenBusiness->id)
        );
    }

    public function test_businesses_index_filter_by_status_deleted(): void
    {
        Business::factory()->create();
        $deletedBusiness = Business::factory()->create();
        $deletedBusiness->delete();

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.index', ['status' => 'deleted']));

        $response->assertInertia(fn ($page) => $page
            ->has('businessList.data', 1)
            ->where('businessList.data.0.id', $deletedBusiness->id)
        );
    }

    public function test_businesses_index_sort_by_name_asc(): void
    {
        $this->business->forceDelete(); // isolate from the setUp business
        Business::factory()->create(['name' => 'Zebra Balloons']);
        Business::factory()->create(['name' => 'Acme Balloons']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.index', ['sort' => 'name', 'dir' => 'asc']));

        $response->assertInertia(fn ($page) => $page
            ->where('businessList.data.0.name', 'Acme Balloons')
            ->where('businessList.data.1.name', 'Zebra Balloons')
        );
    }

    public function test_businesses_index_sort_by_created_at_desc(): void
    {
        Business::factory()->create();
        sleep(1);
        $business2 = Business::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.index', ['sort' => 'created_at', 'dir' => 'desc']));

        $response->assertInertia(fn ($page) => $page
            ->where('businessList.data.0.id', $business2->id)
        );
    }

    public function test_businesses_index_members_count_calculated(): void
    {
        $this->business->forceDelete(); // isolate from the setUp business
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $this->addMember($business, $owner);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('businessList.data.0.members_count', 1)
        );
    }

    public function test_businesses_index_exposes_owner_id_for_owner_actions(): void
    {
        $this->business->forceDelete(); // isolate from the setUp business
        $owner = User::factory()->create();
        $business = Business::factory()->create();
        $this->addMember($business, $owner, 'owner');

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('businessList.data.0.owner_id', $owner->id)
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // show
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_view_business_detail(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.show', $this->business->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Businesses/Show')
            ->where('record.id', $this->business->id)
        );
    }

    public function test_business_detail_shows_members(): void
    {
        $owner = User::factory()->create();
        $this->addMember($this->business, $owner, 'owner');

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.show', $this->business->id));

        $response->assertInertia(fn ($page) => $page
            ->has('members', 1)
            ->where('members.0.name', $owner->name)
            ->where('record.owner_id', $owner->id)
        );
    }

    public function test_business_detail_shows_inventory_stats(): void
    {
        Location::factory()->create(['business_id' => $this->business->id]);
        $sku = Sku::factory()->create();
        StockLevel::factory()->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'full_bags' => 5,
            'open_bags' => 2,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.show', $this->business->id));

        $response->assertInertia(fn ($page) => $page
            ->where('inventory_skus_count', 1)
            ->where('inventory_bags_total', 7)
            ->where('locations_count', 1)
        );
    }

    /**
     * Regression: detail stats must reflect the INSPECTED business, not the
     * admin's own current business. StockLevel/Location/Bin/Membership all carry
     * the tenant scope, so the controller must bypass it and filter explicitly.
     */
    public function test_business_detail_stats_are_not_scoped_to_admins_own_business(): void
    {
        // The admin belongs to (and is therefore contexted into) their own
        // business with its own, different inventory.
        $adminBusiness = Business::factory()->create();
        $this->addMember($adminBusiness, $this->superAdmin, 'owner');
        Location::factory()->create(['business_id' => $adminBusiness->id]);
        $adminSku = Sku::factory()->create();
        StockLevel::factory()->create([
            'business_id' => $adminBusiness->id,
            'sku_id' => $adminSku->id,
            'full_bags' => 99,
            'open_bags' => 0,
        ]);

        // The inspected target business has its own, distinct inventory.
        $target = Business::factory()->create();
        Location::factory()->create(['business_id' => $target->id]);
        $targetSku = Sku::factory()->create();
        StockLevel::factory()->create([
            'business_id' => $target->id,
            'sku_id' => $targetSku->id,
            'full_bags' => 5,
            'open_bags' => 2,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.show', $target->id));

        $response->assertInertia(fn ($page) => $page
            ->where('inventory_skus_count', 1)
            ->where('inventory_bags_total', 7)   // target's 7, not admin's 99
            ->where('locations_count', 1)
        );
    }

    public function test_business_detail_shows_support_tickets(): void
    {
        $owner = User::factory()->create();
        $this->addMember($this->business, $owner, 'owner');
        SupportTicket::create([
            'user_id' => $owner->id,
            'user_name' => $owner->name,
            'user_email' => $owner->email,
            'subject' => 'Need help',
            'body' => 'Something is broken.',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.show', $this->business->id));

        $response->assertInertia(fn ($page) => $page
            ->has('tickets', 1)
        );
    }

    public function test_deleted_business_can_still_be_viewed(): void
    {
        $business = Business::factory()->create();
        $business->delete();

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.show', $business->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('record.deleted_at', $business->deleted_at->toJson())
        );
    }

    public function test_business_detail_shows_creator(): void
    {
        $creator = User::factory()->create();
        $business = Business::factory()->create(['created_by_user_id' => $creator->id]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.show', $business->id));

        $response->assertInertia(fn ($page) => $page
            ->where('record.created_by.id', $creator->id)
            ->where('record.created_by.name', $creator->name)
        );
    }

    public function test_business_detail_creator_is_null_when_unknown(): void
    {
        $business = Business::factory()->create(['created_by_user_id' => null]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.show', $business->id));

        $response->assertInertia(fn ($page) => $page
            ->where('record.created_by', null)
        );
    }

    public function test_businesses_index_can_sort_by_plan(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.businesses.index', ['sort' => 'plan', 'dir' => 'asc']));

        $response->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // suspend
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_suspend_business(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.businesses.suspend', $this->business->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->business->refresh();
        $this->assertNotNull($this->business->frozen_at);
        $this->assertTrue($this->business->isFrozen());
    }

    public function test_suspend_notifies_owners(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $this->addMember($this->business, $owner, 'owner');

        $this->actingAs($this->superAdmin)
            ->post(route('admin.businesses.suspend', $this->business->id));

        Notification::assertSentTo($owner, BusinessFrozen::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // thaw (unsuspend)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_unsuspend_business(): void
    {
        Notification::fake();

        $this->business->update(['frozen_at' => now()]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('admin.businesses.thaw', $this->business->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->business->refresh();
        $this->assertNull($this->business->frozen_at);
        $this->assertFalse($this->business->isFrozen());
    }

    public function test_unsuspend_notifies_owners(): void
    {
        Notification::fake();

        $this->business->update(['frozen_at' => now()]);
        $owner = User::factory()->create();
        $this->addMember($this->business, $owner, 'owner');

        $this->actingAs($this->superAdmin)
            ->delete(route('admin.businesses.thaw', $this->business->id));

        Notification::assertSentTo($owner, BusinessThawed::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_delete_business(): void
    {
        $business = Business::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('admin.businesses.destroy', $business->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $business->refresh();
        $this->assertNotNull($business->deleted_at);
    }

    public function test_site_admin_cannot_delete_business(): void
    {
        $response = $this->actingAs($this->siteAdmin)
            ->delete(route('admin.businesses.destroy', $this->business->id));

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_delete_business(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->delete(route('admin.businesses.destroy', $this->business->id));

        $response->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // authorization
    // ─────────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_businesses(): void
    {
        $response = $this->get(route('admin.businesses.index'));

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_business_owner_cannot_access_admin_businesses(): void
    {
        $owner = User::factory()->create();
        $this->addMember($this->business, $owner, 'owner');

        $response = $this->actingAs($owner)
            ->get(route('admin.businesses.index'));

        $response->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // suspension enforcement ("suspended business = No Access")
    // ─────────────────────────────────────────────────────────────────────────

    public function test_member_cannot_switch_into_a_suspended_business(): void
    {
        $owner = User::factory()->create();
        $frozen = Business::factory()->create(['frozen_at' => now()]);
        $this->addMember($frozen, $owner, 'owner');

        $response = $this->actingAs($owner)
            ->post(route('business.switch', ['business' => $frozen->id]));

        $response->assertStatus(403);
    }

    public function test_suspended_business_blocks_transactional_routes(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $frozen = Business::factory()->create(['frozen_at' => now()]);
        $this->addMember($frozen, $owner, 'owner');

        $response = $this->actingAs($owner)
            ->get(route('inventory.index'));

        $response->assertRedirect(route('account.index'));
    }

    public function test_suspended_business_member_can_still_reach_account(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $frozen = Business::factory()->create(['frozen_at' => now()]);
        $this->addMember($frozen, $owner, 'owner');

        $response = $this->actingAs($owner)
            ->get(route('account.index'));

        $response->assertStatus(200);
    }

    public function test_member_with_an_active_business_is_not_blocked_by_a_suspended_one(): void
    {
        $this->seed(PermissionSeeder::class); // inventory.index uses Gate::authorize

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $active = Business::factory()->create();
        $frozen = Business::factory()->create(['frozen_at' => now()]);
        $this->addMember($active, $owner, 'owner');
        $this->addMember($frozen, $owner, 'owner');

        // SetBusinessContext should prefer the active business, so transactional
        // routes remain reachable.
        $response = $this->actingAs($owner)
            ->get(route('inventory.index'));

        $response->assertStatus(200);
    }
}
