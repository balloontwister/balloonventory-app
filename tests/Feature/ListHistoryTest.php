<?php

namespace Tests\Feature;

use App\Models\BalloonList;
use App\Models\Business;
use App\Models\ListEvent;
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

class ListHistoryTest extends TestCase
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

    // ── store ────────────────────────────────────────────────────────────────────

    public function test_creating_a_list_records_a_created_event(): void
    {
        $this->actingAs($this->owner)
            ->post(route('lists.store'), ['name' => 'Birthday Bash'])
            ->assertRedirect();

        $this->assertDatabaseHas('list_events', [
            'business_id' => $this->business->id,
            'user_id' => $this->owner->id,
            'event_type' => 'created',
        ]);
    }

    // ── update ───────────────────────────────────────────────────────────────────

    public function test_renaming_a_list_records_renamed_with_old_and_new_name(): void
    {
        $list = $this->customList(['name' => 'Old Name']);

        $this->actingAs($this->owner)
            ->patch(route('lists.update', $list), ['name' => 'New Name'])
            ->assertRedirect();

        $event = ListEvent::where('list_id', $list->id)
            ->where('event_type', 'renamed')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('Old Name', $event->payload['old']);
        $this->assertSame('New Name', $event->payload['new']);
    }

    public function test_saving_without_name_change_does_not_record_renamed(): void
    {
        $list = $this->customList(['name' => 'Same Name']);

        $this->actingAs($this->owner)
            ->patch(route('lists.update', $list), ['name' => 'Same Name', 'notes' => 'Updated notes'])
            ->assertRedirect();

        $this->assertDatabaseMissing('list_events', [
            'list_id' => $list->id,
            'event_type' => 'renamed',
        ]);
    }

    public function test_archiving_a_list_records_archived_event(): void
    {
        $list = $this->customList();

        $this->actingAs($this->owner)
            ->patch(route('lists.update', $list), ['name' => $list->name, 'archived' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('list_events', [
            'list_id' => $list->id,
            'event_type' => 'archived',
        ]);
    }

    public function test_unarchiving_a_list_records_unarchived_event(): void
    {
        $list = $this->customList(['archived_at' => now()]);

        $this->actingAs($this->owner)
            ->patch(route('lists.update', $list), ['name' => $list->name, 'archived' => false])
            ->assertRedirect();

        $this->assertDatabaseHas('list_events', [
            'list_id' => $list->id,
            'event_type' => 'unarchived',
        ]);
    }

    public function test_changing_visibility_records_visibility_changed_event(): void
    {
        $list = $this->customList(['visibility' => 'standard']);

        $this->actingAs($this->owner)
            ->patch(route('lists.update', $list), ['name' => $list->name, 'visibility' => 'private'])
            ->assertRedirect();

        $event = ListEvent::where('list_id', $list->id)
            ->where('event_type', 'visibility_changed')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('standard', $event->payload['old']);
        $this->assertSame('private', $event->payload['new']);
    }

    // ── items ────────────────────────────────────────────────────────────────────

    public function test_adding_an_item_records_item_added_with_sku_name(): void
    {
        $list = $this->customList();
        $sku = Sku::factory()->create();

        $this->actingAs($this->owner)
            ->post(route('lists.items.store', $list), ['sku_id' => $sku->id])
            ->assertRedirect();

        $event = ListEvent::where('list_id', $list->id)
            ->where('event_type', 'item_added')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($sku->id, $event->payload['sku_id']);
        $this->assertArrayHasKey('sku_name', $event->payload);
    }

    public function test_removing_an_item_records_item_removed(): void
    {
        $list = $this->customList();
        $sku = Sku::factory()->create();
        $item = ListItem::create(['list_id' => $list->id, 'sku_id' => $sku->id]);

        $this->actingAs($this->owner)
            ->delete(route('lists.items.destroy', ['list' => $list->id, 'item' => $item->id]))
            ->assertRedirect();

        $event = ListEvent::where('list_id', $list->id)
            ->where('event_type', 'item_removed')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($sku->id, $event->payload['sku_id']);
    }

    public function test_changing_planned_quantity_records_item_qty_changed(): void
    {
        $list = $this->customList();
        $sku = Sku::factory()->create();
        $item = ListItem::create(['list_id' => $list->id, 'sku_id' => $sku->id, 'planned_quantity' => 5]);

        $this->actingAs($this->owner)
            ->patch(route('lists.items.update', ['list' => $list->id, 'item' => $item->id]), [
                'planned_quantity' => 10,
            ])
            ->assertRedirect();

        $event = ListEvent::where('list_id', $list->id)
            ->where('event_type', 'item_qty_changed')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals(5, $event->payload['old_qty']);
        $this->assertEquals(10, $event->payload['new_qty']);
    }

    public function test_updating_quantity_to_same_value_does_not_record_event(): void
    {
        $list = $this->customList();
        $sku = Sku::factory()->create();
        $item = ListItem::create(['list_id' => $list->id, 'sku_id' => $sku->id, 'planned_quantity' => 5]);

        $this->actingAs($this->owner)
            ->patch(route('lists.items.update', ['list' => $list->id, 'item' => $item->id]), [
                'planned_quantity' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('list_events', [
            'list_id' => $list->id,
            'event_type' => 'item_qty_changed',
        ]);
    }

    // ── show ─────────────────────────────────────────────────────────────────────

    public function test_show_response_includes_events_prop(): void
    {
        $list = $this->customList();
        ListEvent::create([
            'business_id' => $this->business->id,
            'list_id' => $list->id,
            'user_id' => $this->owner->id,
            'event_type' => 'created',
            'payload' => ['name' => $list->name],
            'created_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->get(route('lists.show', $list))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lists/Show')
                ->has('list.events', 1)
                ->where('list.events.0.type', 'created')
            );
    }

    public function test_inventory_view_does_not_include_events(): void
    {
        $list = $this->customList();
        ListEvent::create([
            'business_id' => $this->business->id,
            'list_id' => $list->id,
            'user_id' => $this->owner->id,
            'event_type' => 'created',
            'payload' => ['name' => $list->name],
            'created_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->get(route('inventory.lists.index', ['list' => $list->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/Lists')
                ->where('activeList.events', [])
            );
    }
}
