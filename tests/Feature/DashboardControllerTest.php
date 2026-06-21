<?php

namespace Tests\Feature;

use App\Models\BalloonList;
use App\Models\Bin;
use App\Models\Business;
use App\Models\ListItem;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Sku;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    private Bin $bin;

    private BalloonList $favoritesList;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->business = Business::factory()->create();

        Membership::create([
            'user_id' => $this->owner->id,
            'business_id' => $this->business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $location = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        $this->bin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $location->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        $this->favoritesList = BalloonList::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Favorites',
            'is_business_favorites' => true,
            'created_by_user_id' => $this->owner->id,
        ]);

        BusinessContext::set($this->business->id);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    public function test_dashboard_loads_for_owner(): void
    {
        $response = $this->actingAs($this->owner)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('kpis.distinctSkus')
            ->has('kpis.totalBags')
            ->has('kpis.binCount')
            ->has('kpis.lowStockCount')
            ->has('lowStock')
            ->has('recentActivity')
            ->has('nudges')
            ->has('can')
        );
    }

    public function test_kpi_math_counts_correctly(): void
    {
        $skuA = Sku::factory()->create();
        $skuB = Sku::factory()->create();

        // SKU A: 3 full + 1 open in one bin
        StockLevel::factory()->create([
            'business_id' => $this->business->id,
            'sku_id' => $skuA->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 3,
            'open_bags' => 1,
        ]);

        // SKU B: 5 full + 0 open
        StockLevel::factory()->create([
            'business_id' => $this->business->id,
            'sku_id' => $skuB->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 5,
            'open_bags' => 0,
        ]);

        $response = $this->actingAs($this->owner)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('kpis.distinctSkus', 2)
            ->where('kpis.totalBags', 9) // 3+1+5+0
            ->where('kpis.binCount', 1)
        );
    }

    public function test_low_stock_uses_sealed_bags_only(): void
    {
        $sku = Sku::factory()->create(['name' => 'Test Balloon']);

        // Add to favorites with a threshold of 5
        ListItem::create([
            'list_id' => $this->favoritesList->id,
            'sku_id' => $sku->id,
            'planned_quantity' => 5,
            'sort_order' => 0,
        ]);

        // full_bags = 2 (below threshold), open_bags = 10 (should be ignored)
        StockLevel::factory()->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 2,
            'open_bags' => 10,
        ]);

        $response = $this->actingAs($this->owner)->get(route('dashboard'));

        // SKU is flagged low despite high open_bags
        $response->assertInertia(fn ($page) => $page
            ->where('kpis.lowStockCount', 1)
            ->where('lowStock.0.name', 'Test Balloon')
            ->where('lowStock.0.on_hand', 2)
            ->where('lowStock.0.threshold', 5)
        );
    }

    public function test_sku_above_threshold_is_not_flagged_low(): void
    {
        $sku = Sku::factory()->create(['name' => 'Stocked Balloon']);

        ListItem::create([
            'list_id' => $this->favoritesList->id,
            'sku_id' => $sku->id,
            'planned_quantity' => 3,
            'sort_order' => 0,
        ]);

        // full_bags = 5 (above threshold of 3) → not low
        StockLevel::factory()->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 5,
            'open_bags' => 0,
        ]);

        $response = $this->actingAs($this->owner)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('kpis.lowStockCount', 0)
            ->where('lowStock', [])
        );
    }

    public function test_open_bags_alone_do_not_prevent_low_stock_flag(): void
    {
        $sku = Sku::factory()->create(['name' => 'Open Bag Balloon']);

        ListItem::create([
            'list_id' => $this->favoritesList->id,
            'sku_id' => $sku->id,
            'planned_quantity' => 5,
            'sort_order' => 0,
        ]);

        // 0 full bags, 20 open bags — full_bags ≤ threshold, so it should flag
        StockLevel::factory()->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 0,
            'open_bags' => 20,
        ]);

        $response = $this->actingAs($this->owner)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('kpis.lowStockCount', 1)
            ->where('lowStock.0.on_hand', 0)
        );
    }

    public function test_recent_activity_is_newest_first_with_resolved_names(): void
    {
        $sku = Sku::factory()->create(['name' => 'Pink Balloon']);

        $older = StockMovement::factory()->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'user_id' => $this->owner->id,
            'direction' => 'in',
            'full_bags_change' => 2,
            'created_at' => now()->subHour(),
        ]);

        $newer = StockMovement::factory()->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'user_id' => $this->owner->id,
            'direction' => 'out',
            'full_bags_change' => 1,
            'created_at' => now(),
        ]);

        // Seed stock level so distinctSkus > 0 (dashboard shows content, not empty state)
        StockLevel::factory()->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 5,
        ]);

        $response = $this->actingAs($this->owner)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('recentActivity.0.id', $newer->id)
            ->where('recentActivity.0.sku_name', 'Pink Balloon')
            ->where('recentActivity.0.direction', 'out')
            ->where('recentActivity.1.id', $older->id)
        );
    }

    public function test_owner_gets_all_abilities_in_can_map(): void
    {
        $response = $this->actingAs($this->owner)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('can.checkIn', true)
            ->where('can.checkOut', true)
            ->where('can.adjust', true)
            ->where('can.addInventory', true)
            ->where('can.manageBusiness', true)
            ->where('can.viewCounts', true)
        );
    }

    public function test_guest_lacks_mutate_abilities(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        Membership::create([
            'user_id' => $guest->id,
            'business_id' => $this->business->id,
            'role' => 'guest',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($guest)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('can.checkIn', false)
            ->where('can.checkOut', false)
            ->where('can.adjust', false)
            ->where('can.addInventory', false)
            ->where('can.manageBusiness', false)
            ->where('can.viewCounts', true) // guests can view counts
        );
    }

    public function test_nudge_sample_stock_flag_reflects_is_sample(): void
    {
        $sku = Sku::factory()->create();

        // No sample stock initially
        StockLevel::factory()->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'is_sample' => false,
        ]);

        $response = $this->actingAs($this->owner)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('nudges.hasSampleStock', false)
        );

        // Add sample stock
        $sku2 = Sku::factory()->create();
        StockLevel::factory()->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku2->id,
            'bin_id' => $this->bin->id,
            'is_sample' => true,
        ]);

        $response2 = $this->actingAs($this->owner)->get(route('dashboard'));

        $response2->assertInertia(fn ($page) => $page
            ->where('nudges.hasSampleStock', true)
        );
    }

    public function test_nudge_onboarding_reflects_completed_at(): void
    {
        // Not completed
        $this->business->update(['onboarding_completed_at' => null]);

        $response = $this->actingAs($this->owner)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('nudges.onboardingComplete', false)
        );

        // Now mark complete
        $this->business->update(['onboarding_completed_at' => now()]);

        $response2 = $this->actingAs($this->owner)->get(route('dashboard'));

        $response2->assertInertia(fn ($page) => $page
            ->where('nudges.onboardingComplete', true)
        );
    }

    public function test_user_can_dismiss_user_level_nudge(): void
    {
        // Ensure nudge is visible first
        $this->owner->update(['phone' => null]);

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('nudges.userContactIncomplete', true));

        // Dismiss it
        $this->actingAs($this->owner)
            ->post(route('dashboard.nudges.dismiss'), ['key' => 'user_contact'])
            ->assertRedirect();

        // Nudge no longer shown
        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('nudges.userContactIncomplete', false));
    }

    public function test_business_nudge_dismissal_is_scoped_to_business(): void
    {
        $otherBusiness = Business::factory()->create();

        // Dismiss onboarding for the primary business
        $this->actingAs($this->owner)
            ->post(route('dashboard.nudges.dismiss'), ['key' => 'onboarding'])
            ->assertRedirect();

        // The stored key includes the primary business ID, not the other one
        $this->owner->refresh();
        $dismissed = $this->owner->dismissed_nudges ?? [];

        $this->assertContains("onboarding:{$this->business->id}", $dismissed);
        $this->assertNotContains("onboarding:{$otherBusiness->id}", $dismissed);
    }

    public function test_dismiss_rejects_invalid_nudge_key(): void
    {
        $this->actingAs($this->owner)
            ->post(route('dashboard.nudges.dismiss'), ['key' => 'not_a_real_nudge'])
            ->assertSessionHasErrors('key');
    }
}
