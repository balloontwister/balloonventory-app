<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessDistributor;
use App\Models\Distributor;
use App\Models\Membership;
use App\Models\User;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SettingsDistributorsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    private Business $business;

    private Business $otherBusiness;

    private Distributor $distributorA;

    private Distributor $distributorB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->business = Business::factory()->create();
        $this->otherBusiness = Business::factory()->create();

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        Membership::create([
            'user_id' => $this->owner->id,
            'business_id' => $this->business->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->staff = User::factory()->create(['email_verified_at' => now()]);
        Membership::create([
            'user_id' => $this->staff->id,
            'business_id' => $this->business->id,
            'role' => 'staff',
            'joined_at' => now(),
        ]);

        $this->distributorA = Distributor::factory()->shopify()->create();
        $this->distributorB = Distributor::factory()->bigcommerce()->create();

        BusinessContext::set($this->business->id);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    public function test_owner_can_enable_distributors(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.distributors.update'), [
                'distributor_ids' => [$this->distributorA->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('business_distributors', [
            'business_id' => $this->business->id,
            'distributor_id' => $this->distributorA->id,
            'is_enabled' => true,
        ]);
    }

    public function test_enabling_one_business_does_not_touch_another_business(): void
    {
        // The other business has distributor B enabled.
        BusinessDistributor::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->otherBusiness->id,
            'distributor_id' => $this->distributorB->id,
            'is_enabled' => true,
        ]);

        // Our business enables, then disables, distributor B — the re-enable
        // path (a dirty update on the composite-key model) previously emitted
        // an UPDATE with no WHERE that flipped every business's row.
        $this->actingAs($this->owner)
            ->post(route('settings.distributors.update'), [
                'distributor_ids' => [$this->distributorB->id],
            ]);

        $this->actingAs($this->owner)
            ->post(route('settings.distributors.update'), [
                'distributor_ids' => [],
            ]);

        // The other business's preference must be completely untouched.
        $this->assertDatabaseHas('business_distributors', [
            'business_id' => $this->otherBusiness->id,
            'distributor_id' => $this->distributorB->id,
            'is_enabled' => true,
        ]);

        // And ours is now disabled.
        $this->assertDatabaseHas('business_distributors', [
            'business_id' => $this->business->id,
            'distributor_id' => $this->distributorB->id,
            'is_enabled' => false,
        ]);
    }

    public function test_re_enabling_a_disabled_distributor_works(): void
    {
        // Enable, disable, then enable again — exercises the upsert update path.
        $this->actingAs($this->owner)->post(route('settings.distributors.update'), [
            'distributor_ids' => [$this->distributorA->id],
        ]);
        $this->actingAs($this->owner)->post(route('settings.distributors.update'), [
            'distributor_ids' => [],
        ]);
        $this->actingAs($this->owner)->post(route('settings.distributors.update'), [
            'distributor_ids' => [$this->distributorA->id],
        ]);

        $this->assertDatabaseHas('business_distributors', [
            'business_id' => $this->business->id,
            'distributor_id' => $this->distributorA->id,
            'is_enabled' => true,
        ]);

        // Exactly one row for this pair — no duplicates from the upsert.
        $this->assertSame(1, BusinessDistributor::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $this->business->id)
            ->where('distributor_id', $this->distributorA->id)
            ->count());
    }

    public function test_staff_cannot_update_distributors(): void
    {
        $this->actingAs($this->staff)
            ->post(route('settings.distributors.update'), [
                'distributor_ids' => [$this->distributorA->id],
            ])
            ->assertForbidden();
    }

    public function test_unknown_distributor_id_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.distributors.update'), [
                'distributor_ids' => ['019e0000-0000-7000-8000-000000000000'],
            ])
            ->assertSessionHasErrors('distributor_ids.0');
    }
}
