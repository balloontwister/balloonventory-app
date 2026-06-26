<?php

namespace Tests\Feature\Console;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogCrawlDistributorTest extends TestCase
{
    use RefreshDatabase;

    private function bigCommerceProductHtml(array $overrides = []): string
    {
        $sku = $overrides['sku'] ?? '53012';
        $title = $overrides['title'] ?? 'Red Fashion 100 count';
        $brand = $overrides['brand'] ?? 'Sempertex';
        $price = $overrides['price'] ?? null;
        $stock = $overrides['stock'] ?? 53;
        $inStock = $overrides['in_stock'] ?? true;

        $bcDataPrice = $price !== null
            ? '"price":{"without_tax":{"value":'.$price.',"currency":"USD"}}'
            : '"price":{"price_range":[],"retail_price_range":[]}';
        $bcDataStock = $stock !== null ? '"stock":'.$stock : '"stock":null';
        $bcDataInStock = $inStock ? 'true' : 'false';

        return <<<HTML
        <script type="application/ld+json">{"@type":"BreadcrumbList","itemListElement":[]}</script>
        <script type="application/ld+json">{"@context":"https://schema.org","@type":"Product","name":"{$title}","sku":"{$sku}","brand":"{$brand}","offers":{"@type":"Offer","price":"","availability":"https://schema.org/OutOfStock"}}</script>
        <script type="text/javascript">
        var BCData = {"product_attributes":{"sku":"{$sku}","upc":null,"mpn":null,"gtin":null,{$bcDataPrice},{$bcDataStock},"instock":{$bcDataInStock}}};
        </script>
        HTML;
    }

    public function test_crawler_stages_product_from_page(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->bigcommerce()->create([
            'base_url' => 'https://test-bc.com',
            'config' => ['request_delay_ms' => 0],
        ]);

        // Sitemap returns one product URL; the product page returns parseable HTML.
        Http::fake([
            'test-bc.com/xmlsitemap.php?type=products&page=1' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://test-bc.com/11s-red-fashion-100-count/</loc></url></urlset>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
            'test-bc.com/xmlsitemap.php?type=products&page=2' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
            'test-bc.com/11s-red-fashion-100-count/' => Http::response($this->bigCommerceProductHtml(), 200),
        ]);

        $this->artisan('catalog:crawl-distributor', [
            'slug' => $distributor->slug,
            '--execute' => true,
            '--limit' => 10,
        ])->assertSuccessful();

        $this->assertSame(1, DistributorProduct::count());

        $product = DistributorProduct::first();

        $this->assertSame($distributor->id, $product->distributor_id);
        $this->assertSame('11s-red-fashion-100-count', $product->external_id);
        $this->assertSame('53012', $product->raw_sku);
        $this->assertSame('Red Fashion 100 count', $product->title);
        $this->assertSame(53, $product->stock);
        $this->assertTrue($product->in_stock);
    }

    public function test_crawler_skips_previously_fetched_products(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->bigcommerce()->create([
            'base_url' => 'https://test-resume.com',
            'config' => ['request_delay_ms' => 0],
        ]);

        // Pre-seed a product that was already fetched recently.
        DistributorProduct::factory()->forDistributor($distributor)->create([
            'external_id' => 'already-fetched',
            'fetched_at' => now()->subHour(), // 1 hour ago — within the 24h window
        ]);

        Http::fake([
            'test-resume.com/xmlsitemap.php?type=products&page=1' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://test-resume.com/already-fetched/</loc></url></urlset>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
            'test-resume.com/xmlsitemap.php?type=products&page=2' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
        ]);

        $this->artisan('catalog:crawl-distributor', [
            'slug' => $distributor->slug,
            '--execute' => true,
            '--limit' => 10,
        ])
            ->expectsOutputToContain('Skipped (fresh): 1')
            ->assertSuccessful();

        // Still only the pre-seeded product; no new ones created.
        $this->assertSame(1, DistributorProduct::count());
    }

