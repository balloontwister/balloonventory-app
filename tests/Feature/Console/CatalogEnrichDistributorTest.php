<?php

namespace Tests\Feature\Console;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use App\Models\Sku;
use App\Services\Distributors\DistributorProductClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogEnrichDistributorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $configOverride
     */
    private function bargainBalloons(array $configOverride = []): Distributor
    {
        return Distributor::factory()->shopify()->create([
            'base_url' => 'https://bb-test.com',
            'config' => array_merge([
                'collection_handle' => 'all',
                'has_json_api' => true,
                'request_delay_ms' => 0,
                'extraction' => [
                    'attribute_list' => ['section_marker' => 'Additional Product Details'],
                    'required_labels' => ['Manufacturer Color', 'Latex Finish', 'Package Count'],
                    'min_rows' => 4,
                    'label_map' => [
                        'size' => 'Size (inches)',
                        'color' => 'Manufacturer Color',
                        'texture' => 'Latex Finish',
                        'count' => 'Package Count',
                        'packaging' => 'Packaging Type',
                    ],
                ],
                'sku_strip_prefixes' => ['BL-'],
                'sku_strip_suffixes' => ['-B'],
            ], $configOverride),
        ]);
    }

    /** The "Additional Product Details" accordion of a real BB latex page. */
    private function pageHtml(): string
    {
        return <<<'HTML'
        <h1>11 inch Latex Balloons 100 Per Bag Fashion Yellow</h1>
        <details><summary>Additional Product Details</summary>
        <div class="cc-accordion-item__content">
          <ul>
            <li><span>SKU: </span>BL-53005</li>
            <li><span>UPC: </span>030625530057</li>
            <li><span>Size (inches): </span>11.0</li>
            <li><span>Print: </span>Solid Color</li>
            <li><span>Manufacturer Color: </span>Yellow</li>
            <li><span>Packaging Type: </span>Retail Packaged</li>
            <li><span>Package Count: </span>100</li>
            <li><span>Latex Finish: </span>Fashion</li>
          </ul>
        </div></details>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $variant
     */
    private function fakeShopify(string $base, array $product, ?string $pageHtml = null): void
    {
        Http::fake([
            "{$base}/collections/all/products.json?limit=250&page=1" => Http::response(['products' => [$product]], 200),
            "{$base}/collections/all/products.json?limit=250&page=2" => Http::response(['products' => []], 200),
            "{$base}/products/{$product['handle']}" => Http::response($pageHtml ?? $this->pageHtml(), 200),
        ]);
    }

    private function fashionYellowProduct(): array
    {
        return [
            'handle' => 'fashion-yellow',
            'title' => '11 inch Latex Balloons 100 Per Bag Fashion Yellow',
            'vendor' => 'Sempertex',
            'tags' => ['Latex Balloons', '11 inch'],
            'updated_at' => '2026-06-20T00:00:00Z',
            'variants' => [[
                'sku' => 'BL-53005',
                'barcode' => '030625530057',
                'price' => '12.99',
                'inventory_quantity' => 10,
            ]],
        ];
    }

    public function test_enrich_stages_a_net_new_latex_product_with_brand_shape_and_attributes(): void
    {
        Http::preventStrayRequests();

        $distributor = $this->bargainBalloons();
        $this->fakeShopify('https://bb-test.com', $this->fashionYellowProduct());

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame(1, DistributorProduct::count());

        $product = DistributorProduct::first();

        $this->assertSame('BL-53005', $product->raw_sku);
        $this->assertSame('53005', $product->normalized_sku);
        $this->assertSame('030625530057', $product->upc);
        $this->assertSame(DistributorProductClassifier::SOLID_LATEX, $product->product_type);

        // Brand injected from the JSON vendor; shape synthesised (default Round).
        $this->assertSame(['Sempertex'], $product->raw_data['attributes']['Brand']);
        $this->assertSame(['Round'], $product->raw_data['attributes']['Balloon Type / Shape']);
        // Page-read attributes are present.
        $this->assertSame(['Yellow'], $product->raw_data['attributes']['Manufacturer Color']);
        $this->assertSame(['Fashion'], $product->raw_data['attributes']['Latex Finish']);
    }

    public function test_enrich_reads_upc_from_the_page_when_products_json_lacks_a_barcode(): void
    {
        Http::preventStrayRequests();

        // BargainBalloons' collection products.json omits the barcode entirely; the
        // UPC lives only in the product page's spec accordion. Enrichment must read
        // it there, or the product can never cluster (clustering is UPC-gated).
        $distributor = $this->bargainBalloons();
        $product = $this->fashionYellowProduct();
        unset($product['variants'][0]['barcode']);
        $this->fakeShopify('https://bb-test.com', $product);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame('030625530057', DistributorProduct::first()->upc);
    }

    public function test_enrich_skips_a_product_already_in_our_catalog(): void
    {
        Http::preventStrayRequests();

        // We already carry this UPC → reconciliation handles it; no page fetch.
        Sku::factory()->create(['upc' => '030625530057']);

        $distributor = $this->bargainBalloons();
        Http::fake([
            'https://bb-test.com/collections/all/products.json?limit=250&page=1' => Http::response(
                ['products' => [$this->fashionYellowProduct()]], 200
            ),
            'https://bb-test.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
        ]);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        // No page was fetched and nothing staged by the enrich pass.
        $this->assertSame(0, DistributorProduct::count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/products/fashion-yellow'));
    }

    public function test_enrich_skips_a_product_another_distributor_already_staged(): void
    {
        Http::preventStrayRequests();

        // Larocks (another distributor) already staged this UPC → it reconciles via
        // the shared barcode at cluster time; BargainBalloons needs no page fetch.
        $larocks = Distributor::factory()->bigcommerce()->create();
        DistributorProduct::factory()->create([
            'distributor_id' => $larocks->id,
            'upc' => '030625530057',
        ]);

        $distributor = $this->bargainBalloons();
        Http::fake([
            'https://bb-test.com/collections/all/products.json?limit=250&page=1' => Http::response(
                ['products' => [$this->fashionYellowProduct()]], 200
            ),
            'https://bb-test.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
        ]);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        // Only Larocks' staged row exists; BargainBalloons enriched nothing.
        $this->assertSame(0, DistributorProduct::where('distributor_id', $distributor->id)->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/products/fashion-yellow'));
    }

    public function test_enrich_skips_non_latex_products(): void
    {
        Http::preventStrayRequests();

        $distributor = $this->bargainBalloons();
        Http::fake([
            'https://bb-test.com/collections/all/products.json?limit=250&page=1' => Http::response([
                'products' => [[
                    'handle' => 'gold-star-foil',
                    'title' => '18 inch Gold Star Foil Balloon',
                    'vendor' => 'Betallic',
                    'tags' => ['Foil Balloons'],
                    'variants' => [[
                        'sku' => 'BF-1234',
                        'barcode' => '012345678905',
                        'price' => '3.99',
                        'inventory_quantity' => 5,
                    ]],
                ]],
            ], 200),
            'https://bb-test.com/collections/all/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
        ]);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame(0, DistributorProduct::count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/products/gold-star-foil'));
    }

    public function test_dry_run_does_not_write(): void
    {
        Http::preventStrayRequests();

        $distributor = $this->bargainBalloons();
        $this->fakeShopify('https://bb-test.com', $this->fashionYellowProduct());

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
        ])
            ->expectsOutputToContain('[DRY RUN]')
            ->assertSuccessful();

        $this->assertSame(0, DistributorProduct::count());
    }

    public function test_enrich_is_idempotent_and_skips_already_enriched(): void
    {
        Http::preventStrayRequests();

        $distributor = $this->bargainBalloons();
        $this->fakeShopify('https://bb-test.com', $this->fashionYellowProduct());

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        // Second run: the product is already enriched and unchanged → skipped.
        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame(1, DistributorProduct::count());

        // The product page itself was fetched exactly once across both runs.
        $pageFetches = collect(Http::recorded())
            ->filter(fn (array $pair) => str_contains($pair[0]->url(), '/products/fashion-yellow'))
            ->count();
        $this->assertSame(1, $pageFetches);
    }

    public function test_enrich_reads_in_stock_from_page_json_ld_when_feed_lacks_stock(): void
    {
        Http::preventStrayRequests();

        // Real BargainBalloons shape: the feed variant carries no inventory field,
        // so stock is unknown from the JSON — but the page's JSON-LD Offer does.
        $distributor = $this->bargainBalloons();
        $product = $this->fashionYellowProduct();
        unset($product['variants'][0]['inventory_quantity']);

        $this->fakeShopify('https://bb-test.com', $product, $this->pageHtmlWithAvailability('http://schema.org/InStock'));

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertTrue(DistributorProduct::first()->in_stock);
    }

    public function test_enrich_reads_out_of_stock_from_page_json_ld(): void
    {
        Http::preventStrayRequests();

        $distributor = $this->bargainBalloons();
        $product = $this->fashionYellowProduct();
        unset($product['variants'][0]['inventory_quantity']);

        $this->fakeShopify('https://bb-test.com', $product, $this->pageHtmlWithAvailability('http://schema.org/OutOfStock'));

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertFalse(DistributorProduct::first()->in_stock);
    }

    private function pageHtmlWithAvailability(string $availability): string
    {
        $json = json_encode([
            '@type' => 'Product',
            'offers' => ['@type' => 'Offer', 'availability' => $availability],
        ]);

        return $this->pageHtml()."\n<script type=\"application/ld+json\">{$json}</script>";
    }

    // ── Joker Party Supply: product-JSON enrichment ─────────────────────
    // The attribute table is in the product's body_html and the barcode is on the
    // variant — both in the light per-product .json, so no HTML page is fetched.

    private function joker(): Distributor
    {
        return Distributor::factory()->shopify()->create([
            'base_url' => 'https://joker-test.com',
            'config' => [
                'collection_handle' => 'latex',
                'has_json_api' => true,
                'request_delay_ms' => 0,
                'enrich_from_product_json' => true,
                'extraction' => [
                    'attribute_rows' => ['section_marker' => 'Product Information'],
                    'required_labels' => ['Brand', 'Size', 'Material'],
                    'min_rows' => 4,
                    'label_map' => [
                        'brand' => 'Brand',
                        'size' => 'Size',
                        'color' => 'Color',
                        'count' => 'Quantity',
                    ],
                ],
                'sku_strip_prefixes' => ['BT-'],
            ],
        ]);
    }

    private function jokerBodyHtml(): string
    {
        return <<<'HTML'
        <p>Great balloons.</p>
        <table>
        <thead><tr><th>Product Information</th><th>Details</th></tr></thead>
        <tbody>
          <tr><td>Brand</td><td>Sempertex</td></tr>
          <tr><td>Size</td><td>11 inches</td></tr>
          <tr><td>Material</td><td>Latex</td></tr>
          <tr><td>Color</td><td>Crystal Clear</td></tr>
          <tr><td>UPC</td><td>030625530118</td></tr>
          <tr><td>Vendor ID</td><td>53011</td></tr>
          <tr><td>Quantity</td><td>100 balloons</td></tr>
          <tr><td>Quantity per Box</td><td>10</td></tr>
        </tbody>
        </table>
        HTML;
    }

    /** The collection feed omits barcode; the per-product .json carries it + body_html. */
    private function fakeJoker(bool $variantBarcode = true): void
    {
        $collectionProduct = [
            'handle' => 'crystal-clear',
            'title' => '11in Sempertex Crystal Clear 100ct',
            'vendor' => 'Sempertex',
            'product_type' => 'Latex Balloons',
            'tags' => ['11in Latex', 'Latex'],
            'updated_at' => '2026-06-20T00:00:00Z',
            'variants' => [['sku' => 'BT-53011', 'price' => '18.58']],
        ];

        $variant = ['sku' => 'BT-53011'];
        if ($variantBarcode) {
            $variant['barcode'] = '030625530118';
        }

        Http::fake([
            'https://joker-test.com/collections/latex/products.json?limit=250&page=1' => Http::response(['products' => [$collectionProduct]], 200),
            'https://joker-test.com/collections/latex/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
            'https://joker-test.com/products/crystal-clear.json' => Http::response([
                'product' => ['body_html' => $this->jokerBodyHtml(), 'variants' => [$variant]],
            ], 200),
        ]);
    }

    public function test_enrich_stages_from_product_json_body_html_table(): void
    {
        Http::preventStrayRequests();

        $distributor = $this->joker();
        $this->fakeJoker();

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame(1, DistributorProduct::count());

        $product = DistributorProduct::first();

        $this->assertSame('BT-53011', $product->raw_sku);
        $this->assertSame('53011', $product->normalized_sku);
        $this->assertSame('030625530118', $product->upc);
        $this->assertSame(DistributorProductClassifier::SOLID_LATEX, $product->product_type);

        // Table attributes read from body_html.
        $this->assertSame(['Sempertex'], $product->raw_data['attributes']['Brand']);
        $this->assertSame(['11 inches'], $product->raw_data['attributes']['Size']);
        $this->assertSame(['Crystal Clear'], $product->raw_data['attributes']['Color']);
        // Shape synthesised (the table omits it).
        $this->assertSame(['Round'], $product->raw_data['attributes']['Balloon Type / Shape']);

        // No heavy HTML product page was fetched — only the light .json.
        Http::assertNotSent(fn ($request) => $request->url() === 'https://joker-test.com/products/crystal-clear');
    }

    public function test_enrich_reads_upc_from_body_html_when_variant_lacks_a_barcode(): void
    {
        Http::preventStrayRequests();

        // Even if the per-product variant had no barcode, the body_html "UPC" row is
        // the fallback so the product can still cluster (clustering is UPC-gated).
        $distributor = $this->joker();
        $this->fakeJoker(variantBarcode: false);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertSame('030625530118', DistributorProduct::first()->upc);
    }

    /** The rendered page's "Product Information" table (same <tr><td> shape). */
    private function jokerPageHtml(): string
    {
        return <<<'HTML'
        <h1>11in Sempertex Deluxe Almond White 100ct</h1>
        <h4><strong>Product Information:</strong></h4>
        <table>
          <tr><td><strong>Product Information</strong></td><td><strong>Details</strong></td></tr>
          <tr><td>Brand</td><td>Sempertex</td></tr>
          <tr><td>Size</td><td><span id="pi-size">11"</span></td></tr>
          <tr><td>Material</td><td>Latex</td></tr>
          <tr><td>Color</td><td><span id="pi-color">Deluxe Almond White</span></td></tr>
          <tr><td>UPC</td><td><span id="pi-upc">030625536622</span></td></tr>
          <tr><td>Quantity</td><td>100 balloons</td></tr>
        </table>
        HTML;
    }

    public function test_enrich_falls_back_to_the_page_when_body_html_has_no_table(): void
    {
        Http::preventStrayRequests();

        // "auto-info" products carry a narrative body_html with no spec table, but
        // the rendered page still has the table (from metafields).
        $distributor = $this->joker();

        Http::fake([
            'https://joker-test.com/collections/latex/products.json?limit=250&page=1' => Http::response(['products' => [[
                'handle' => 'almond-white',
                'title' => '11in Sempertex Deluxe Almond White 100ct',
                'vendor' => 'Sempertex',
                'product_type' => 'Latex Balloons',
                'tags' => ['11in Latex', 'auto-info'],
                'updated_at' => '2026-06-20T00:00:00Z',
                'variants' => [['sku' => 'BT-53662', 'barcode' => '030625536622', 'price' => '16.02']],
            ]]], 200),
            'https://joker-test.com/collections/latex/products.json?limit=250&page=2' => Http::response(['products' => []], 200),
            // body_html: narrative marketing copy, no table.
            'https://joker-test.com/products/almond-white.json' => Http::response([
                'product' => [
                    'body_html' => '<h2>About This Item</h2><p>A soft almond white latex balloon.</p>',
                    'variants' => [['sku' => 'BT-53662', 'barcode' => '030625536622']],
                ],
            ], 200),
            // the rendered page carries the table.
            'https://joker-test.com/products/almond-white' => Http::response($this->jokerPageHtml(), 200),
        ]);

        $this->artisan('catalog:ingest-distributor', [
            'slug' => $distributor->slug,
            '--enrich' => true,
            '--execute' => true,
        ])->assertSuccessful();

        $product = DistributorProduct::first();

        // Resolved from the page fallback, not parked as unknown.
        $this->assertSame(DistributorProductClassifier::SOLID_LATEX, $product->product_type);
        $this->assertSame('030625536622', $product->upc);
        $this->assertSame(['Sempertex'], $product->raw_data['attributes']['Brand']);
        $this->assertSame(['11"'], $product->raw_data['attributes']['Size']);
        $this->assertSame(['Deluxe Almond White'], $product->raw_data['attributes']['Color']);
    }
}
