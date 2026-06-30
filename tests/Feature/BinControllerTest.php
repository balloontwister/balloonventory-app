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
use Tests\TestCase;

class BinControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    private Location $location;

    private Bin $defaultBin;

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

        $this->defaultBin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $this->location->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        BusinessContext::set($this->business->id);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    private function makeBin(array $attributes = []): Bin
    {
        return Bin::withoutGlobalScope(BusinessScope::class)->create(array_merge([
            'business_id' => $this->business->id,
            'location_id' => $this->location->id,
            'name' => 'Bin',
        ], $attributes));
    }

    private function stockIn(Bin $bin, int $full = 0, int $open = 0): StockLevel
    {
        return StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => Sku::factory()->create()->id,
            'bin_id' => $bin->id,
            'full_bags' => $full,
            'open_bags' => $open,
        ]);
    }

    // ── index ──────────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $this->get(route('inventory.bins.index'))->assertRedirect(route('login'));
    }

    public function test_index_renders_with_locations_and_bins(): void
    {
        $this->actingAs($this->owner)
            ->get(route('inventory.bins.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/Bins')
                ->has('locations', 1)
                ->where('locations.0.id', $this->location->id)
                ->has('locations.0.bins', 1)
            );
    }

    public function test_index_creates_a_default_location_and_bin_when_business_has_none(): void
    {
        // A business predating the bins feature: member, but no location/bin.
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Membership::create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->assertDatabaseMissing('bins', ['business_id' => $business->id]);

        BusinessContext::clear();
        $this->actingAs($user)
            ->get(route('inventory.bins.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('locations', 1));

        $this->assertDatabaseHas('locations', [
            'business_id' => $business->id,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('bins', [
            'business_id' => $business->id,
            'is_default' => true,
        ]);
    }

    public function test_index_includes_stock_summary_for_each_bin(): void
    {
        $this->stockIn($this->defaultBin, full: 5, open: 2);
        $this->stockIn($this->defaultBin, full: 0, open: 0); // empty row should not count

        $this->actingAs($this->owner)
            ->get(route('inventory.bins.index'))
            ->assertInertia(fn ($page) => $page
                ->where('locations.0.bins.0.full_bags_total', 5)
                ->where('locations.0.bins.0.open_bags_total', 2)
                ->where('locations.0.bins.0.sku_count', 1)
            );
    }

    // ── contents ────────────────────────────────────────────────────────────────

    public function test_contents_returns_only_stocked_items(): void
    {
        $stocked = $this->stockIn($this->defaultBin, full: 3, open: 1);
        $this->stockIn($this->defaultBin, full: 0, open: 0);

        $this->actingAs($this->owner)
            ->getJson(route('inventory.bins.contents', ['bin' => $this->defaultBin->id]))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.sku_id', $stocked->sku_id)
            ->assertJsonPath('items.0.full_bags', 3)
            ->assertJsonPath('items.0.open_bags', 1);
    }

    public function test_contents_rejects_a_foreign_bin(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherLocation = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id, 'name' => 'Default', 'is_default' => true,
        ]);
        $otherBin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id, 'location_id' => $otherLocation->id, 'name' => 'Foreign',
        ]);

        $this->actingAs($this->owner)
            ->getJson(route('inventory.bins.contents', ['bin' => $otherBin->id]))
            ->assertNotFound();
    }

    // ── store ───────────────────────────────────────────────────────────────────

    public function test_store_creates_a_bin(): void
    {
        $this->actingAs($this->owner)
            ->post(route('inventory.bins.store'), [
                'location_id' => $this->location->id,
                'name' => 'Top Shelf',
                'number' => 7,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bins', [
            'business_id' => $this->business->id,
            'location_id' => $this->location->id,
            'name' => 'Top Shelf',
            'number' => 7,
            'is_default' => false,
        ]);
    }

    public function test_store_requires_a_name(): void
    {
        $this->actingAs($this->owner)
            ->post(route('inventory.bins.store'), [
                'location_id' => $this->location->id,
                'name' => '',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_store_rejects_a_location_from_another_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherLocation = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id, 'name' => 'Foreign',
        ]);

        $this->actingAs($this->owner)
            ->post(route('inventory.bins.store'), [
                'location_id' => $otherLocation->id,
                'name' => 'Sneaky',
            ])
            ->assertSessionHasErrors('location_id');
    }

    public function test_store_rejects_a_duplicate_number_in_the_same_business(): void
    {
        $this->makeBin(['number' => 3, 'name' => 'Bin 3']);

        $this->actingAs($this->owner)
            ->post(route('inventory.bins.store'), [
                'location_id' => $this->location->id,
                'name' => 'Another',
                'number' => 3,
            ])
            ->assertSessionHasErrors('number');
    }

    // ── update ──────────────────────────────────────────────────────────────────

    public function test_update_edits_a_bin(): void
    {
        $bin = $this->makeBin(['name' => 'Old', 'number' => 4]);

        $this->actingAs($this->owner)
            ->patch(route('inventory.bins.update', ['bin' => $bin->id]), [
                'location_id' => $this->location->id,
                'name' => 'Renamed',
                'number' => 4,
            ])
            ->assertRedirect();

        $this->assertSame('Renamed', $bin->fresh()->name);
    }

    public function test_update_allows_keeping_its_own_number(): void
    {
        $bin = $this->makeBin(['name' => 'Keep', 'number' => 9]);

        $this->actingAs($this->owner)
            ->patch(route('inventory.bins.update', ['bin' => $bin->id]), [
                'location_id' => $this->location->id,
                'name' => 'Keep',
                'number' => 9,
            ])
            ->assertSessionHasNoErrors();
    }

    // ── destroy ─────────────────────────────────────────────────────────────────

    public function test_destroy_deletes_an_empty_non_default_bin(): void
    {
        $bin = $this->makeBin();
        $this->stockIn($bin, full: 0, open: 0); // empty assignment rides along

        $this->actingAs($this->owner)
            ->delete(route('inventory.bins.destroy', ['bin' => $bin->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('bins', ['id' => $bin->id]);
    }

    public function test_destroy_blocks_the_default_bin(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('inventory.bins.destroy', ['bin' => $this->defaultBin->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('bins', [
            'id' => $this->defaultBin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_destroy_blocks_a_bin_that_holds_stock(): void
    {
        $bin = $this->makeBin();
        $this->stockIn($bin, full: 2, open: 0);

        $this->actingAs($this->owner)
            ->delete(route('inventory.bins.destroy', ['bin' => $bin->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('bins', [
            'id' => $bin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cannot_delete_another_businesss_bin(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherLocation = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id, 'name' => 'Default', 'is_default' => true,
        ]);
        $otherBin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id, 'location_id' => $otherLocation->id, 'name' => 'Foreign',
        ]);

        $this->actingAs($this->owner)
            ->delete(route('inventory.bins.destroy', ['bin' => $otherBin->id]))
            ->assertNotFound();
    }

    // ── permission gating (guests can't manage bins) ─────────────────────────────

    private function guestMember(): User
    {
        $guest = User::factory()->create(['email_verified_at' => now()]);
        Membership::create([
            'user_id' => $guest->id,
            'business_id' => $this->business->id,
            'role' => 'guest',
            'joined_at' => now(),
        ]);

        return $guest;
    }

    public function test_store_is_denied_without_manage_permission(): void
    {
        $this->actingAs($this->guestMember())
            ->post(route('inventory.bins.store'), [
                'location_id' => $this->location->id,
                'name' => 'Sneaky bin',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('bins', ['name' => 'Sneaky bin']);
    }

    public function test_update_is_denied_without_manage_permission(): void
    {
        $bin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $this->location->id,
            'name' => 'Shelf',
        ]);

        $this->actingAs($this->guestMember())
            ->patch(route('inventory.bins.update', $bin), [
                'location_id' => $this->location->id,
                'name' => 'Renamed',
            ])
            ->assertForbidden();

        $this->assertSame('Shelf', $bin->refresh()->name);
    }

    public function test_destroy_is_denied_without_manage_permission(): void
    {
        $bin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $this->location->id,
            'name' => 'Shelf',
        ]);

        $this->actingAs($this->guestMember())
            ->delete(route('inventory.bins.destroy', $bin))
            ->assertForbidden();

        $this->assertDatabaseHas('bins', ['id' => $bin->id, 'deleted_at' => null]);
    }
}
