<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Business;
use App\Models\BusinessDistributor;
use App\Models\Distributor;
use App\Models\DistributorCatalogGap;
use App\Models\DistributorSkuUrl;
use App\Models\Sku;
use App\Models\User;
use Database\Seeders\BrandSeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->seed(PermissionSeeder::class);
        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);

        $this->admin = User::factory()->create([
            'email_verified_at' => now(),
            'admin_level' => 'super_admin',
        ]);
    }

    public function test_index_lists_distributors(): void
    {
        Distributor::factory()->count(2)->shopify()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.distributors.index'));

        $response->assertOk();
    }

    public function test_create_page_loads(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.distributors.create'));

        $response->assertOk();
    }

    public function test_store_creates_distributor(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.distributors.store'), [
                'name' => 'Test Distributor',
                'slug' => 'test-distributor',
                'platform_type' => 'shopify',
                'base_url' => 'https://test-distributor.com',
            ]);

        $response->assertRedirect(route('admin.distributors.index'));
        $this->assertDatabaseHas('distributors', ['slug' => 'test-distributor']);
    }

    public function test_show_page_loads(): void
    {
        $distributor = Distributor::factory()->shopify()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.distributors.show', $distributor));

        $response->assertOk();
    }

    public function test_edit_page_loads(): void
    {
        $distributor = Distributor::factory()->shopify()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.distributors.edit', $distributor));

        $response->assertOk();
    }

    public function test_update_distributor(): void
    {
        $distributor = Distributor::factory()->shopify()->create();

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.distributors.update', $distributor), [
                'name' => 'Updated Name',
                'slug' => $distributor->slug,
                'platform_type' => 'bigcommerce',
                'base_url' => 'https://updated.com',
            ]);

        $response->assertRedirect(route('admin.distributors.index'));
        $this->assertDatabaseHas('distributors', ['id' => $distributor->id, 'name' => 'Updated Name']);
    }

    public function test_destroy_soft_deletes(): void
    {
        $distributor = Distributor::factory()->shopify()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.distributors.destroy', $distributor));

        $response->assertRedirect(route('admin.distributors.index'));
        $this->assertSoftDeleted('distributors', ['id' => $distributor->id]);
    }

    public function test_destroy_cleans_up_child_rows(): void
    {
        $distributor = Distributor::factory()->shopify()->create();
        $sku = Sku::factory()->create();
        $business = Business::factory()->create();

        DistributorSkuUrl::create([
            'distributor_id' => $distributor->id,
            'sku_id' => $sku->id,
            'url' => 'https://example.com/products/one',
        ]);
        BusinessDistributor::create([
            'business_id' => $business->id,
            'distributor_id' => $distributor->id,
            'is_enabled' => true,
        ]);
        DistributorCatalogGap::factory()->create(['distributor_id' => $distributor->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.distributors.destroy', $distributor))
            ->assertRedirect(route('admin.distributors.index'));

        $this->assertSoftDeleted('distributors', ['id' => $distributor->id]);
        $this->assertDatabaseMissing('distributor_sku_urls', ['distributor_id' => $distributor->id]);
        $this->assertDatabaseMissing('business_distributors', ['distributor_id' => $distributor->id]);
        $this->assertDatabaseMissing('distributor_catalog_gaps', ['distributor_id' => $distributor->id]);
    }

    public function test_slug_can_be_reused_after_soft_delete(): void
    {
        $distributor = Distributor::factory()->shopify()->create([
            'name' => 'Reusable',
            'slug' => 'reusable',
        ]);
        $distributor->delete();

        $this->actingAs($this->admin)
            ->post(route('admin.distributors.store'), [
                'name' => 'Reusable',
                'slug' => 'reusable',
                'platform_type' => 'shopify',
                'base_url' => 'https://reusable.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.distributors.index'));

        // One live row plus the trashed one — the index no longer collides.
        $this->assertSame(1, Distributor::where('slug', 'reusable')->count());
        $this->assertSame(2, Distributor::withTrashed()->where('slug', 'reusable')->count());
    }
}
