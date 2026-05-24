<?php

namespace Tests\Feature;

use App\Enums\StockDirection;
use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Models\Sku;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Scopes\BusinessScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventorySchemaTest extends TestCase
{
    use RefreshDatabase;

    // ── Business registration seeds ───────────────────────────────────────────

    public function test_registering_a_business_creates_default_location_and_bin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/onboarding/create-business', ['name' => 'Test Balloons']);

        $business = Business::latest()->first();

        $location = Location::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('is_default', true)
            ->first();

        $this->assertNotNull($location, 'Default location was not created.');
        $this->assertSame('Default', $location->name);

        $bin = Bin::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('is_default', true)
            ->first();

        $this->assertNotNull($bin, 'Default bin was not created.');
        $this->assertSame('Default', $bin->name);
        $this->assertSame($location->id, $bin->location_id);
        $this->assertNotNull($bin->scan_code);
        $this->assertStringStartsWith('BIN-', $bin->scan_code);
    }

    public function test_registering_a_business_still_creates_favorites_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/onboarding/create-business', ['name' => 'Test Balloons']);

        $business = Business::latest()->first();

        $this->assertDatabaseHas('lists', [
            'business_id' => $business->id,
            'is_business_favorites' => true,
        ]);
    }

    // ── Bin scan code ─────────────────────────────────────────────────────────

    public function test_bin_auto_generates_scan_code_on_creation(): void
    {
        $business = Business::factory()->create();
        $location = Location::factory()->default()->create(['business_id' => $business->id]);

        $bin = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'location_id' => $location->id,
            'name' => 'Bin #5',
            'number' => 5,
        ]);

        $this->assertNotNull($bin->scan_code);
        $this->assertStringStartsWith('BIN-', $bin->scan_code);
    }

    public function test_bin_scan_codes_are_unique(): void
    {
        $business = Business::factory()->create();
        $location = Location::factory()->default()->create(['business_id' => $business->id]);

        $binA = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'location_id' => $location->id,
            'name' => 'Bin #1',
        ]);

        $binB = Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'location_id' => $location->id,
            'name' => 'Bin #2',
        ]);

        $this->assertNotSame($binA->scan_code, $binB->scan_code);
    }

    // ── Default location/bin protection ──────────────────────────────────────

    public function test_default_location_cannot_be_deleted(): void
    {
        $business = Business::factory()->create();
        $location = Location::factory()->default()->create(['business_id' => $business->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The Default location cannot be deleted.');

        $location->delete();
    }

    public function test_default_bin_cannot_be_deleted(): void
    {
        $business = Business::factory()->create();
        $location = Location::factory()->default()->create(['business_id' => $business->id]);
        $bin = Bin::factory()->default()->create([
            'business_id' => $business->id,
            'location_id' => $location->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The Default bin cannot be deleted.');

        $bin->delete();
    }

    // ── StockLevel: bags model ────────────────────────────────────────────────

    public function test_stock_level_stores_full_and_open_bags(): void
    {
        $business = Business::factory()->create();
        $location = Location::factory()->default()->create(['business_id' => $business->id]);
        $bin = Bin::factory()->default()->create([
            'business_id' => $business->id,
            'location_id' => $location->id,
        ]);
        $sku = Sku::factory()->create();

        $level = StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'sku_id' => $sku->id,
            'bin_id' => $bin->id,
            'full_bags' => 4,
            'open_bags' => 1,
        ]);

        $this->assertSame(4, $level->full_bags);
        $this->assertSame(1, $level->open_bags);
    }

    public function test_stock_level_soft_deletes(): void
    {
        $business = Business::factory()->create();
        $location = Location::factory()->default()->create(['business_id' => $business->id]);
        $bin = Bin::factory()->default()->create([
            'business_id' => $business->id,
            'location_id' => $location->id,
        ]);
        $sku = Sku::factory()->create();

        $level = StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'sku_id' => $sku->id,
            'bin_id' => $bin->id,
            'full_bags' => 3,
            'open_bags' => 0,
        ]);

        $level->delete();

        $this->assertSoftDeleted('stock_levels', ['id' => $level->id]);
    }

    // ── StockMovement: direction enum ─────────────────────────────────────────

    public function test_stock_movement_stores_all_direction_enum_values(): void
    {
        $business = Business::factory()->create();
        $sku = Sku::factory()->create();
        $user = User::factory()->create();

        foreach (StockDirection::cases() as $direction) {
            $movement = StockMovement::withoutGlobalScope(BusinessScope::class)->create([
                'business_id' => $business->id,
                'sku_id' => $sku->id,
                'user_id' => $user->id,
                'direction' => $direction,
                'full_bags_change' => 1,
                'open_bags_change' => 0,
            ]);

            $this->assertSame($direction, $movement->direction);
        }
    }

    public function test_stock_movement_records_removal_event(): void
    {
        $business = Business::factory()->create();
        $sku = Sku::factory()->create();
        $user = User::factory()->create();

        $movement = StockMovement::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'sku_id' => $sku->id,
            'user_id' => $user->id,
            'direction' => StockDirection::Removed,
            'full_bags_change' => 0,
            'open_bags_change' => 0,
            'notes' => 'Removed from inventory by user.',
        ]);

        $this->assertSame(StockDirection::Removed, $movement->direction);
        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'direction' => 'removed',
        ]);
    }

    // ── BusinessSkuOverride: removed columns ──────────────────────────────────

    public function test_business_sku_override_no_longer_has_removed_columns(): void
    {
        $this->assertFalse(
            Schema::hasColumn('business_sku_overrides', 'is_hidden'),
            'is_hidden should have been removed from business_sku_overrides.'
        );

        $this->assertFalse(
            Schema::hasColumn('business_sku_overrides', 'reorder_threshold'),
            'reorder_threshold should have been removed from business_sku_overrides.'
        );
    }

    // ── Sku: url field ────────────────────────────────────────────────────────

    public function test_sku_stores_purchase_url(): void
    {
        $sku = Sku::factory()->create(['url' => 'https://example.com/product/123']);

        $this->assertSame('https://example.com/product/123', $sku->fresh()->url);
    }
}
