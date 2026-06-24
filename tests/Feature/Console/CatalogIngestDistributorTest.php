<?php

namespace Tests\Feature\Console;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogIngestDistributorTest extends TestCase
{
    use RefreshDatabase;

    public function test_shopify_ingest_stages_products_with_barcode_and_price(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->shopify()->create([
            'base_url' => 'https://test-shop.com',
            'config' => ['collection_handle' => 'all', 'has_json_api' => true, 'request_delay_ms' => 0],
        ]);

        Http::fake([
            'test-shop.com/collections/all/products.json?limit=250&page=1' => Http::response([
                'products' => [[
                    'handle' => 'red-balloon',
                    'title' => 'Red Balloon 100ct',
                    'variants' => [[
                        'sku' => '53012',
                        'barcode' => '030625530125',
                        'price' => '12.99',
                        'inventory_quantity' => 42,
                    ]],
                ]],
            ], 200),
            'test-shop.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
        ]);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame(1, DistributorProduct::count());

        $product = DistributorProduct::first();

        $this->assertSame($distributor->id, $product->distributor_id);
        $this->assertSame('53012', $product->external_id);
        $this->assertSame('53012', $product->raw_sku);
        $this->assertSame('030625530125', $product->upc);
        $this->assertSame('Red Balloon 100ct', $product->title);
        $this->assertSame('https://test-shop.com/products/red-balloon', $product->url);
        $this->assertSame(12.99, (float) $product->price);
        $this->assertTrue($product->in_stock);
    }

    public function test_shopify_ingest_is_idempotent(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->shopify()->create([
            'base_url' => 'https://test-idem.com',
            'config' => ['collection_handle' => 'all', 'has_json_api' => true, 'request_delay_ms' => 0],
        ]);

        Http::fake([
            'test-idem.com/collections/all/products.json?limit=250&page=1' => Http::response([
                'products' => [[
                    'handle' => 'blue-balloon',
                    'title' => 'Blue Balloon',
                    'variants' => [[
                        'sku' => 'SKU-1',
                        'barcode' => '012345678905',
                        'price' => '9.99',
                        'inventory_quantity' => 10,
                    ]],
                ]],
            ], 200),
            'test-idem.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
        ]);

        // First run
        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--execute' => true,
        ])->assertSuccessful();

        // Second run — upsert, not duplicate insert
        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame(1, DistributorProduct::count());
    }

    public function test_dry_run_does_not_write(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->shopify()->create([
            'base_url' => 'https://test-dry.com',
            'config' => ['collection_handle' => 'all', 'has_json_api' => true, 'request_delay_ms' => 0],
        ]);

        Http::fake([
            'test-dry.com/collections/all/products.json?limit=250&page=1' => Http::response([
                'products' => [[
                    'handle' => 'green-balloon',
                    'title' => 'Green Balloon',
                    'variants' => [[
                        'sku' => 'SKU-2',
                        'barcode' => '111111111116',
                        'price' => '5.00',
                        'inventory_quantity' => 20,
                    ]],
                ]],
            ], 200),
            'test-dry.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
        ]);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
        ])
            ->expectsOutputToContain('[DRY RUN]')
            ->assertSuccessful();

        $this->assertSame(0, DistributorProduct::count());
    }

    public function test_marks_synced_on_complete_pass(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->shopify()->create([
            'base_url' => 'https://test-complete.com',
            'config' => ['collection_handle' => 'all', 'has_json_api' => true, 'request_delay_ms' => 0],
        ]);

        Http::fake([
            'test-complete.com/collections/all/products.json?limit=250&page=1' => Http::response(['products' => []], 200),
            'test-complete.com/sitemap.xml' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
        ]);

        $this->assertNull($distributor->last_synced_at);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertNotNull($distributor->fresh()->last_synced_at);
    }

    public function test_bigcommerce_rejected_with_helpful_message(): void
    {
        $distributor = Distributor::factory()->bigcommerce()->create([
            'base_url' => 'https://some-bc.com',
        ]);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
        ])
            ->expectsOutputToContain('catalog:crawl-distributor')
            ->assertFailed();

        $this->assertSame(0, DistributorProduct::count());
    }

    public function test_invalid_slug_fails(): void
    {
        $this->artisan('catalog:ingest-distributor', [
            'slug' => 'nonexistent-distributor',
        ])->assertFailed();
    }

    public function test_sku_normalization_is_applied(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->shopify()->create([
            'base_url' => 'https://test-norm.com',
            'config' => [
                'collection_handle' => 'all',
                'has_json_api' => true,
                'request_delay_ms' => 0,
                'sku_strip_prefixes' => ['BL-'],
            ],
        ]);

        Http::fake([
            'test-norm.com/collections/all/products.json?limit=250&page=1' => Http::response([
                'products' => [[
                    'handle' => 'test-product',
                    'title' => 'Test Product',
                    'variants' => [[
                        'sku' => 'BL-53012',
                        'barcode' => '030625530125',
                        'price' => '10.00',
                        'inventory_quantity' => 5,
                    ]],
                ]],
            ], 200),
            'test-norm.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
        ]);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--execute' => true,
        ])->assertSuccessful();

        $product = DistributorProduct::first();

        $this->assertSame('BL-53012', $product->raw_sku);
        $this->assertSame('53012', $product->normalized_sku);
    }
}
