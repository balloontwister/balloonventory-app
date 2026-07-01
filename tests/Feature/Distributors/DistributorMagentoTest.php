<?php

namespace Tests\Feature\Distributors;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use App\Services\DistributorPlatforms\MagentoAdapter;
use App\Services\DistributorPlatforms\MagentoProductPageParser;
use App\Services\DistributorPlatforms\PlatformFactory;
use App\Services\DistributorProductIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DistributorMagentoTest extends TestCase
{
    use RefreshDatabase;

    /** A minimal Magento product page: JSON-LD Product + Offer, no barcode. */
    private function productPage(string $sku, string $brand, float $price, string $availability, string $name = 'A Balloon'): string
    {
        $json = json_encode([
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $name,
            'sku' => $sku,
            'brand' => ['@type' => 'Brand', 'name' => $brand],
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'USD',
                'price' => $price,
                'availability' => 'https://schema.org/'.$availability,
            ],
        ]);

        return '<html><head><script type="application/ld+json">'.$json.'</script></head><body></body></html>';
    }

    public function test_platform_factory_resolves_the_magento_adapter(): void
    {
        $distributor = Distributor::factory()->magento()->create();

        $this->assertInstanceOf(MagentoAdapter::class, app(PlatformFactory::class)->make($distributor));
    }

    public function test_parser_reads_the_json_ld_product(): void
    {
        $parser = new MagentoProductPageParser;

        $parsed = $parser->parse($this->productPage('57102B', 'BETALLIC INC', 5.1, 'InStock', '260B Sempertex Fashion White'));

        $this->assertSame('57102B', $parsed['raw_sku']);
        $this->assertSame('260B Sempertex Fashion White', $parsed['title']);
        $this->assertSame('BETALLIC INC', $parsed['brand']);
        $this->assertSame(5.1, $parsed['price']);
        $this->assertNull($parsed['upc']); // Magento exposes no barcode
    }

    public function test_parser_returns_null_when_there_is_no_product(): void
    {
        $parser = new MagentoProductPageParser;

        $this->assertNull($parser->parse('<html><body>404 Not Found</body></html>'));
        $this->assertNull($parser->parse('<script type="application/ld+json">{"@type":"WebPage"}</script>'));
    }

    public function test_adapter_harvests_product_links_across_pages_then_stops_at_an_empty_grid(): void
    {
        // Page 1 lists two products (one href-before-class, one class-before-href,
        // plus a duplicate to prove per-page dedupe); page 2 is an empty grid.
        $page1 = <<<'HTML'
        <ol class="products list items">
          <li><a class="product-item-link" href="https://rainbowballoons.com/260b-sempertex-fashion-white-57102b.html">White</a></li>
          <li><a href="https://rainbowballoons.com/11c-tuf-tex-baby-blue-10021.html" class="product-item-link">Baby Blue</a></li>
          <li><a class="product-item-link" href="https://rainbowballoons.com/260b-sempertex-fashion-white-57102b.html">White (dup)</a></li>
        </ol>
        HTML;

        Http::fake(function ($request) use ($page1) {
            return str_contains($request->url(), 'p=2')
                ? Http::response('<ol class="products list items"></ol>', 200)
                : Http::response($page1, 200);
        });

        $distributor = Distributor::factory()->magento()->create([
            'base_url' => 'https://rainbowballoons.com',
            'config' => ['category_urls' => ['https://rainbowballoons.com/latex/solid-betallatex-latex.html']],
        ]);

        $adapter = app(MagentoAdapter::class);
        $products = $adapter->fetchProducts($distributor);

        $this->assertCount(2, $products); // duplicate collapsed
        $this->assertEqualsCanonicalizing(
            [
                'https://rainbowballoons.com/260b-sempertex-fashion-white-57102b.html',
                'https://rainbowballoons.com/11c-tuf-tex-baby-blue-10021.html',
            ],
            array_column($products, 'url'),
        );
        $this->assertFalse($adapter->lastFetchReport()->stoppedEarly);
        // A clean sweep of every category page → no barcode/price yet (page parse
        // fills those); each entry is just a URL to crawl.
        $this->assertNull($products[0]['barcode']);
    }

    public function test_crawl_stages_a_sempertex_product_with_a_stripped_sku_and_canonical_brand(): void
    {
        $url = 'https://rainbowballoons.com/260b-sempertex-fashion-white-57102b.html';
        Http::fake([$url => Http::response($this->productPage('57102B', 'BETALLIC INC', 5.1, 'InStock'))]);

        $distributor = Distributor::factory()->magento()->create([
            'config' => [
                'sku_strip_suffixes' => ['B'],
                'attribute_aliases' => ['brand' => ['BETALLIC INC' => 'Sempertex', 'PIONEER BALLOON' => 'Qualatex']],
            ],
        ]);

        $parsed = app(DistributorProductIngestor::class)
            ->crawlMagentoPage($distributor, $url, 'ext-1', $distributor->config, execute: true);

        $this->assertSame('57102B', $parsed['raw_sku']);
        $this->assertSame('57102', $parsed['normalized_sku']); // Betallic "B" stripped → meets our warehouse_sku
        // A successful JSON-LD read is the health signal (no attribute table to grade)
        // — otherwise the crawl command's drift guard aborts the whole run.
        $this->assertTrue($parsed['extraction']['ok']);

        $staged = DistributorProduct::where('distributor_id', $distributor->id)->sole();
        $this->assertSame('57102B', $staged->raw_sku);
        $this->assertSame('57102', $staged->normalized_sku);
        $this->assertNull($staged->upc);
        $this->assertSame(['Brand' => ['Sempertex']], $staged->raw_data['attributes']); // BETALLIC INC → Sempertex
        $this->assertEquals(5.1, $staged->price);
        $this->assertTrue((bool) $staged->in_stock);
    }

    public function test_crawl_stages_a_qualatex_product_with_a_bare_sku_and_out_of_stock(): void
    {
        $url = 'https://rainbowballoons.com/11c-caribbean-blue-50322.html';
        Http::fake([$url => Http::response($this->productPage('50322', 'PIONEER BALLOON', 19.85, 'OutOfStock'))]);

        $distributor = Distributor::factory()->magento()->create([
            'config' => [
                'sku_strip_suffixes' => ['B'],
                'attribute_aliases' => ['brand' => ['PIONEER BALLOON' => 'Qualatex']],
            ],
        ]);

        $parsed = app(DistributorProductIngestor::class)
            ->crawlMagentoPage($distributor, $url, 'ext-2', $distributor->config, execute: true);

        $this->assertSame('50322', $parsed['raw_sku']);
        $this->assertSame('50322', $parsed['normalized_sku']); // no "B" suffix → unchanged

        $staged = DistributorProduct::where('distributor_id', $distributor->id)->sole();
        $this->assertSame(['Brand' => ['Qualatex']], $staged->raw_data['attributes']);
        $this->assertFalse((bool) $staged->in_stock);
    }

    public function test_crawl_returns_null_on_a_failed_fetch(): void
    {
        $url = 'https://rainbowballoons.com/gone-99999.html';
        Http::fake([$url => Http::response('nope', 503)]);

        $distributor = Distributor::factory()->magento()->create();

        $parsed = app(DistributorProductIngestor::class)
            ->crawlMagentoPage($distributor, $url, 'ext-3', $distributor->config ?? [], execute: true);

        $this->assertNull($parsed);
        $this->assertSame(0, DistributorProduct::count());
    }
}
