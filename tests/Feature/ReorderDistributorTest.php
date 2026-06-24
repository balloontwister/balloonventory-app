<?php

namespace Tests\Feature;

use App\Models\BalloonList;
use App\Models\Business;
use App\Models\BusinessDistributor;
use App\Models\Distributor;
use App\Models\DistributorSkuUrl;
use App\Models\ListItem;
use App\Models\Membership;
use App\Models\Sku;
use App\Models\User;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderDistributorTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Business $business;

    private BalloonList $favorites;

    private Sku $sku;

    private Distributor $distributor;

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

        $this->favorites = BalloonList::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'name' => 'Favorites',
            'is_business_favorites' => true,
            'created_by_user_id' => $this->owner->id,
        ]);

        $this->sku = Sku::factory()->create(['is_active' => true]);
        ListItem::create([
            'list_id' => $this->favorites->id,
            'sku_id' => $this->sku->id,
            'planned_quantity' => 5,
        ]);

        $this->distributor = Distributor::factory()->shopify()->create();

        BusinessDistributor::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $this->business->id,
            'distributor_id' => $this->distributor->id,
            'is_enabled' => true,
        ]);

        DistributorSkuUrl::create([
            'distributor_id' => $this->distributor->id,
            'sku_id' => $this->sku->id,
            'url' => 'https://example.com/products/balloon',
            'price' => 12.50,
            'currency' => 'USD',
            'in_stock' => true,
        ]);

        BusinessContext::set($this->business->id);
    }

    protected function tearDown(): void
    {
        BusinessContext::clear();
        parent::tearDown();
    }

    public function test_reorder_page_shows_distributor_urls(): void
    {
        $this->actingAs($this->owner)
            ->get(route('reorder.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('skus.0.distributor_urls.0.url', 'https://example.com/products/balloon')
                ->where('distributors.0.id', $this->distributor->id));
    }

    public function test_reorder_page_survives_a_soft_deleted_distributor(): void
    {
        // Soft delete leaves the distributor_sku_urls + business_distributors
        // rows orphaned. The page must not try to eager-load a now-null
        // distributor and crash.
        $this->distributor->delete();

        $this->actingAs($this->owner)
            ->get(route('reorder.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('skus.0.distributor_urls', [])
                ->where('distributors', []));
    }
}
