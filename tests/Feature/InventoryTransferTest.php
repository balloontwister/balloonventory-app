<?php

namespace Tests\Feature;

use App\Models\Bin;
use App\Models\Business;
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

class InventoryTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    private Location $location;

    private Bin $binA;

    private Bin $binB;

    private Sku $sku;

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

        $this->binA = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $this->location->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        $this->binB = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $this->location->id,
            'name' => 'Shelf B',
        ]);

        BusinessContext::set($this->business->id);

        $this->sku = Sku::factory()->create();
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    private function stock(Bin $bin, int $full, int $open): StockLevel
    {
        return StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $bin->id,
            'full_bags' => $full,
            'open_bags' => $open,
        ]);
    }

    private function transfer(array $payload)
    {
        return $this->actingAs($this->owner)->post(
            route('inventory.sku.transfer', ['sku' => $this->sku->id]),
            $payload,
        );
    }

    public function test_transfer_moves_stock_between_bins(): void
    {
        $this->stock($this->binA, full: 5, open: 2);

        $this->transfer([
            'from_bin_id' => $this->binA->id,
            'to_bin_id' => $this->binB->id,
            'full_bags_change' => 3,
            'open_bags_change' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('stock_levels', [
            'sku_id' => $this->sku->id, 'bin_id' => $this->binA->id, 'full_bags' => 2, 'open_bags' => 1,
        ]);
        $this->assertDatabaseHas('stock_levels', [
            'sku_id' => $this->sku->id, 'bin_id' => $this->binB->id, 'full_bags' => 3, 'open_bags' => 1,
        ]);
    }

    public function test_transfer_writes_two_paired_adjusted_movements(): void
    {
        $this->stock($this->binA, full: 4, open: 0);

        $this->transfer([
            'from_bin_id' => $this->binA->id,
            'to_bin_id' => $this->binB->id,
            'full_bags_change' => 2,
            'open_bags_change' => 0,
        ])->assertRedirect();

        // Source leg: negative change; destination leg: positive; shared transfer_id.
        $this->assertDatabaseHas('stock_movements', [
            'sku_id' => $this->sku->id, 'bin_id' => $this->binA->id, 'direction' => 'adjusted', 'full_bags_change' => -2,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'sku_id' => $this->sku->id, 'bin_id' => $this->binB->id, 'direction' => 'adjusted', 'full_bags_change' => 2,
        ]);

        $transferIds = StockMovement::withoutGlobalScope(BusinessScope::class)
            ->where('sku_id', $this->sku->id)
            ->whereNotNull('transfer_id')
            ->pluck('transfer_id')
            ->all();

        $this->assertCount(2, $transferIds, 'Both legs should carry a transfer_id.');
        $this->assertCount(1, array_unique($transferIds), 'Both legs should share one transfer_id.');
    }

    public function test_transfer_rejects_insufficient_source_stock(): void
    {
        $this->stock($this->binA, full: 1, open: 0);

        $this->transfer([
            'from_bin_id' => $this->binA->id,
            'to_bin_id' => $this->binB->id,
            'full_bags_change' => 5,
            'open_bags_change' => 0,
        ])->assertSessionHasErrors('full_bags_change');

        // Nothing moved.
        $this->assertDatabaseHas('stock_levels', [
            'sku_id' => $this->sku->id, 'bin_id' => $this->binA->id, 'full_bags' => 1,
        ]);
        $this->assertDatabaseMissing('stock_levels', [
            'sku_id' => $this->sku->id, 'bin_id' => $this->binB->id,
        ]);
    }

    public function test_transfer_rejects_same_source_and_destination(): void
    {
        $this->stock($this->binA, full: 3, open: 0);

        $this->transfer([
            'from_bin_id' => $this->binA->id,
            'to_bin_id' => $this->binA->id,
            'full_bags_change' => 1,
            'open_bags_change' => 0,
        ])->assertSessionHasErrors('to_bin_id');
    }

    public function test_transfer_rejects_zero_quantity(): void
    {
        $this->stock($this->binA, full: 3, open: 0);

        $this->transfer([
            'from_bin_id' => $this->binA->id,
            'to_bin_id' => $this->binB->id,
            'full_bags_change' => 0,
            'open_bags_change' => 0,
        ])->assertSessionHasErrors('full_bags_change');
    }

    public function test_transfer_rejects_a_bin_from_another_business(): void
    {
        $this->stock($this->binA, full: 3, open: 0);

        $otherBusiness = Business::factory()->create();
        $otherLocation = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id, 'name' => 'Default', 'is_default' => true,
        ]);
        $otherBin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id, 'location_id' => $otherLocation->id, 'name' => 'Foreign',
        ]);

        $this->transfer([
            'from_bin_id' => $this->binA->id,
            'to_bin_id' => $otherBin->id,
            'full_bags_change' => 1,
            'open_bags_change' => 0,
        ])->assertSessionHasErrors('to_bin_id');
    }

    public function test_transfer_requires_the_sku_to_be_in_inventory(): void
    {
        $strangerSku = Sku::factory()->create();

        $this->actingAs($this->owner)->post(
            route('inventory.sku.transfer', ['sku' => $strangerSku->id]),
            [
                'from_bin_id' => $this->binA->id,
                'to_bin_id' => $this->binB->id,
                'full_bags_change' => 1,
                'open_bags_change' => 0,
            ],
        )->assertNotFound();
    }
}
