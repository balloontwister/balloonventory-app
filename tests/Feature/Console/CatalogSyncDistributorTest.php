<?php

namespace Tests\Feature\Console;

use App\Models\Distributor;
use App\Models\DistributorSkuUrl;
use App\Models\Sku;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ColorFamilySeeder;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\PackagingTypeSeeder;
use Database\Seeders\ShapeSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\TextureFamilySeeder;
use Database\Seeders\TextureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogSyncDistributorTest extends TestCase
{
    use RefreshDatabase;

    private Distributor $distributor;

    private Sku $sku;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BrandSeeder::class);
        $this->seed(MaterialSeeder::class);
        $this->seed(ShapeSeeder::class);
        $this->seed(SizeSeeder::class);
        $this->seed(PackagingTypeSeeder::class);
        $this->seed(TextureFamilySeeder::class);
        $this->seed(TextureSeeder::class);
        $this->seed(ColorFamilySeeder::class);

        // Never let the suite reach out to the network. Individual tests fake
        // the responses they need; anything else throws.
        Http::preventStrayRequests();

        $this->distributor = Distributor::factory()->shopify()->create([
            'base_url' => 'https://test-store.com',
            'config' => ['collection_handle' => 'all', 'has_json_api' => false],
        ]);

        $this->sku = Sku::factory()->create([
            'warehouse_sku' => 'MATCH_ME',
            'upc' => '012345678905',
            'is_active' => true,
        ]);
    }

    public function test_dry_run_reports_without_writing(): void
    {
        // Empty sitemap → zero products, but the command still reports cleanly.
        Http::fake([
            'test-store.com/*' => Http::response('<urlset></urlset>', 200, ['Content-Type' => 'application/xml']),
        ]);

        $this->artisan('catalog:sync-distributor', [
            'distributor' => $this->distributor->slug,
        ])
            ->expectsOutputToContain('[DRY RUN]')
            ->assertSuccessful();

        $this->assertSame(0, DistributorSkuUrl::count());
    }

    public function test_execute_writes_matched_url(): void
    {
        $distributor = Distributor::factory()->shopify()->create([
            'base_url' => 'https://fake-shopify.com',
            'config' => ['collection_handle' => 'all', 'has_json_api' => true],
        ]);

        // Page 1 carries a product whose variant barcode matches our SKU's UPC;
        // page 2 is empty so pagination stops.
        Http::fake([
            'fake-shopify.com/collections/all/products.json?limit=250&page=1' => Http::response([
                'products' => [[
                    'handle' => 'red-balloon',
                    'title' => 'Red Balloon',
                    'variants' => [[
                        'sku' => 'MATCH_ME',
                        'barcode' => '012345678905',
                        'price' => '9.99',
                        'inventory_quantity' => 4,
                    ]],
                ]],
            ], 200),
            'fake-shopify.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
        ]);

        $this->artisan('catalog:sync-distributor', [
            'distributor' => $distributor->slug,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('distributor_sku_urls', [
            'distributor_id' => $distributor->id,
            'sku_id' => $this->sku->id,
            'url' => 'https://fake-shopify.com/products/red-balloon',
        ]);

        // Re-running is idempotent — upsert, not duplicate insert.
        $this->artisan('catalog:sync-distributor', [
            'distributor' => $distributor->slug,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame(1, DistributorSkuUrl::where('distributor_id', $distributor->id)->count());
    }

    public function test_invalid_distributor_slug(): void
    {
        $this->artisan('catalog:sync-distributor', [
            'distributor' => 'nonexistent',
        ])->assertFailed();
    }

    public function test_no_active_distributors_shows_warning(): void
    {
        Distributor::query()->update(['is_active' => false]);

        $this->artisan('catalog:sync-distributor')
            ->expectsOutputToContain('No active distributors found.')
            ->assertSuccessful();
    }
}
