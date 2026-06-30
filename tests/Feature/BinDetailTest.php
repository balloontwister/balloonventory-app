<?php

namespace Tests\Feature;

use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Sku;
use App\Models\StockLevel;
use App\Models\User;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BinDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    private Location $location;

    private Bin $bin;

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

        $this->location = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        $this->bin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $this->location->id,
            'name' => 'Shelf A',
            'number' => 3,
        ]);

        BusinessContext::set($this->business->id);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    private function stock(Sku $sku, Bin $bin, int $full, int $open): StockLevel
    {
        return StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $bin->id,
            'full_bags' => $full,
            'open_bags' => $open,
        ]);
    }

    public function test_show_renders_the_bin_with_its_stocked_items(): void
    {
        $stocked = Sku::factory()->create();
        $empty = Sku::factory()->create();
        $this->stock($stocked, $this->bin, full: 4, open: 1);
        $this->stock($empty, $this->bin, full: 0, open: 0);

        $this->actingAs($this->owner)
            ->get(route('inventory.bins.show', $this->bin))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/BinShow')
                ->where('bin.id', $this->bin->id)
                ->where('fullBagsTotal', 4)
                ->where('openBagsTotal', 1)
                // Only rows that hold something are listed (the 0/0 row is hidden).
                ->has('items', 1)
                ->where('items.0.sku_id', $stocked->id)
                // Locations are passed for the edit-bin form's location picker.
                ->has('locations', 1)
            );
    }

    public function test_deleting_an_empty_bin_redirects_to_the_wall(): void
    {
        // Delete is now triggered from the detail page, so it must land on the
        // wall (the deleted bin's own URL would 404).
        $this->actingAs($this->owner)
            ->delete(route('inventory.bins.destroy', $this->bin))
            ->assertRedirect(route('inventory.bins.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('bins', ['id' => $this->bin->id]);
    }

    public function test_show_404s_for_a_bin_from_another_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherLocation = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id, 'name' => 'Default', 'is_default' => true,
        ]);
        $foreignBin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id, 'location_id' => $otherLocation->id, 'name' => 'Foreign',
        ]);

        $this->actingAs($this->owner)
            ->get(route('inventory.bins.show', $foreignBin))
            ->assertNotFound();
    }

    public function test_add_item_seeds_a_new_sku_into_the_bin_and_records_an_adjustment(): void
    {
        // A shared-catalog SKU this business has never stocked before.
        $sku = Sku::factory()->create();

        $this->actingAs($this->owner)
            ->post(route('inventory.bins.add-item', $this->bin), [
                'sku_id' => $sku->id,
                'full_bags' => 6,
                'open_bags' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('stock_levels', [
            'business_id' => $this->business->id,
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 6,
            'open_bags' => 2,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
            'direction' => 'adjusted',
            'full_bags_change' => 6,
            'open_bags_change' => 2,
        ]);
    }

    public function test_add_item_rejects_a_private_sku_owned_by_another_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $privateSku = Sku::factory()->create(['owned_by_business_id' => $otherBusiness->id]);

        $this->actingAs($this->owner)
            ->post(route('inventory.bins.add-item', $this->bin), [
                'sku_id' => $privateSku->id,
                'full_bags' => 1,
                'open_bags' => 0,
            ])
            ->assertSessionHasErrors('sku_id');

        $this->assertDatabaseMissing('stock_levels', [
            'sku_id' => $privateSku->id,
            'bin_id' => $this->bin->id,
        ]);
    }

    public function test_add_item_is_denied_without_manual_adjust_permission(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        Membership::create([
            'user_id' => $guest->id,
            'business_id' => $this->business->id,
            'role' => 'guest',
            'joined_at' => now(),
        ]);

        $sku = Sku::factory()->create();

        $this->actingAs($guest)
            ->post(route('inventory.bins.add-item', $this->bin), [
                'sku_id' => $sku->id,
                'full_bags' => 1,
                'open_bags' => 0,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('stock_levels', [
            'sku_id' => $sku->id,
            'bin_id' => $this->bin->id,
        ]);
    }

    public function test_search_items_returns_visible_matches_and_flags_in_bin(): void
    {
        $inBin = Sku::factory()->create(['name' => 'Sparkle Ruby Special']);
        $catalog = Sku::factory()->create(['name' => 'Sparkle Sapphire Special']);
        $otherBusiness = Business::factory()->create();
        $foreignPrivate = Sku::factory()->create([
            'name' => 'Sparkle Secret Special',
            'owned_by_business_id' => $otherBusiness->id,
        ]);

        $this->stock($inBin, $this->bin, full: 2, open: 0);

        $response = $this->actingAs($this->owner)
            ->getJson(route('inventory.bins.search-items', $this->bin).'?q=Sparkle+Special')
            ->assertOk();

        $skuIds = collect($response->json('items'))->pluck('sku_id')->all();

        $this->assertContains($inBin->id, $skuIds);
        $this->assertContains($catalog->id, $skuIds);
        $this->assertNotContains($foreignPrivate->id, $skuIds, 'A foreign private SKU must not be searchable.');

        $inBinRow = collect($response->json('items'))->firstWhere('sku_id', $inBin->id);
        $catalogRow = collect($response->json('items'))->firstWhere('sku_id', $catalog->id);
        $this->assertTrue($inBinRow['in_bin']);
        $this->assertFalse($catalogRow['in_bin']);
    }

    private function makeBin(string $name, ?int $number, int $sort, bool $locked = false): Bin
    {
        return Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $this->location->id,
            'name' => $name,
            'number' => $number,
            'number_locked' => $locked,
            'sort_order' => $sort,
        ]);
    }

    public function test_manage_renders_the_storage_view(): void
    {
        $this->actingAs($this->owner)
            ->get(route('inventory.storage'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/ManageStorage')
                ->has('locations'));
    }

    public function test_update_persists_the_number_lock(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('inventory.bins.update', $this->bin), [
                'location_id' => $this->location->id,
                'name' => $this->bin->name,
                'number' => 3,
                'number_locked' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bins', [
            'id' => $this->bin->id,
            'number' => 3,
            'number_locked' => true,
        ]);
    }

    public function test_auto_number_fill_only_numbers_unnumbered_bins(): void
    {
        $this->bin->forceDelete();
        $a = $this->makeBin('A', number: null, sort: 1);
        $b = $this->makeBin('B', number: 5, sort: 2);
        $c = $this->makeBin('C', number: null, sort: 3);

        $this->actingAs($this->owner)
            ->post(route('inventory.bins.auto-number'), ['mode' => 'fill'])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Existing number is preserved; gaps fill with the smallest free numbers.
        $this->assertSame(1, $a->refresh()->number);
        $this->assertSame(5, $b->refresh()->number);
        $this->assertSame(2, $c->refresh()->number);
    }

    public function test_auto_number_renumber_reassigns_unlocked_and_keeps_locked(): void
    {
        $this->bin->forceDelete();
        $a = $this->makeBin('A', number: 9, sort: 1);
        $b = $this->makeBin('B', number: 4, sort: 2, locked: true);
        $c = $this->makeBin('C', number: null, sort: 3);

        $this->actingAs($this->owner)
            ->post(route('inventory.bins.auto-number'), ['mode' => 'renumber'])
            ->assertRedirect();

        // Locked #4 stays and is reserved; unlocked bins take 1, 2 in order.
        $this->assertSame(1, $a->refresh()->number);
        $this->assertSame(4, $b->refresh()->number);
        $this->assertSame(2, $c->refresh()->number);
    }

    public function test_auto_number_is_denied_without_manage_permission(): void
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        Membership::create([
            'user_id' => $guest->id,
            'business_id' => $this->business->id,
            'role' => 'guest',
            'joined_at' => now(),
        ]);

        $this->actingAs($guest)
            ->post(route('inventory.bins.auto-number'), ['mode' => 'fill'])
            ->assertForbidden();
    }

    public function test_bulk_contents_groups_stocked_items_by_bin(): void
    {
        $skuA = Sku::factory()->create();
        $skuB = Sku::factory()->create();
        $this->stock($skuA, $this->bin, full: 2, open: 0);

        $secondBin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $this->location->id,
            'name' => 'Shelf B',
        ]);
        $this->stock($skuB, $secondBin, full: 1, open: 3);

        $response = $this->actingAs($this->owner)
            ->getJson(route('inventory.bins.bulk-contents'))
            ->assertOk();

        $contents = $response->json('contents');

        $this->assertArrayHasKey($this->bin->id, $contents);
        $this->assertArrayHasKey($secondBin->id, $contents);
        $this->assertSame($skuA->id, $contents[$this->bin->id][0]['sku_id']);
        $this->assertSame($skuB->id, $contents[$secondBin->id][0]['sku_id']);
    }

    public function test_bulk_contents_can_be_scoped_to_one_location(): void
    {
        $skuA = Sku::factory()->create();
        $skuB = Sku::factory()->create();
        $this->stock($skuA, $this->bin, full: 2, open: 0);

        $otherLocation = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Warehouse',
        ]);
        $otherBin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $otherLocation->id,
            'name' => 'Rack 1',
        ]);
        $this->stock($skuB, $otherBin, full: 5, open: 0);

        $contents = $this->actingAs($this->owner)
            ->getJson(route('inventory.bins.bulk-contents', ['location' => $otherLocation->id]))
            ->assertOk()
            ->json('contents');

        $this->assertArrayHasKey($otherBin->id, $contents);
        $this->assertArrayNotHasKey($this->bin->id, $contents);
    }
}
