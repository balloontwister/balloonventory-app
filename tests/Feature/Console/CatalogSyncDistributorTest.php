<?php

namespace Tests\Feature\Console;

use App\Models\Distributor;
use App\Models\DistributorCatalogGap;
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

    public function test_reports_block_and_does_not_mark_synced(): void
    {
        // max_retries:0 keeps the test from sleeping through real back-offs.
        $distributor = Distributor::factory()->bigcommerce()->create([
            'base_url' => 'https://bc-block.com',
            'config' => ['request_delay_ms' => 0, 'max_retries' => 0],
        ]);

        // Page 1 returns a product, page 2 is a 403 block — a truncated fetch.
        Http::fake([
            'bc-block.com/xmlsitemap.php?type=products&page=1' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://bc-block.com/some-balloon-10-count/</loc></url></urlset>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
            'bc-block.com/xmlsitemap.php?type=products&page=2' => Http::response('Forbidden', 403),
        ]);

        $this->artisan('catalog:sync-distributor', [
            'distributor' => $distributor->slug,
            '--execute' => true,
        ])
            ->expectsOutputToContain('Looks blocked')
            ->assertSuccessful();

        // Partial data is still written (the one product became a gap)...
        $this->assertGreaterThan(0, DistributorCatalogGap::where('distributor_id', $distributor->id)->count());
        // ...but the distributor is NOT marked as fully synced.
        $this->assertNull($distributor->fresh()->last_synced_at);
    }

    public function test_detects_cloudflare_challenge(): void
    {
        $distributor = Distributor::factory()->bigcommerce()->create([
            'base_url' => 'https://bc-cf.com',
            'config' => ['request_delay_ms' => 0, 'max_retries' => 0],
        ]);

        // A 200 whose body is a Cloudflare interstitial, not the sitemap XML.
        Http::fake([
            'bc-cf.com/*' => Http::response(
                '<!DOCTYPE html><html><head><title>Just a moment...</title></head><body>Enable JavaScript and cookies to continue</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $this->artisan('catalog:sync-distributor', [
            'distributor' => $distributor->slug,
        ])
            ->expectsOutputToContain('challenge')
            ->assertSuccessful();
    }

    public function test_shopify_json_empty_falls_back_to_sitemap(): void
    {
        $distributor = Distributor::factory()->shopify()->create([
            'base_url' => 'https://fake-shopify2.com',
            'config' => ['collection_handle' => 'all', 'has_json_api' => true, 'request_delay_ms' => 0],
        ]);

        Http::fake([
            // JSON API responds but with no products → triggers the fallback.
            'fake-shopify2.com/collections/*' => Http::response(['products' => []], 200),
            'fake-shopify2.com/sitemap.xml' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://fake-shopify2.com/products/red-balloon-12345</loc></url></urlset>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
        ]);

        $this->artisan('catalog:sync-distributor', [
            'distributor' => $distributor->slug,
        ])
            ->expectsOutputToContain('sitemap fallback')
            ->assertSuccessful();
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
