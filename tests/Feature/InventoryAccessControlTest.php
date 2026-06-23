<?php

namespace Tests\Feature;

use App\Models\BalloonList;
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

class InventoryAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Bin $bin;

    private Sku $sku;

    private User $owner;

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

        BalloonList::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Favorites',
            'is_business_favorites' => true,
            'created_by_user_id' => $this->owner->id,
        ]);

        $this->sku = Sku::factory()->create();

        StockLevel::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'sku_id' => $this->sku->id,
            'bin_id' => $this->bin->id,
            'full_bags' => 5,
            'open_bags' => 0,
        ]);

        BusinessContext::set($this->business->id);
    }

    private function memberAs(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Membership::create([
            'user_id' => $user->id,
            'business_id' => $this->business->id,
            'role' => $role,
            'joined_at' => now(),
        ]);

        return $user;
    }

    // ── inventory index ───────────────────────────────────────────────────────

    public function test_staff_can_view_inventory_index(): void
    {
        $this->actingAs($this->memberAs('staff'))
            ->get(route('inventory.index'))
            ->assertOk();
    }

    public function test_guest_can_view_inventory_index(): void
    {
        $this->actingAs($this->memberAs('guest'))
            ->get(route('inventory.index'))
            ->assertOk();
    }

    public function test_none_role_cannot_view_inventory_index(): void
    {
        $this->actingAs($this->memberAs('none'))
            ->get(route('inventory.index'))
            ->assertForbidden();
    }

    // ── adjust (manual count) ─────────────────────────────────────────────────

    public function test_staff_can_adjust_stock(): void
    {
        $this->actingAs($this->memberAs('staff'))
            ->post(route('inventory.sku.adjust', $this->sku), [
                'bin_id' => $this->bin->id,
                'full_bags' => 10,
                'open_bags' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('stock_levels', [
            'sku_id' => $this->sku->id,
            'full_bags' => 10,
        ]);
    }

    public function test_guest_cannot_adjust_stock(): void
    {
        $this->actingAs($this->memberAs('guest'))
            ->post(route('inventory.sku.adjust', $this->sku), [
                'bin_id' => $this->bin->id,
                'full_bags' => 10,
                'open_bags' => 0,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('stock_levels', [
            'sku_id' => $this->sku->id,
            'full_bags' => 5,
        ]);
    }

    public function test_none_role_cannot_adjust_stock(): void
    {
        $this->actingAs($this->memberAs('none'))
            ->post(route('inventory.sku.adjust', $this->sku), [
                'bin_id' => $this->bin->id,
                'full_bags' => 10,
                'open_bags' => 0,
            ])
            ->assertForbidden();
    }

    // ── remove SKU from inventory ─────────────────────────────────────────────

    public function test_staff_can_remove_sku_from_inventory(): void
    {
        $this->actingAs($this->memberAs('staff'))
            ->delete(route('inventory.sku.destroy', $this->sku))
            ->assertRedirect();

        $this->assertDatabaseMissing('stock_levels', [
            'sku_id' => $this->sku->id,
            'deleted_at' => null,
        ]);
    }

    public function test_guest_cannot_remove_sku_from_inventory(): void
    {
        $this->actingAs($this->memberAs('guest'))
            ->delete(route('inventory.sku.destroy', $this->sku))
            ->assertForbidden();
    }

    // ── business switching ────────────────────────────────────────────────────

    public function test_cannot_switch_to_none_role_business(): void
    {
        $owner = $this->memberAs('owner');

        $otherBusiness = Business::factory()->create();
        Membership::create([
            'user_id' => $owner->id,
            'business_id' => $otherBusiness->id,
            'role' => 'none',
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('business.switch', ['business' => $otherBusiness->id]))
            ->assertForbidden();
    }

    public function test_can_switch_to_guest_role_business(): void
    {
        $owner = $this->memberAs('owner');

        $otherBusiness = Business::factory()->create();
        Membership::create([
            'user_id' => $owner->id,
            'business_id' => $otherBusiness->id,
            'role' => 'guest',
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('business.switch', ['business' => $otherBusiness->id]))
            ->assertRedirect();
    }
}