    public function test_skips_unchanged_product_via_sitemap_lastmod(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->bigcommerce()->create([
            'base_url' => 'https://test-lastmod.com',
            'config' => ['request_delay_ms' => 0],
        ]);

        // Fetched 2 days ago; the sitemap says it last changed 10 days ago — so it
        // hasn't changed since we fetched it. The page is NOT faked: a fetch attempt
        // would throw (preventStrayRequests), proving we skipped it.
        DistributorProduct::factory()->forDistributor($distributor)->create([
            'external_id' => 'unchanged',
            'title' => 'Original title',
            'fetched_at' => now()->subDays(2),
        ]);

        Http::fake([
            'test-lastmod.com/xmlsitemap.php?type=products&page=1' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url>'
                .'<loc>https://test-lastmod.com/unchanged/</loc>'
                .'<lastmod>'.now()->subDays(10)->toAtomString().'</lastmod>'
                .'</url></urlset>',
                200, ['Content-Type' => 'application/xml'],
            ),
            'test-lastmod.com/xmlsitemap.php?type=products&page=2' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
                200, ['Content-Type' => 'application/xml'],
            ),
        ]);

        $this->artisan('catalog:crawl-distributor', ['slug' => $distributor->slug, '--execute' => true, '--limit' => 10])
            ->expectsOutputToContain('Skipped (fresh): 1')
            ->assertSuccessful();

        $this->assertSame('Original title', DistributorProduct::first()->title);
    }

    public function test_refetches_updated_product_via_sitemap_lastmod(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->bigcommerce()->create([
            'base_url' => 'https://test-updated.com',
            'config' => ['request_delay_ms' => 0],
        ]);

        // Fetched 10 days ago; the sitemap says it changed 2 days ago → re-fetch.
        DistributorProduct::factory()->forDistributor($distributor)->create([
            'external_id' => 'changed',
            'title' => 'Stale title',
            'fetched_at' => now()->subDays(10),
        ]);

        Http::fake([
            'test-updated.com/xmlsitemap.php?type=products&page=1' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url>'
                .'<loc>https://test-updated.com/changed/</loc>'
                .'<lastmod>'.now()->subDays(2)->toAtomString().'</lastmod>'
                .'</url></urlset>',
                200, ['Content-Type' => 'application/xml'],
            ),
            'test-updated.com/xmlsitemap.php?type=products&page=2' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
                200, ['Content-Type' => 'application/xml'],
            ),
            'test-updated.com/changed/' => Http::response($this->bigCommerceProductHtml(['title' => 'Fresh title']), 200),
        ]);

        $this->artisan('catalog:crawl-distributor', ['slug' => $distributor->slug, '--execute' => true, '--limit' => 10])
            ->assertSuccessful();

        $this->assertSame('Fresh title', DistributorProduct::where('external_id', 'changed')->value('title'));
    }

    public function test_retires_products_that_left_the_sitemap(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->bigcommerce()->create([
            'base_url' => 'https://test-retire.com',
            'config' => ['request_delay_ms' => 0],
        ]);

        // 'gone' is staged but no longer listed in the sitemap.
        $gone = DistributorProduct::factory()->forDistributor($distributor)->create([
            'external_id' => 'gone', 'fetched_at' => now()->subDays(3),
        ]);

        Http::fake([
            'test-retire.com/xmlsitemap.php?type=products&page=1' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://test-retire.com/present/</loc></url></urlset>',
                200, ['Content-Type' => 'application/xml'],
            ),
            'test-retire.com/xmlsitemap.php?type=products&page=2' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
                200, ['Content-Type' => 'application/xml'],
            ),
            'test-retire.com/present/' => Http::response($this->bigCommerceProductHtml(['sku' => 'PRES']), 200),
        ]);

        $this->artisan('catalog:crawl-distributor', ['slug' => $distributor->slug, '--execute' => true, '--limit' => 10])
            ->expectsOutputToContain('Retired (gone):  1')
            ->assertSuccessful();

        $this->assertNotNull($gone->fresh()->removed_at, 'Missing product should be retired');
        $present = DistributorProduct::where('external_id', 'present')->first();
        $this->assertNull($present->removed_at);
        $this->assertNotNull($present->last_seen_at);
    }

    public function test_reappeared_product_is_un_retired(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->bigcommerce()->create([
            'base_url' => 'https://test-reappear.com',
            'config' => ['request_delay_ms' => 0],
        ]);

        // Previously retired, but back in the sitemap (unchanged → page not fetched).
        $back = DistributorProduct::factory()->forDistributor($distributor)->create([
            'external_id' => 'back', 'fetched_at' => now()->subDays(2), 'removed_at' => now()->subDay(),
        ]);

        Http::fake([
            'test-reappear.com/xmlsitemap.php?type=products&page=1' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url>'
                .'<loc>https://test-reappear.com/back/</loc><lastmod>'.now()->subDays(20)->toAtomString().'</lastmod>'
                .'</url></urlset>',
                200, ['Content-Type' => 'application/xml'],
            ),
            'test-reappear.com/xmlsitemap.php?type=products&page=2' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
                200, ['Content-Type' => 'application/xml'],
            ),
        ]);

        $this->artisan('catalog:crawl-distributor', ['slug' => $distributor->slug, '--execute' => true, '--limit' => 10])
            ->assertSuccessful();

        $this->assertNull($back->fresh()->removed_at, 'Reappeared product should be un-retired');
    }

    public function test_does_not_retire_when_the_sitemap_is_incomplete(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->bigcommerce()->create([
            'base_url' => 'https://test-incomplete.com',
            'config' => ['request_delay_ms' => 0, 'max_retries' => 0],
        ]);

        $gone = DistributorProduct::factory()->forDistributor($distributor)->create([
            'external_id' => 'gone', 'fetched_at' => now()->subDays(3),
        ]);

        // Page 1 lists one product; page 2 is blocked (403) → sitemap truncated, so
        // we must NOT retire 'gone' even though it isn't listed.
        Http::fake([
            'test-incomplete.com/xmlsitemap.php?type=products&page=1' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://test-incomplete.com/present/</loc></url></urlset>',
                200, ['Content-Type' => 'application/xml'],
            ),
            'test-incomplete.com/xmlsitemap.php?type=products&page=2' => Http::response('blocked', 403),
            'test-incomplete.com/present/' => Http::response($this->bigCommerceProductHtml(['sku' => 'PRES']), 200),
        ]);

        $this->artisan('catalog:crawl-distributor', ['slug' => $distributor->slug, '--execute' => true, '--limit' => 10])
            ->assertSuccessful();

        $this->assertNull($gone->fresh()->removed_at, 'Must not retire on an incomplete sitemap');
    }

    public function test_crawler_respects_limit(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->bigcommerce()->create([
            'base_url' => 'https://test-limit.com',
            'config' => ['request_delay_ms' => 0],
        ]);

        // Sitemap has 3 products, limit is 2.
        Http::fake([
            'test-limit.com/xmlsitemap.php?type=products&page=1' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                .'<url><loc>https://test-limit.com/product-a/</loc></url>'
                .'<url><loc>https://test-limit.com/product-b/</loc></url>'
                .'<url><loc>https://test-limit.com/product-c/</loc></url>'
                .'</urlset>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
            'test-limit.com/xmlsitemap.php?type=products&page=2' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
            'test-limit.com/product-a/' => Http::response($this->bigCommerceProductHtml(['sku' => 'AAA']), 200),
            'test-limit.com/product-b/' => Http::response($this->bigCommerceProductHtml(['sku' => 'BBB']), 200),
            'test-limit.com/product-c/' => Http::response($this->bigCommerceProductHtml(['sku' => 'CCC']), 200),
        ]);

        $this->artisan('catalog:crawl-distributor', [
            'slug' => $distributor->slug,
            '--execute' => true,
            '--limit' => 2,
        ])->assertSuccessful();

        $this->assertSame(2, DistributorProduct::count());
    }

    public function test_dry_run_counts_without_writing(): void
    {
        Http::preventStrayRequests();

        $distributor = Distributor::factory()->bigcommerce()->create([
            'base_url' => 'https://test-bc-dry.com',
            'config' => ['request_delay_ms' => 0],
        ]);

        Http::fake([
            'test-bc-dry.com/xmlsitemap.php?type=products&page=1' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://test-bc-dry.com/some-product/</loc></url></urlset>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
            'test-bc-dry.com/xmlsitemap.php?type=products&page=2' => Http::response(
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
        ]);

        $this->artisan('catalog:crawl-distributor', [
            'slug' => $distributor->slug,
            '--limit' => 10,
        ])
            ->expectsOutputToContain('[DRY RUN]')
            ->assertSuccessful();

        $this->assertSame(0, DistributorProduct::count());
    }

    public function test_rejects_shopify_distributor(): void
    {
        $distributor = Distributor::factory()->shopify()->create();

        $this->artisan('catalog:crawl-distributor', [
            'slug' => $distributor->slug,
        ])
            ->expectsOutputToContain('catalog:ingest-distributor')
            ->assertFailed();
    }

    public function test_invalid_slug_fails(): void
    {
        $this->artisan('catalog:crawl-distributor', [
            'slug' => 'nonexistent',
        ])->assertFailed();
    }
}
