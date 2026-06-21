<?php

namespace Tests\Feature;

use App\Models\BalloonList;
use App\Models\Business;
use App\Models\ListItem;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Sku;
use App\Models\User;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    private BalloonList $favorites;

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

        Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        $this->favorites = BalloonList::withoutGlobalScope(BusinessScope::class)->create([
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

    private function customList(array $attributes = []): BalloonList
    {
        return BalloonList::withoutGlobalScope(BusinessScope::class)->create(array_merge([
            'business_id' => $this->business->id,
            'name' => 'Smith Wedding',
            'is_business_favorites' => false,
            'created_by_user_id' => $this->owner->id,
        ], $attributes));
    }

    /** A guest user + business; guests lack list.delete and favorites.edit. */
    private function guestActor(): array
    {
        $business = Business::factory()->create();
        $guest = User::factory()->create(['email_verified_at' => now()]);

        Membership::create([
            'user_id' => $guest->id,
            'business_id' => $business->id,
            'role' => 'guest',
            'joined_at' => now(),
        ]);

        $favorites = BalloonList::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'name' => 'Favorites',
            'is_business_favorites' => true,
            'created_by_user_id' => $guest->id,
        ]);

        return [$guest, $business, $favorites];
    }

    // ── index ───────────────────────────────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $this->get(route('lists.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_favorites_first(): void
    {
        $this->customList(['name' => 'Zeta']);
        $this->customList(['name' => 'Alpha']);

        $this->actingAs($this->owner)
            ->get(route('lists.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lists/Index')
                ->has('lists', 3)
                ->where('lists.0.is_business_favorites', true)
                ->where('lists.1.name', 'Alpha')
                ->where('lists.2.name', 'Zeta')
            );
    }

    // ── store / update / destroy ─────────────────────────────────────────────────

    public function test_store_creates_a_custom_list(): void
    {
        $this->actingAs($this->owner)
            ->post(route('lists.store'), ['name' => 'Birthday Bash', 'notes' => 'June'])
            ->assertRedirect();

        $this->assertDatabaseHas('lists', [
            'business_id' => $this->business->id,
            'name' => 'Birthday Bash',
            'is_business_favorites' => false,
            'created_by_user_id' => $this->owner->id,
        ]);
    }

    public function test_update_renames_a_custom_list(): void
    {
        $list = $this->customList();

        $this->actingAs($this->owner)
            ->patch(route('lists.update', $list), ['name' => 'Jones Wedding'])
            ->assertRedirect();

        $this->assertSame('Jones Wedding', $list->fresh()->name);
    }

    public function test_favorites_cannot_be_renamed(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('lists.update', $this->favorites), ['name' => 'Hacked'])
            ->assertForbidden();

        $this->assertSame('Favorites', $this->favorites->fresh()->name);
    }

    public function test_destroy_soft_deletes_a_custom_list(): void
    {
        $list = $this->customList();

        $this->actingAs($this->owner)
            ->delete(route('lists.destroy', $list))
            ->assertRedirect(route('lists.index'));

        $this->assertSoftDeleted('lists', ['id' => $list->id]);
    }

    public function test_favorites_cannot_be_deleted(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('lists.destroy', $this->favorites))
            ->assertForbidden();

        $this->assertDatabaseHas('lists', ['id' => $this->favorites->id, 'deleted_at' => null]);
    }

    // ── items ────────────────────────────────────────────────────────────────────

    public function test_items_store_adds_a_visible_sku(): void
    {
        $list = $this->customList();
        $sku = Sku::factory()->create(); // shared catalog SKU

        $this->actingAs($this->owner)
            ->post(route('lists.items.store', $list), ['sku_id' => $sku->id])
            ->assertRedirect();

        $this->assertDatabaseHas('list_items', [
            'list_id' => $list->id,
            'sku_id' => $sku->id,
        ]);
    }

    public function test_items_store_rejects_a_foreign_private_sku(): void
    {
        $list = $this->customList();
        $otherBusiness = Business::factory()->create();
        $privateSku = Sku::factory()->create(['owned_by_business_id' => $otherBusiness->id]);

        $this->actingAs($this->owner)
            ->post(route('lists.items.store', $list), ['sku_id' => $privateSku->id])
            ->assertNotFound();

        $this->assertDatabaseMissing('list_items', ['sku_id' => $privateSku->id]);
    }

    public function test_items_update_sets_planned_quantity(): void
    {
        $list = $this->customList();
        $sku = Sku::factory()->create();
        $item = ListItem::create(['list_id' => $list->id, 'sku_id' => $sku->id]);

        $this->actingAs($this->owner)
            ->patch(route('lists.items.update', ['list' => $list->id, 'item' => $item->id]), [
                'planned_quantity' => 5,
            ])
            ->assertRedirect();

        $this->assertSame('5.00', $item->fresh()->planned_quantity);
    }

    public function test_items_destroy_removes_an_item(): void
    {
        $list = $this->customList();
        $sku = Sku::factory()->create();
        $item = ListItem::create(['list_id' => $list->id, 'sku_id' => $sku->id]);

        $this->actingAs($this->owner)
            ->delete(route('lists.items.destroy', ['list' => $list->id, 'item' => $item->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('list_items', ['id' => $item->id]);
    }

    public function test_item_from_another_list_is_rejected(): void
    {
        $listA = $this->customList(['name' => 'A']);
        $listB = $this->customList(['name' => 'B']);
        $sku = Sku::factory()->create();
        $itemOnB = ListItem::create(['list_id' => $listB->id, 'sku_id' => $sku->id]);

        // Editing list A but passing an item that lives on list B must 404.
        $this->actingAs($this->owner)
            ->patch(route('lists.items.update', ['list' => $listA->id, 'item' => $itemOnB->id]), [
                'planned_quantity' => 3,
            ])
            ->assertNotFound();
    }

    // ── tenant isolation ─────────────────────────────────────────────────────────

    public function test_foreign_list_returns_404(): void
    {
        $otherBusiness = Business::factory()->create();
        $foreignList = BalloonList::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id,
            'name' => 'Not Yours',
            'is_business_favorites' => false,
            'created_by_user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('lists.show', $foreignList))
            ->assertNotFound();
    }

    // ── permissions ──────────────────────────────────────────────────────────────

    public function test_guest_cannot_delete_a_list(): void
    {
        [$guest, $business] = $this->guestActor();
        $list = BalloonList::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'name' => 'Guest List',
            'is_business_favorites' => false,
            'created_by_user_id' => $guest->id,
        ]);

        // Resolve route-model binding under the guest's own business.
        BusinessContext::set($business->id);

        $this->actingAs($guest)
            ->delete(route('lists.destroy', $list))
            ->assertForbidden();
    }

    public function test_guest_cannot_edit_favorites_items(): void
    {
        [$guest, $business, $favorites] = $this->guestActor();
        $sku = Sku::factory()->create();

        // Resolve route-model binding under the guest's own business.
        BusinessContext::set($business->id);

        $this->actingAs($guest)
            ->post(route('lists.items.store', $favorites), ['sku_id' => $sku->id])
            ->assertForbidden();
    }

    // ── inventory "By List" tab ──────────────────────────────────────────────────

    public function test_inventory_view_defaults_to_favorites(): void
    {
        $this->customList();

        $this->actingAs($this->owner)
            ->get(route('inventory.lists.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/Lists')
                ->where('activeList.is_business_favorites', true)
                ->has('lists', 2)
            );
    }
}
