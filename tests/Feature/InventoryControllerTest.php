<?php

namespace Tests\Feature;

use App\Models\BalloonList;
use App\Models\BalloonSize;
use App\Models\Bin;
use App\Models\Brand;
use App\Models\Business;
use App\Models\Color;
use App\Models\ColorFamily;
use App\Models\ListItem;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Models\StockLevel;
use App\Models\User;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    private Location $location;

    private Bin $bin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->business = Business::factory()->create();

        Membership::create([
            'user_id' => $this->owner->id,
            'business_id' => $this->business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->location = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        $this->bin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $this->location->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        // Seed a Favorites list for the business.
        BalloonList::withoutGlobalScope(BusinessScope::class)->create([
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

    // ── index ──────────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $this->get(route('inventory.index'))->assertRedirect(route('login'));
    }

    public function test_index_returns_ok_for_authenticated_owner(): void
    {
        $this->actingAs($this->owner)
            ->get(route('inventory.index'))
            ->assertOk();
    }

    public function test_index_only_shows_skus_in_this_business_inventory(): void
    {
        $sku = Sku::factory()->create();
        $otherSku = Sku::factory()->create();
        $otherBusiness = Business::factory()->create();

        // Only $sku is in this business's inventory.
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 2,
            'open_bags' => 0,
        ]);

        // $otherSku is in another business's inventory — should not appear.
        $otherLocation = Location::withoutGlobalScope(BusinessScope::class)
            ->create(['business_id' => $otherBusiness->id, 'name' => 'Default', 'is_default' => true]);
        $otherBin = Bin::withoutGlobalScope(BusinessScope::class)
            ->create(['business_id' => $otherBusiness->id, 'location_id' => $otherLocation->id, 'name' => 'Default', 'is_default' => true]);

        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id,
            'sku_id' => $otherSku->id,
            'bin_id' => $otherBin->id,
            'full_bags' => 5,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->get(route('inventory.index'))
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1)
                ->where('skus.data.0.id', $sku->id)
            );
    }

    public function test_index_returns_bag_totals(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 3,
            'open_bags' => 1,
        ]);

        $this->actingAs($this->owner)
            ->get(route('inventory.index'))
            ->assertInertia(fn ($page) => $page
                ->where('skus.data.0.full_bags_total', 3)
                ->where('skus.data.0.open_bags_total', 1)
            );
    }

    public function test_index_search_returns_catalog_fallback_for_non_inventory_skus(): void
    {
        $inventorySku = Sku::factory()->create(['name' => 'Round 11" Red Balloon']);
        $catalogSku = Sku::factory()->create(['name' => 'Round 11" Blue Balloon']);

        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $inventorySku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 2,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->get(route('inventory.index', ['search' => 'Round 11']))
            ->assertInertia(fn ($page) => $page
                ->has('skus.data', 1)
                ->where('skus.data.0.id', $inventorySku->id)
                ->has('catalogSkus', 1)
                ->where('catalogSkus.0.id', $catalogSku->id)
            );
    }

    public function test_index_catalog_fallback_honors_dropdown_filters(): void
    {
        // Reproduces the reported scenario: a user picks Brand + Size + Color
        // Family from the dropdowns (no free-text) to find a master-catalog item.
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        $size = Size::factory()->create(['name' => '260']);
        $shape = Shape::factory()->create();
        $reds = ColorFamily::factory()->create();
        $red = Color::factory()->create(['name' => 'Red', 'color_family_id' => $reds->id]);

        $balloonSize = BalloonSize::factory()->create([
            'brand_id' => $brand->id,
            'size_id' => $size->id,
            'shape_id' => $shape->id,
        ]);

        $match = Sku::factory()->create([
            'name' => '260M Standard Red - 100CT',
            'brand_id' => $brand->id,
            'balloon_size_id' => $balloonSize->id,
            'color_id' => $red->id,
        ]);

        // A shared catalog SKU that does NOT match the chosen brand/size/color.
        $other = Sku::factory()->create(['name' => 'Round 11" Blue Balloon']);

        $this->actingAs($this->owner)
            ->get(route('inventory.index', [
                'brand' => $brand->id,
                'size' => $size->id,
                'color_family' => $reds->id,
            ]))
            ->assertInertia(fn ($page) => $page
                ->has('catalogSkus', 1)
                ->where('catalogSkus.0.id', $match->id)
            );
    }

    public function test_index_catalog_fallback_excludes_skus_already_in_inventory(): void
    {
        $brand = Brand::factory()->create();

        $inInventory = Sku::factory()->create(['brand_id' => $brand->id]);
        $catalogOnly = Sku::factory()->create(['brand_id' => $brand->id]);

        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $inInventory->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 1,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->get(route('inventory.index', ['brand' => $brand->id]))
            ->assertInertia(fn ($page) => $page
                ->has('catalogSkus', 1)
                ->where('catalogSkus.0.id', $catalogOnly->id)
            );
    }

    public function test_index_search_matches_color_name(): void
    {
        $crimson = Color::factory()->create(['name' => 'Crimson']);
        $sku = Sku::factory()->create([
            'name' => 'Some SKU Without The Word In Its Name',
            'color_id' => $crimson->id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('inventory.index', ['search' => 'Crimson']))
            ->assertInertia(fn ($page) => $page
                ->has('catalogSkus', 1)
                ->where('catalogSkus.0.id', $sku->id)
            );
    }

    public function test_index_search_matches_brand_name(): void
    {
        $brand = Brand::factory()->create(['name' => 'Tuftex']);
        $sku = Sku::factory()->create([
            'name' => 'Plain Balloon',
            'brand_id' => $brand->id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('inventory.index', ['search' => 'Tuftex']))
            ->assertInertia(fn ($page) => $page
                ->has('catalogSkus', 1)
                ->where('catalogSkus.0.id', $sku->id)
            );
    }

    public function test_index_search_matches_words_spread_across_fields(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kalisan']);
        $shape = Shape::factory()->create(['name' => 'Link']);
        $balloonSize = BalloonSize::factory()->create([
            'brand_id' => $brand->id,
            'shape_id' => $shape->id,
        ]);
        $color = Color::factory()->create(['name' => 'Macaron Blue']);

        $match = Sku::factory()->create([
            'name' => 'K-Link 50CT',
            'brand_id' => $brand->id,
            'balloon_size_id' => $balloonSize->id,
            'color_id' => $color->id,
        ]);
        Sku::factory()->create(['name' => 'Unrelated Item']);

        $this->actingAs($this->owner)
            ->get(route('inventory.index', ['search' => 'Kalisan Blue Link']))
            ->assertInertia(fn ($page) => $page
                ->has('catalogSkus', 1)
                ->where('catalogSkus.0.id', $match->id)
            );
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_ok_for_sku_in_inventory(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 1,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->get(route('inventory.sku.show', $sku))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sku.id', $sku->id)
            );
    }

    public function test_show_returns_404_for_sku_not_in_inventory(): void
    {
        $sku = Sku::factory()->create();

        $this->actingAs($this->owner)
            ->get(route('inventory.sku.show', $sku))
            ->assertNotFound();
    }

    // ── adjust ────────────────────────────────────────────────────────────────

    public function test_adjust_sets_bag_counts_and_records_adjusted_movement(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 2,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.adjust', $sku), [
                'bin_id' => $this->bin->id,
                'full_bags' => 5,
                'open_bags' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_levels', [
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 5,
            'open_bags' => 1,
        ]);

        // Net change recorded as one adjusted movement: +3 full, +1 open.
        $this->assertDatabaseHas('stock_movements', [
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'direction' => 'adjusted',
            'full_bags_change' => 3,
            'open_bags_change' => 1,
        ]);
    }

    public function test_adjust_creates_stock_level_for_a_new_bin(): void
    {
        $sku = Sku::factory()->create();
        // SKU is in inventory via the default bin.
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 1,
            'open_bags' => 0,
        ]);

        $secondBin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $this->location->id,
            'name' => 'Shelf B',
        ]);

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.adjust', $sku), [
                'bin_id' => $secondBin->id,
                'full_bags' => 4,
                'open_bags' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_levels', [
            'sku_id' => $sku->id,
            'bin_id' => $secondBin->id,
            'full_bags' => 4,
        ]);
    }

    public function test_adjust_with_no_net_change_records_no_movement(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 3,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.adjust', $sku), [
                'bin_id' => $this->bin->id,
                'full_bags' => 3,
                'open_bags' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('stock_movements', [
            'sku_id' => $sku->id,
            'direction' => 'adjusted',
        ]);
    }

    public function test_adjust_rejects_bin_from_another_business(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 1,
            'open_bags' => 0,
        ]);

        $otherBusiness = Business::factory()->create();
        $otherLocation = Location::withoutGlobalScope(BusinessScope::class)
            ->create(['business_id' => $otherBusiness->id, 'name' => 'Default', 'is_default' => true]);
        $foreignBin = Bin::withoutGlobalScope(BusinessScope::class)
            ->create(['business_id' => $otherBusiness->id, 'location_id' => $otherLocation->id, 'name' => 'Default', 'is_default' => true]);

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.adjust', $sku), [
                'bin_id' => $foreignBin->id,
                'full_bags' => 2,
                'open_bags' => 0,
            ])
            ->assertSessionHasErrors('bin_id');
    }

    public function test_adjust_returns_404_for_sku_not_in_inventory(): void
    {
        $sku = Sku::factory()->create();

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.adjust', $sku), [
                'bin_id' => $this->bin->id,
                'full_bags' => 2,
                'open_bags' => 0,
            ])
            ->assertNotFound();
    }

    public function test_adjust_rejects_negative_bag_counts(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 1,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.adjust', $sku), [
                'bin_id' => $this->bin->id,
                'full_bags' => -1,
                'open_bags' => 0,
            ])
            ->assertSessionHasErrors('full_bags');
    }

    // ── removeStockBin ──────────────────────────────────────────────────────────

    public function test_remove_stock_bin_soft_deletes_an_empty_level(): void
    {
        $sku = Sku::factory()->create();

        // Two bins: one with stock, one empty.
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 3,
            'open_bags' => 0,
        ]);
        $emptyBin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $this->location->id,
            'name' => 'Shelf B',
        ]);
        $emptyLevel = StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $emptyBin->id,
            'full_bags' => 0,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('inventory.sku.bin.remove', [$sku->id, $emptyBin->id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('stock_levels', ['id' => $emptyLevel->id]);
        $this->assertDatabaseHas('stock_levels', [
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_remove_stock_bin_rejects_a_non_empty_bin(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 2,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('inventory.sku.bin.remove', [$sku->id, $this->bin->id]))
            ->assertSessionHasErrors('bin_id');

        $this->assertDatabaseHas('stock_levels', [
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_remove_last_empty_bin_drops_sku_from_inventory(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 0,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('inventory.sku.bin.remove', [$sku->id, $this->bin->id]))
            ->assertRedirect(route('inventory.index'));

        $this->assertFalse(
            StockLevel::where('sku_id', $sku->id)->exists(),
        );
    }

    // ── show: identical SKUs ────────────────────────────────────────────────────

    public function test_show_includes_identical_skus_that_are_in_inventory(): void
    {
        $sku = Sku::factory()->create();
        $identicalInInventory = Sku::factory()->create();
        $identicalNotInInventory = Sku::factory()->create();

        $sku->linkIdentical($identicalInInventory);
        $sku->linkIdentical($identicalNotInInventory);

        foreach ([$sku, $identicalInInventory] as $s) {
            StockLevel::withoutGlobalScope(BusinessScope::class)->create([
                'business_id' => $this->business->id,
                'sku_id' => $s->id,
                'bin_id' => $this->bin->id,
                'full_bags' => 1,
                'open_bags' => 0,
            ]);
        }

        $this->actingAs($this->owner)
            ->get(route('inventory.sku.show', $sku))
            ->assertInertia(fn ($page) => $page
                ->has('identicalSkus', 1)
                ->where('identicalSkus.0.id', $identicalInInventory->id)
            );
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_adds_sku_to_inventory(): void
    {
        $sku = Sku::factory()->create();

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.store'), ['sku_id' => $sku->id])
            ->assertRedirect();

        $this->assertDatabaseHas('stock_levels', [
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 0,
            'open_bags' => 0,
        ]);
    }

    public function test_store_is_idempotent(): void
    {
        $sku = Sku::factory()->create();

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.store'), ['sku_id' => $sku->id]);
        $this->actingAs($this->owner)
            ->post(route('inventory.sku.store'), ['sku_id' => $sku->id]);

        $this->assertDatabaseCount('stock_levels', 1);
    }

    public function test_store_rejects_sku_owned_by_another_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $foreignSku = Sku::factory()->create(['owned_by_business_id' => $otherBusiness->id]);

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.store'), ['sku_id' => $foreignSku->id])
            ->assertSessionHasErrors('sku_id');

        $this->assertDatabaseMissing('stock_levels', ['sku_id' => $foreignSku->id]);
    }

    public function test_store_rejects_soft_deleted_sku(): void
    {
        $sku = Sku::factory()->create();
        $sku->delete();

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.store'), ['sku_id' => $sku->id])
            ->assertSessionHasErrors('sku_id');

        $this->assertDatabaseMissing('stock_levels', ['sku_id' => $sku->id]);
    }

    public function test_store_accepts_sku_owned_by_current_business(): void
    {
        $ownSku = Sku::factory()->create(['owned_by_business_id' => $this->business->id]);

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.store'), ['sku_id' => $ownSku->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_levels', [
            'business_id' => $this->business->id,
            'sku_id' => $ownSku->id,
        ]);
    }

    public function test_add_favorite_rejects_sku_owned_by_another_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $foreignSku = Sku::factory()->create(['owned_by_business_id' => $otherBusiness->id]);

        $this->actingAs($this->owner)
            ->post(route('favorites.add', $foreignSku))
            ->assertNotFound();

        $this->assertDatabaseMissing('list_items', ['sku_id' => $foreignSku->id]);
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_removes_sku_from_inventory(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 3,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('inventory.sku.destroy', $sku))
            ->assertRedirect(route('inventory.index'));

        $this->assertSoftDeleted('stock_levels', [
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
        ]);
    }

    public function test_destroy_records_removal_movement(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 2,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('inventory.sku.destroy', $sku));

        $this->assertDatabaseHas('stock_movements', [
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'direction' => 'removed',
        ]);
    }

    public function test_destroy_returns_404_for_sku_not_in_inventory(): void
    {
        $sku = Sku::factory()->create();

        $this->actingAs($this->owner)
            ->delete(route('inventory.sku.destroy', $sku))
            ->assertNotFound();
    }

    // ── updateOverride ────────────────────────────────────────────────────────

    public function test_update_override_saves_custom_fields(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 1,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->patch(route('inventory.override.update', $sku), [
                'custom_name' => 'My Red Balloon',
                'custom_color_hex' => '#FF0000',
                'notes' => 'Ordered from Balloonventory.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('business_sku_overrides', [
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'custom_name' => 'My Red Balloon',
            'custom_color_hex' => '#FF0000',
        ]);
    }

    public function test_update_override_is_upsert(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 1,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->patch(route('inventory.override.update', $sku), ['custom_name' => 'First Name']);
        $this->actingAs($this->owner)
            ->patch(route('inventory.override.update', $sku), ['custom_name' => 'Updated Name']);

        $this->assertDatabaseCount('business_sku_overrides', 1);
        $this->assertDatabaseHas('business_sku_overrides', ['custom_name' => 'Updated Name']);
    }

    public function test_update_override_validates_color_hex_format(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 1,
            'open_bags' => 0,
        ]);

        $this->actingAs($this->owner)
            ->patch(route('inventory.override.update', $sku), ['custom_color_hex' => 'not-a-hex'])
            ->assertSessionHasErrors('custom_color_hex');
    }

    // ── addToList ─────────────────────────────────────────────────────────────

    public function test_add_to_list_creates_list_item(): void
    {
        $sku = Sku::factory()->create();
        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 1,
            'open_bags' => 0,
        ]);

        $list = BalloonList::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Holiday',
            'is_business_favorites' => false,
            'created_by_user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->post(route('inventory.sku.add-to-list', $sku), ['list_id' => $list->id])
            ->assertRedirect();

        $this->assertDatabaseHas('list_items', [
            'list_id' => $list->id,
            'sku_id' => $sku->id,
        ]);
    }

    // ── favorites ─────────────────────────────────────────────────────────────

    public function test_add_favorite_creates_list_item_in_favorites(): void
    {
        $sku = Sku::factory()->create();

        $this->actingAs($this->owner)
            ->post(route('favorites.add', $sku))
            ->assertRedirect();

        $favoritesListId = BalloonList::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $this->business->id)
            ->where('is_business_favorites', true)
            ->value('id');

        $this->assertDatabaseHas('list_items', [
            'list_id' => $favoritesListId,
            'sku_id' => $sku->id,
        ]);
    }

    public function test_remove_favorite_deletes_list_item(): void
    {
        $sku = Sku::factory()->create();

        $favoritesListId = BalloonList::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $this->business->id)
            ->where('is_business_favorites', true)
            ->value('id');

        ListItem::create(['list_id' => $favoritesListId, 'sku_id' => $sku->id]);

        $this->actingAs($this->owner)
            ->post(route('favorites.remove', $sku))
            ->assertRedirect();

        $this->assertDatabaseMissing('list_items', [
            'list_id' => $favoritesListId,
            'sku_id' => $sku->id,
        ]);
    }
}
