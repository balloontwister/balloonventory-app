<?php

namespace Tests\Feature;

use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Models\Membership;
use App\Models\User;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    private Location $defaultLocation;

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

        $this->defaultLocation = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
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

    public function test_store_creates_a_location(): void
    {
        $this->actingAs($this->owner)
            ->post(route('inventory.locations.store'), [
                'name' => 'Back Room',
                'description' => 'Behind the counter',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('locations', [
            'business_id' => $this->business->id,
            'name' => 'Back Room',
            'is_default' => false,
        ]);
    }

    public function test_store_requires_a_name(): void
    {
        $this->actingAs($this->owner)
            ->post(route('inventory.locations.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_update_renames_a_location(): void
    {
        $location = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Old',
        ]);

        $this->actingAs($this->owner)
            ->patch(route('inventory.locations.update', ['location' => $location->id]), [
                'name' => 'New Name',
            ])
            ->assertRedirect();

        $this->assertSame('New Name', $location->fresh()->name);
    }

    public function test_destroy_deletes_an_empty_non_default_location(): void
    {
        $location = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Temp',
        ]);

        $this->actingAs($this->owner)
            ->delete(route('inventory.locations.destroy', ['location' => $location->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('locations', ['id' => $location->id]);
    }

    public function test_destroy_blocks_the_default_location(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('inventory.locations.destroy', ['location' => $this->defaultLocation->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('locations', [
            'id' => $this->defaultLocation->id,
            'deleted_at' => null,
        ]);
    }

    public function test_destroy_blocks_a_location_that_still_has_bins(): void
    {
        $location = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Has Bins',
        ]);
        Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'location_id' => $location->id,
            'name' => 'A bin',
        ]);

        $this->actingAs($this->owner)
            ->delete(route('inventory.locations.destroy', ['location' => $location->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cannot_update_another_businesss_location(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherLocation = Location::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $otherBusiness->id,
            'name' => 'Foreign',
        ]);

        $this->actingAs($this->owner)
            ->patch(route('inventory.locations.update', ['location' => $otherLocation->id]), [
                'name' => 'Hacked',
            ])
            ->assertNotFound();

        $this->assertSame('Foreign', $otherLocation->fresh()->name);
    }
}
